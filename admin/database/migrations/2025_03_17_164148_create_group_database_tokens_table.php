<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('group_database_tokens', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('group_id');
            $table->string('name');
            $table->string('full_access_token')->unique();
            $table->string('read_only_token')->unique();
            $table->integer('expiration_day');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('group_id')
                ->references('id')
                ->on('group_databases')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('group_database_tokens');
    }
};
