<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('processing_tasks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('tool', 64)->index();
            $table->string('status', 20)->index();
            $table->json('input_metadata');
            $table->json('options');
            $table->json('output_metadata')->nullable();
            $table->string('session_hash', 64)->index();
            $table->string('ip_hash', 64)->nullable();
            $table->string('error_code', 64)->nullable();
            $table->text('error_message')->nullable();
            $table->dateTime('started_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->dateTime('expires_at')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('processing_tasks');
    }
};
