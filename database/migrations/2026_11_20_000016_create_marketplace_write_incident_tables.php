<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketplace_write_incidents', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('business_id');
            $table->unsignedBigInteger('attempt_id');
            $table->string('incident_key', 64);
            $table->string('title', 180);
            $table->text('description')->nullable();
            $table->string('severity', 16)->default('MEDIUM');
            $table->string('status', 20)->default('OPEN');
            $table->unsignedBigInteger('assigned_to')->nullable();
            $table->unsignedBigInteger('opened_by')->nullable();
            $table->unsignedBigInteger('resolved_by')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            $table->foreign('business_id', 'mwi_business_fk')->references('id')->on('businesses')->cascadeOnDelete();
            $table->foreign('attempt_id', 'mwi_attempt_fk')->references('id')->on('marketplace_write_attempts')->cascadeOnDelete();
            $table->foreign('assigned_to', 'mwi_assignee_fk')->references('id')->on('users')->nullOnDelete();
            $table->foreign('opened_by', 'mwi_opener_fk')->references('id')->on('users')->nullOnDelete();
            $table->foreign('resolved_by', 'mwi_resolver_fk')->references('id')->on('users')->nullOnDelete();
            $table->unique(['business_id', 'incident_key'], 'mwi_business_key_uq');
            $table->index(['business_id', 'status', 'severity'], 'mwi_business_status_idx');
            $table->index(['business_id', 'created_at'], 'mwi_business_created_idx');
        });

        Schema::create('marketplace_write_incident_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('incident_id');
            $table->unsignedBigInteger('business_id');
            $table->unsignedBigInteger('recorded_by')->nullable();
            $table->string('event_type', 24);
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20)->nullable();
            $table->text('note')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('recorded_at');
            $table->timestamps();
            $table->foreign('incident_id', 'mwie_incident_fk')->references('id')->on('marketplace_write_incidents')->cascadeOnDelete();
            $table->foreign('business_id', 'mwie_business_fk')->references('id')->on('businesses')->cascadeOnDelete();
            $table->foreign('recorded_by', 'mwie_user_fk')->references('id')->on('users')->nullOnDelete();
            $table->index(['business_id', 'incident_id', 'recorded_at'], 'mwie_business_history_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_write_incident_events');
        Schema::dropIfExists('marketplace_write_incidents');
    }
};
