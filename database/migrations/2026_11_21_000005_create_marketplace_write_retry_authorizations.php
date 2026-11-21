<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketplace_write_retry_authorizations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('business_id');
            $table->unsignedBigInteger('incident_id');
            $table->unsignedBigInteger('attempt_id');
            $table->unsignedBigInteger('requested_by');
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->string('status', 24)->default('PENDING');
            $table->string('verification_outcome', 32);
            $table->string('evidence_reference', 500);
            $table->text('request_reason');
            $table->text('review_note')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->foreign('business_id', 'mwra_business_fk')->references('id')->on('businesses')->cascadeOnDelete();
            $table->foreign('incident_id', 'mwra_incident_fk')->references('id')->on('marketplace_write_incidents')->cascadeOnDelete();
            $table->foreign('attempt_id', 'mwra_attempt_fk')->references('id')->on('marketplace_write_attempts')->cascadeOnDelete();
            $table->foreign('requested_by', 'mwra_requester_fk')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('reviewed_by', 'mwra_reviewer_fk')->references('id')->on('users')->nullOnDelete();
            $table->index(['business_id', 'status', 'created_at'], 'mwra_business_status_idx');
            $table->index(['business_id', 'attempt_id'], 'mwra_business_attempt_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_write_retry_authorizations');
    }
};
