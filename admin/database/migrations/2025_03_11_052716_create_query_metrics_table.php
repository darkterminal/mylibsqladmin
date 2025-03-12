<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('query_metrics', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('database_id');
            $table->foreign('database_id')->references('id')->on('user_databases')->onDelete('cascade');
            $table->integer('rows_read_count');
            $table->integer('rows_written_count');
            $table->integer('storage_bytes_used');
            $table->integer('write_requests_delegated');
            $table->integer('replication_index');
            $table->integer('embedded_replica_frames_replicated');
            $table->integer('query_count');
            $table->float('elapsed_ms');
            $table->text('queries')->nullable();
            $table->timestamps();
        });

        Schema::create('top_queries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('main_id');
            $table->foreign('main_id')->references('id')->on('query_metrics')->onDelete('cascade');
            $table->integer('rows_written');
            $table->integer('rows_read');
            $table->text('query');
            $table->timestamps();
        });

        Schema::create('slowest_queries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('main_id');
            $table->foreign('main_id')->references('id')->on('query_metrics')->onDelete('cascade');
            $table->float('elapsed_ms');
            $table->integer('rows_read');
            $table->integer('rows_written');
            $table->text('query');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('slowest_queries');
        Schema::dropIfExists('top_queries');
        Schema::dropIfExists('query_metrics');
    }
};
