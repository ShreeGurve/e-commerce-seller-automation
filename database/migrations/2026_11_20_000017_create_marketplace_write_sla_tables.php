<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketplace_write_sla_policies', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('business_id');
            $table->string('severity', 16);
            $table->unsignedInteger('response_minutes')->default(60);
            $table->unsignedInteger('resolution_minutes')->default(1440);
            $table->boolean('enabled')->default(true);
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->foreign('business_id', 'mwsp_business_fk')->references('id')->on('businesses')->cascadeOnDelete();
            $table->foreign('updated_by', 'mwsp_user_fk')->references('id')->on('users')->nullOnDelete();
            $table->unique(['business_id', 'severity'], 'mwsp_business_severity_uq');
        });

        Schema::create('marketplace_write_sla_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('business_id');
            $table->unsignedBigInteger('incident_id');
            $table->string('event_type', 24);
            $table->timestamp('occurred_at');
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->foreign('business_id', 'mwse_business_fk')->references('id')->on('businesses')->cascadeOnDelete();
            $table->foreign('incident_id', 'mwse_incident_fk')->references('id')->on('marketplace_write_incidents')->cascadeOnDelete();
            $table->unique(['incident_id', 'event_type'], 'mwse_incident_event_uq');
            $table->index(['business_id', 'occurred_at'], 'mwse_business_time_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_write_sla_events');
        Schema::dropIfExists('marketplace_write_sla_policies');
    }
};
