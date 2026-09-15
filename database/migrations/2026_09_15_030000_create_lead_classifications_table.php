<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tabla aparte y no columnas en leads: la clasificación tiene su propio ciclo (IA, revisión humana).
        Schema::create('lead_classifications', function (Blueprint $table) {
            $table->foreignUuid('lead_id')->primary()->constrained('leads')->cascadeOnDelete();
            $table->string('status', 20)->index();
            $table->string('category', 20)->nullable();
            $table->string('urgency', 10)->nullable();
            $table->double('confidence')->nullable();
            $table->text('reason')->nullable();
            $table->string('model', 80)->nullable();
            $table->string('review_reason', 255)->nullable();
            $table->string('reviewed_by', 255)->nullable();
            $table->timestampTz('created_at');
            $table->timestampTz('updated_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_classifications');
    }
};
