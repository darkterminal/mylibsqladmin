<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

class ActivityLog extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'team_id',
        'database_id',
        'user_id',
        'action',
        'query'
    ];

    public const ACTION_MAP = [
        'CREATE_TABLE' => 'Created table',
        'ALTER_TABLE' => 'Modified schema',
        'INSERT' => 'Added record',
        'UPDATE' => 'Updated record',
        'DELETE' => 'Deleted record',
        'CREATE_INDEX' => 'Added index',
        'DROP_INDEX' => 'Removed index',
        'SELECT' => 'Viewed data',
        'VACUUM' => 'Optimized database',
        'ANALYZE' => 'Performed analysis'
    ];

    public function team()
    {
        return $this->belongsTo(Team::class);
    }

    public function database()
    {
        return $this->belongsTo(UserDatabase::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function getTimeAgoAttribute()
    {
        return Carbon::parse($this->created_at)->diffForHumans();
    }

    public static function determineAction(string $query, int $databaseId): string
    {
        $normalized = strtoupper(trim($query));
        $tokens = preg_split('/\s+/', $normalized);
        $actionKey = null;
        $table = null;
        $query = str_replace(['MAIN.', 'MAIN'], '', strtoupper(trim($query)));

        logger("Query: $query");

        // Detect action and extract table name
        if (count($tokens) >= 2) {
            $firstTwo = "{$tokens[0]} {$tokens[1]}";
            switch ($firstTwo) {
                case 'CREATE TABLE':
                    $actionKey = 'CREATE_TABLE';
                    preg_match('/CREATE\s+TABLE\s+["`]?(\w+)["`]?/i', $query, $matches);
                    $table = $matches[1] ?? null;
                    break;
                case 'ALTER TABLE':
                    $actionKey = 'ALTER_TABLE';
                    break;
                case 'CREATE INDEX':
                    $actionKey = 'CREATE_INDEX';
                    break;
                case 'DROP INDEX':
                    $actionKey = 'DROP_INDEX';
                    break;
            }
        }

        if (!$actionKey && !empty($tokens)) {
            switch ($tokens[0]) {
                case 'INSERT':
                    $actionKey = 'INSERT';
                    preg_match('/INSERT\s+INTO\s+["`]?(\w+)["`]?/i', $query, $matches);
                    $table = $matches[1] ?? null;
                    break;
                case 'UPDATE':
                    $actionKey = 'UPDATE';
                    preg_match('/UPDATE\s+["`]?(\w+)["`]?/i', $query, $matches);
                    $table = $matches[1] ?? null;
                    break;
                case 'DELETE':
                    $actionKey = 'DELETE';
                    preg_match('/DELETE\s+FROM\s+["`]?(\w+)["`]?/i', $query, $matches);
                    $table = $matches[1] ?? null;
                    break;
                case 'SELECT':
                    $actionKey = 'SELECT';
                    preg_match('/FROM\s+["`]?(\w+)["`]?/i', $query, $matches);
                    $table = $matches[1] ?? null;
                    break;
                case 'VACUUM':
                    $actionKey = 'VACUUM';
                    break;
                case 'ANALYZE':
                    $actionKey = 'ANALYZE';
                    break;
            }
        }

        // Build description with table name
        $baseDescription = self::ACTION_MAP[$actionKey] ?? null;

        if (!$baseDescription)
            return null;

        return !empty($table) && strtolower($table)
            ? sprintf("%s [%s] table in database [%s]", $baseDescription, strtolower($table), UserDatabase::find($databaseId)->database_name)
            : $baseDescription;
    }
}
