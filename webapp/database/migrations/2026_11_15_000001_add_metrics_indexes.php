<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PERF-10 — verified via EXPLAIN QUERY PLAN on sqlite:
 *  - "WHERE created_at > ? ORDER BY id DESC" (summarized()) scanned query_metrics fully
 *  - "WHERE main_id IN (...)" (eager loads) scanned top_queries / slowest_queries fully
 *
 * Add the covering indexes. SQLite, MariaDB and Postgres all honour these
 * without shape changes.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('query_metrics', function (Blueprint $table) {
            $table->index('created_at', 'query_metrics_created_at_index');
        });

        Schema::table('top_queries', function (Blueprint $table) {
            $table->index('main_id', 'top_queries_main_id_index');
        });

        Schema::table('slowest_queries', function (Blueprint $table) {
            $table->index('main_id', 'slowest_queries_main_id_index');
        });
    }

    public function down(): void
    {
        Schema::table('query_metrics', function (Blueprint $table) {
            $table->dropIndex('query_metrics_created_at_index');
        });

        Schema::table('top_queries', function (Blueprint $table) {
            $table->dropIndex('top_queries_main_id_index');
        });

        Schema::table('slowest_queries', function (Blueprint $table) {
            $table->dropIndex('slowest_queries_main_id_index');
        });
    }
};
