<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('marketplace_write_verifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attempt_id')->unique('mwv_attempt_uq')->constrained('marketplace_write_attempts', 'id', 'mwv_attempt_fk')->cascadeOnDelete();
            $table->unsignedBigInteger('business_id');
            $table->foreign('business_id', 'mwv_business_fk')->references('id')->on('businesses')->cascadeOnDelete();
            $table->foreignId('marketplace_connection_id')->constrained('marketplace_connections', 'id', 'mwv_conn_fk')->restrictOnDelete();
            $table->string('status', 24);
            $table->string('issue_code', 100)->nullable();
            $table->string('external_submission_id', 150)->nullable();
            $table->text('verification_note');
            $table->string('evidence_url', 2048)->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users', 'id', 'mwv_user_fk')->nullOnDelete();
            $table->timestamp('checked_at');
            $table->timestamps();
            $table->index(['business_id', 'status', 'checked_at'], 'mwv_business_status_idx');
        });

        Schema::create('marketplace_write_verification_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('verification_id')->constrained('marketplace_write_verifications', 'id', 'mwve_verification_fk')->cascadeOnDelete();
            $table->foreignId('attempt_id')->constrained('marketplace_write_attempts', 'id', 'mwve_attempt_fk')->cascadeOnDelete();
            $table->string('from_status', 24)->nullable();
            $table->string('to_status', 24);
            $table->string('issue_code', 100)->nullable();
            $table->text('verification_note');
            $table->string('evidence_url', 2048)->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users', 'id', 'mwve_user_fk')->nullOnDelete();
            $table->timestamp('recorded_at');
            $table->timestamps();
            $table->index(['attempt_id', 'recorded_at'], 'mwve_attempt_recorded_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_write_verification_events');
        Schema::dropIfExists('marketplace_write_verifications');
    }
};
