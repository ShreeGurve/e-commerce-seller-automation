<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketplace_write_alert_escalation_rules', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('business_id')->index();
            $table->string('category', 64);
            $table->string('severity', 16);
            $table->unsignedSmallInteger('delay_minutes')->default(30);
            $table->unsignedSmallInteger('repeat_every_minutes')->default(60);
            $table->unsignedTinyInteger('max_escalations')->default(3);
            $table->boolean('notify_business_admins')->default(true);
            $table->boolean('enabled')->default(true);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->unique(['business_id', 'category', 'severity'], 'mw_alert_escalation_rule_unique');
            $table->foreign('business_id', 'mw_alert_escalation_business_fk')->references('id')->on('businesses')->cascadeOnDelete();
            $table->foreign('created_by', 'mw_alert_escalation_creator_fk')->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('marketplace_write_alert_escalation_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('business_id')->index();
            $table->string('source_notification_id', 64);
            $table->unsignedBigInteger('rule_id');
            $table->unsignedBigInteger('recipient_user_id');
            $table->unsignedTinyInteger('escalation_number');
            $table->timestamp('escalated_at');
            $table->timestamps();
            $table->unique(['source_notification_id', 'recipient_user_id', 'escalation_number'], 'mw_alert_escalation_log_unique');
            $table->foreign('business_id', 'mw_alert_escalation_log_business_fk')->references('id')->on('businesses')->cascadeOnDelete();
            $table->foreign('rule_id', 'mw_alert_escalation_log_rule_fk')->references('id')->on('marketplace_write_alert_escalation_rules')->cascadeOnDelete();
            $table->foreign('recipient_user_id', 'mw_alert_escalation_log_user_fk')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_write_alert_escalation_logs');
        Schema::dropIfExists('marketplace_write_alert_escalation_rules');
    }
};
