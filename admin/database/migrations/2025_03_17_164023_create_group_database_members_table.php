<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('group_database_members', function (Blueprint $table) {
            $table->unsignedBigInteger('group_id');
            $table->unsignedBigInteger('database_id');

            $table->foreign('group_id')
                ->references('id')
                ->on('group_databases')
                ->onDelete('cascade');

            $table->foreign('database_id')
                ->references('id')
                ->on('user_databases')
                ->onDelete('cascade');

            $table->primary(['group_id', 'database_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('group_database_members');
    }
};
