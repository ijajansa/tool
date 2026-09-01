<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_admin')->default(false)->index();
        });
        Schema::create('tool_settings', function (Blueprint $table) {
            $table->string('tool', 64)->primary();
            $table->boolean('enabled')->default(true);
            $table->unsignedInteger('rate_limit')->nullable();
            $table->unsignedInteger('max_upload_kb')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tool_settings');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('is_admin'));
    }
};
