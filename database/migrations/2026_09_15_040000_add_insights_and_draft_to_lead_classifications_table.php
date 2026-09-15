<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lead_classifications', function (Blueprint $table) {
            $table->string('summary', 300)->nullable()->after('reason');
            $table->json('missing_information')->nullable()->after('summary');
            $table->json('contradictions')->nullable()->after('missing_information');
            $table->text('draft_message')->nullable()->after('contradictions');
            $table->string('draft_status', 20)->nullable()->after('draft_message');
            $table->string('draft_approved_by', 255)->nullable()->after('draft_status');
            $table->timestampTz('draft_approved_at')->nullable()->after('draft_approved_by');
        });
    }

    public function down(): void
    {
        Schema::table('lead_classifications', function (Blueprint $table) {
            $table->dropColumn(['summary', 'missing_information', 'contradictions', 'draft_message', 'draft_status', 'draft_approved_by', 'draft_approved_at']);
        });
    }
};
