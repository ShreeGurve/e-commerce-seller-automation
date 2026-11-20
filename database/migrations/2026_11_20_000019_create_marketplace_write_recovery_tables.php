<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('marketplace_write_recovery_cases', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('business_id');
            $table->unsignedBigInteger('incident_id');
            $table->string('playbook', 80);
            $table->string('status', 20)->default('OPEN');
            $table->unsignedBigInteger('owner_id')->nullable();
            $table->timestamp('target_at')->nullable();
            $table->text('resolution_summary')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->foreign('business_id', 'mwrc_business_fk')->references('id')->on('businesses')->cascadeOnDelete();
            $table->foreign('incident_id', 'mwrc_incident_fk')->references('id')->on('marketplace_write_incidents')->cascadeOnDelete();
            $table->foreign('owner_id', 'mwrc_owner_fk')->references('id')->on('users')->nullOnDelete();
            $table->foreign('created_by', 'mwrc_creator_fk')->references('id')->on('users')->nullOnDelete();
            $table->index(['business_id', 'status'], 'mwrc_business_status_idx');
        });

        Schema::create('marketplace_write_recovery_steps', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('recovery_case_id');
            $table->string('step_title', 160);
            $table->text('step_notes')->nullable();
            $table->string('status', 20)->default('PENDING');
            $table->unsignedBigInteger('completed_by')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->foreign('recovery_case_id', 'mwrs_case_fk')->references('id')->on('marketplace_write_recovery_cases')->cascadeOnDelete();
            $table->foreign('completed_by', 'mwrs_user_fk')->references('id')->on('users')->nullOnDelete();
            $table->index(['recovery_case_id', 'sort_order'], 'mwrs_case_order_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_write_recovery_steps');
        Schema::dropIfExists('marketplace_write_recovery_cases');
    }
};
