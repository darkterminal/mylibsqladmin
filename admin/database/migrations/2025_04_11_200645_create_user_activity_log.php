<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('user_activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('type', 50); // login, logout, profile_update, etc.
            $table->string('description');
            $table->json('metadata')->nullable();
            $table->timestamp('timestamp');
            $table->timestamps();

            // Indexes for common query patterns
            $table->index(['user_id', 'type']);
            $table->index('timestamp');
        });
    }

    public function down()
    {
        Schema::dropIfExists('user_activity_logs');
    }
};
