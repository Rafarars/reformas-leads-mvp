<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('outbox_messages', function (Blueprint $table) {
            // Un reintento manual abre una ventana nueva de 24 h antes de volver a apartar el mensaje.
            $table->timestampTz('last_replayed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('outbox_messages', function (Blueprint $table) {
            $table->dropColumn('last_replayed_at');
        });
    }
};
