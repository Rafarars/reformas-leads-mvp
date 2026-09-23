<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('telegram_links', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            // Un chat de Telegram solo puede estar vinculado a una cuenta de usuario a la vez.
            $table->string('chat_id', 40)->nullable()->unique();
            // Solo el hash SHA-256: el token en claro no se guarda en ningún sitio.
            $table->char('token_hash', 64)->nullable()->unique();
            $table->timestampTz('token_expires_at')->nullable();
            $table->timestampTz('token_used_at')->nullable();
            $table->timestampTz('linked_at')->nullable();
            $table->timestampTz('created_at');
            $table->timestampTz('updated_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telegram_links');
    }
};
