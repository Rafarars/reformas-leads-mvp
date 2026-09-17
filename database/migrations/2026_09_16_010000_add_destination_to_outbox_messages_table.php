<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('outbox_messages', function (Blueprint $table) {
            // Los mensajes anteriores iban todos al único destino que había: el CRM.
            $table->string('destination', 30)->default('crm')->after('id');
        });

        Schema::table('outbox_messages', function (Blueprint $table) {
            $table->string('destination', 30)->default(null)->change();
        });
    }

    public function down(): void
    {
        Schema::table('outbox_messages', function (Blueprint $table) {
            $table->dropColumn('destination');
        });
    }
};
