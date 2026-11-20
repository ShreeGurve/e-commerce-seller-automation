<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('marketplace_write_evidence', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('business_id');
            $table->string('subject_type', 40)->default('GENERAL');
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('evidence_type', 40);
            $table->string('title', 180);
            $table->text('description')->nullable();
            $table->text('evidence_url')->nullable();
            $table->string('reference_code', 120)->nullable();
            $table->unsignedBigInteger('recorded_by');
            $table->timestamps();

            $table->index(['business_id', 'subject_type', 'subject_id'], 'mw_evidence_subject_idx');
            $table->index(['business_id', 'evidence_type'], 'mw_evidence_type_idx');
            $table->index(['business_id', 'created_at'], 'mw_evidence_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_write_evidence');
    }
};
