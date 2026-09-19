<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lead_attachments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('lead_id')->constrained('leads')->cascadeOnDelete();
            $table->string('kind', 10);
            $table->string('file_name', 200);
            $table->string('content_type', 60);
            $table->unsignedInteger('size_bytes');
            // Dónde está el archivo. La calcula el dominio con identificadores, nunca con el nombre recibido.
            $table->string('path', 255);
            $table->timestampTz('uploaded_at');
            $table->index(['lead_id', 'uploaded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_attachments');
    }
};
