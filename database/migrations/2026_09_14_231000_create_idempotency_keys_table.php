<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('idempotency_keys', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('api_client_id')->constrained('api_clients')->cascadeOnDelete();
            $table->string('key', 255);
            $table->char('request_fingerprint', 64);
            $table->string('status', 20);
            $table->unsignedSmallInteger('response_status')->nullable();
            $table->text('response_body')->nullable();
            $table->timestampTz('created_at');
            // Por cliente: dos integradores pueden usar la misma clave sin pisarse.
            $table->unique(['api_client_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('idempotency_keys');
    }
};
