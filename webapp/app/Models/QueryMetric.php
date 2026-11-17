<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QueryMetric extends Model
{
    protected $table = 'query_metrics';
    protected $fillable = [
        'database_id',
        'rows_read_count',
        'rows_written_count',
        'storage_bytes_used',
        'write_requests_delegated',
        'replication_index',
        'embedded_replica_frames_replicated',
        'query_count',
        'elapsed_ms',
        'queries',
    ];

    public function database()
    {
        return $this->belongsTo(UserDatabase::class);
    }

    public function topQueries()
    {
        return $this->hasMany(TopQuery::class, 'main_id');
    }

    public function slowestQueries()
    {
        return $this->hasMany(SlowestQuery::class, 'main_id');
    }

    /**
     * Aggregate the last 24 hours of metrics for the /dashboard page.
     *
     * Everything here is computed server-side so the client can render
     * honest numbers:
     * - totals for the window + the previous 24h baseline (for deltas)
     * - a zero-filled hourly series (25 buckets, storage forward-filled)
     * - per-database breakdowns with previous-window baselines
     * - Top/Slowest queries merged across ALL snapshots in the window
     *   (queries keyed by their text), both per database and team-wide
     */
    public static function dashboard(): array
    {
        $since = now()->subDay();
        $previousSince = now()->subDays(2);

        $metrics = self::with(['topQueries', 'slowestQueries'])
            ->where('created_at', '>', $since)
            ->orderBy('created_at')
            ->get();

        $previous = self::whereBetween('created_at', [$previousSince, $since])
            ->get(['database_id', 'rows_read_count', 'rows_written_count', 'query_count']);

        $databaseIds = $metrics->pluck('database_id')
            ->merge($previous->pluck('database_id'))
            ->unique();

        $databases = UserDatabase::whereIn('id', $databaseIds)
            ->get(['id', 'database_name', 'is_schema'])
            ->keyBy('id');

        $prevByDb = $previous->groupBy('database_id')->map(fn($rows) => [
            'query_count' => (int) $rows->sum('query_count'),
            'rows_read' => (int) $rows->sum('rows_read_count'),
            'rows_written' => (int) $rows->sum('rows_written_count'),
        ]);

        $perDatabase = $metrics->groupBy('database_id')
            ->map(fn($rows, $dbId) => $databases->get((int) $dbId)
                ? self::aggregate($rows, [
                    'database_id' => (int) $dbId,
                    'name' => $databases->get((int) $dbId)->database_name,
                    'is_schema' => (bool) $databases->get((int) $dbId)->is_schema,
                ])
                : null)
            ->filter()
            ->map(function (array $entry) use ($prevByDb) {
                $entry['previous'] = $prevByDb->get($entry['database_id'], [
                    'query_count' => 0,
                    'rows_read' => 0,
                    'rows_written' => 0,
                ]);
                return $entry;
            })
            ->values();

        $totals = [
            'query_count' => (int) $perDatabase->sum('query_count'),
            'rows_read' => (int) $perDatabase->sum('rows_read'),
            'rows_written' => (int) $perDatabase->sum('rows_written'),
            'storage_bytes' => (int) $perDatabase->sum('storage_bytes'),
        ];

        $prevTotals = [
            'query_count' => (int) $previous->sum('query_count'),
            'rows_read' => (int) $previous->sum('rows_read_count'),
            'rows_written' => (int) $previous->sum('rows_written_count'),
        ];

        return [
            'generated_at' => now()->toIso8601String(),
            'window_hours' => 24,
            'totals' => $totals,
            'previous' => $prevTotals,
            'series' => self::seriesFrom($metrics),
            'top_queries' => self::mergeQueryTops($perDatabase->pluck('top_queries')),
            'slowest_queries' => self::mergeQuerySlowest($perDatabase->pluck('slowest_queries')),
            'databases' => $perDatabase->all(),
        ];
    }

    /**
     * Sum of base fields + hourly series + merged Top/Slowest queries
     * for one group of metric rows.
     *
     * @param  iterable<QueryMetric>  $rows
     */
    private static function aggregate($rows, array $base): array
    {
        $rows = collect($rows);

        $topMaps = [];
        $slowestMaps = [];
        foreach ($rows as $metric) {
            $merged = self::queryMerges($metric);
            $topMaps[] = $merged['top'];
            $slowestMaps[] = $merged['slowest'];
        }

        return array_merge($base, [
            'query_count' => (int) $rows->sum('query_count'),
            'rows_read' => (int) $rows->sum('rows_read_count'),
            'rows_written' => (int) $rows->sum('rows_written_count'),
            'storage_bytes' => (int) $rows->max('storage_bytes_used'),
            'avg_elapsed_ms' => $rows->count() ? round((float) $rows->avg('elapsed_ms'), 1) : null,
            'samples' => $rows->count(),
            'series' => self::seriesFrom($rows),
            'top_queries' => self::mergeQueryTops($topMaps),
            'slowest_queries' => self::mergeQuerySlowest($slowestMaps),
        ]);
    }

    /**
     * 25 zero-filled hourly buckets ending at the current hour.
     * Query/row counts default to 0; storage is forward-filled from the
     * last known value so the area chart does not dip to zero during
     * quiet hours.
     *
     * @param  iterable<QueryMetric>  $rows
     */
    private static function seriesFrom($rows): array
    {
        $rows = collect($rows);
        $buckets = [];

        $start = now()->subHours(24)->startOfHour();
        for ($i = 0; $i <= 24; $i++) {
            $hour = $start->copy()->addHours($i)->format('Y-m-d H:00');
            $buckets[$hour] = [
                't' => $hour,
                'queries' => 0,
                'rows_read' => 0,
                'rows_written' => 0,
                'storage_bytes' => null,
            ];
        }

        $byHour = $rows->groupBy(fn($m) => $m->created_at->format('Y-m-d H:00'));
        foreach ($byHour as $hour => $bucketRows) {
            if (! array_key_exists($hour, $buckets)) {
                continue;
            }
            $buckets[$hour]['queries'] = (int) $bucketRows->sum('query_count');
            $buckets[$hour]['rows_read'] = (int) $bucketRows->sum('rows_read_count');
            $buckets[$hour]['rows_written'] = (int) $bucketRows->sum('rows_written_count');
            $buckets[$hour]['storage_bytes'] = (int) $bucketRows->max('storage_bytes_used');
        }

        $storage = 0;
        foreach ($buckets as $hour => $bucket) {
            if ($bucket['storage_bytes'] !== null) {
                $storage = $bucket['storage_bytes'];
            }
            $buckets[$hour]['storage_bytes'] = $storage;
        }

        return array_values($buckets);
    }

    /**
     * Per-snapshot query merges (query text => counters).
     *
     * @return array{top: array<string, array>, slowest: array<string, array>}
     */
    private static function queryMerges($metric): array
    {
        $top = [];
        foreach ($metric->topQueries as $query) {
            $key = trim((string) ($query->query ?? ''));
            if ($key === '') {
                continue;
            }
            $entry = $top[$key] ?? ['query' => $key, 'rows_read' => 0, 'rows_written' => 0, 'occurrences' => 0];
            $entry['rows_read'] += (int) ($query->rows_read ?? 0);
            $entry['rows_written'] += (int) ($query->rows_written ?? 0);
            $entry['occurrences'] += 1;
            $top[$key] = $entry;
        }

        $slowest = [];
        foreach ($metric->slowestQueries as $query) {
            $key = trim((string) ($query->query ?? ''));
            if ($key === '') {
                continue;
            }
            $entry = $slowest[$key] ?? ['query' => $key, 'elapsed_ms' => 0.0, 'rows_read' => 0, 'rows_written' => 0, 'occurrences' => 0];
            $entry['elapsed_ms'] = max($entry['elapsed_ms'], (float) ($query->elapsed_ms ?? 0));
            $entry['rows_read'] += (int) ($query->rows_read ?? 0);
            $entry['rows_written'] += (int) ($query->rows_written ?? 0);
            $entry['occurrences'] += 1;
            $slowest[$key] = $entry;
        }

        return ['top' => $top, 'slowest' => $slowest];
    }

    /**
     * Merge already-merged query maps (per snapshot or per database),
     * summing rows/occurrences and taking the worst (max) elapsed time.
     * Returns the 10 most-read / slowest queries.
     */
    private static function mergeQueryTops($collections): array
    {
        $merged = [];
        foreach ($collections as $map) {
            foreach ((array) $map as $key => $q) {
                $key = is_string($key) ? $key : trim((string) ($q['query'] ?? ''));
                if ($key === '') {
                    continue;
                }
                $entry = $merged[$key] ?? ['query' => $key, 'rows_read' => 0, 'rows_written' => 0, 'occurrences' => 0];
                $entry['rows_read'] += (int) ($q['rows_read'] ?? 0);
                $entry['rows_written'] += (int) ($q['rows_written'] ?? 0);
                $entry['occurrences'] += (int) ($q['occurrences'] ?? 0);
                $merged[$key] = $entry;
            }
        }
        usort($merged, fn($a, $b) => $b['rows_read'] <=> $a['rows_read']);

        return array_slice(array_values($merged), 0, 10);
    }

    private static function mergeQuerySlowest($collections): array
    {
        $merged = [];
        foreach ($collections as $map) {
            foreach ((array) $map as $key => $q) {
                $key = is_string($key) ? $key : trim((string) ($q['query'] ?? ''));
                if ($key === '') {
                    continue;
                }
                $entry = $merged[$key] ?? ['query' => $key, 'elapsed_ms' => 0.0, 'rows_read' => 0, 'rows_written' => 0, 'occurrences' => 0];
                $entry['elapsed_ms'] = max($entry['elapsed_ms'], (float) ($q['elapsed_ms'] ?? 0));
                $entry['rows_read'] += (int) ($q['rows_read'] ?? 0);
                $entry['rows_written'] += (int) ($q['rows_written'] ?? 0);
                $entry['occurrences'] += (int) ($q['occurrences'] ?? 0);
                $merged[$key] = $entry;
            }
        }
        usort($merged, fn($a, $b) => $b['elapsed_ms'] <=> $a['elapsed_ms']);

        // round elapsed for transport cleanliness
        return array_map(function (array $q) {
            $q['elapsed_ms'] = round((float) $q['elapsed_ms'], 1);
            return $q;
        }, array_slice(array_values($merged), 0, 10));
    }

}
