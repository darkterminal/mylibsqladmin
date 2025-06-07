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
        Schema::create('database_user_permissions', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('database_id')->constrained('user_databases')->cascadeOnDelete();
            $table->enum('permission', ['read', 'write', 'full'])->default('read');
            $table->timestamps();

            $table->primary(['user_id', 'database_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('database_user_permissions');
    }
};
