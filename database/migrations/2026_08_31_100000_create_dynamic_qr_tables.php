<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dynamic_qr_codes', function (Blueprint $table) {
            $table->id();
            $table->string('public_id', 32)->unique();
            $table->text('destination_url');
            $table->string('management_token_hash', 64);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
        Schema::create('dynamic_qr_scans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dynamic_qr_code_id')->constrained()->cascadeOnDelete();
            $table->dateTime('scanned_at')->index();
            $table->string('device', 20);
            $table->string('referrer_domain')->nullable();
            $table->string('ip_hash', 64);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dynamic_qr_scans');
        Schema::dropIfExists('dynamic_qr_codes');
    }
};
