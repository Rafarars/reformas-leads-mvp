<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leads', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 120);
            // Indexados: la regla de no duplicar busca por teléfono o por email en cada envío.
            $table->string('phone', 16)->index();
            $table->string('email', 254)->index();
            $table->string('renovation_type', 20);
            $table->string('zone', 120)->nullable();
            $table->unsignedInteger('budget')->nullable();
            $table->text('description')->nullable();
            $table->string('source', 40);
            $table->string('status', 20);
            $table->unsignedInteger('contact_count');
            $table->timestampTz('received_at');
            $table->timestampTz('last_contact_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};
