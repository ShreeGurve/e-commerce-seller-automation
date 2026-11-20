<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('marketplace_write_escalation_rules', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('business_id');
            $table->string('severity', 16);
            $table->string('breach_type', 16)->default('RESPONSE');
            $table->unsignedInteger('after_minutes')->default(15);
            $table->unsignedBigInteger('escalate_to')->nullable();
            $table->boolean('enabled')->default(true);
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->foreign('business_id', 'mwer_business_fk')->references('id')->on('businesses')->cascadeOnDelete();
            $table->foreign('escalate_to', 'mwer_target_fk')->references('id')->on('users')->nullOnDelete();
            $table->foreign('updated_by', 'mwer_editor_fk')->references('id')->on('users')->nullOnDelete();
            $table->unique(['business_id', 'severity', 'breach_type'], 'mwer_rule_scope_uq');
        });

        Schema::create('marketplace_write_escalations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('business_id');
            $table->unsignedBigInteger('incident_id');
            $table->unsignedBigInteger('rule_id');
            $table->unsignedBigInteger('escalated_to')->nullable();
            $table->timestamp('escalated_at');
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->foreign('business_id', 'mwe_business_fk')->references('id')->on('businesses')->cascadeOnDelete();
            $table->foreign('incident_id', 'mwe_incident_fk')->references('id')->on('marketplace_write_incidents')->cascadeOnDelete();
            $table->foreign('rule_id', 'mwe_rule_fk')->references('id')->on('marketplace_write_escalation_rules')->cascadeOnDelete();
            $table->foreign('escalated_to', 'mwe_target_fk')->references('id')->on('users')->nullOnDelete();
            $table->unique(['incident_id', 'rule_id'], 'mwe_incident_rule_uq');
            $table->index(['business_id', 'escalated_at'], 'mwe_business_time_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_write_escalations');
        Schema::dropIfExists('marketplace_write_escalation_rules');
    }
};
