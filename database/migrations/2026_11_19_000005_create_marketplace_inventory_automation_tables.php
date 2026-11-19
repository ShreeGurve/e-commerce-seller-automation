<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('marketplace_inventory_automation_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('business_id');
            $table->foreign('business_id', 'mias_business_fk')->references('id')->on('businesses')->cascadeOnDelete();
            $table->unsignedBigInteger('marketplace_connection_id');
            $table->foreign('marketplace_connection_id', 'mias_connection_fk')->references('id')->on('marketplace_connections')->cascadeOnDelete();
            $table->boolean('enabled')->default(false);
            $table->unsignedSmallInteger('interval_hours')->default(6);
            $table->unsignedInteger('low_stock_threshold')->default(5);
            $table->timestamp('last_scheduled_at')->nullable();
            $table->timestamp('next_run_at')->nullable();
            $table->timestamps();
            $table->unique('marketplace_connection_id', 'mias_connection_unique');
            $table->index(['enabled', 'next_run_at'], 'mias_due_idx');
        });

        Schema::create('marketplace_inventory_automation_runs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('business_id');
            $table->foreign('business_id', 'miar_business_fk')->references('id')->on('businesses')->cascadeOnDelete();
            $table->unsignedBigInteger('marketplace_connection_id');
            $table->foreign('marketplace_connection_id', 'miar_connection_fk')->references('id')->on('marketplace_connections')->cascadeOnDelete();
            $table->unsignedBigInteger('sync_run_id')->nullable();
            $table->foreign('sync_run_id', 'miar_sync_fk')->references('id')->on('marketplace_sync_runs')->nullOnDelete();
            $table->unsignedBigInteger('reconciliation_run_id')->nullable();
            $table->foreign('reconciliation_run_id', 'miar_recon_fk')->references('id')->on('marketplace_inventory_reconciliation_runs')->nullOnDelete();
            $table->string('status', 20)->default('QUEUED');
            $table->text('message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
            $table->index(['business_id', 'created_at'], 'miar_business_created_idx');
            $table->index(['marketplace_connection_id', 'status'], 'miar_connection_status_idx');
        });

        Schema::create('marketplace_inventory_alerts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('business_id');
            $table->foreign('business_id', 'miaa_business_fk')->references('id')->on('businesses')->cascadeOnDelete();
            $table->unsignedBigInteger('marketplace_connection_id');
            $table->foreign('marketplace_connection_id', 'miaa_connection_fk')->references('id')->on('marketplace_connections')->cascadeOnDelete();
            $table->string('alert_key', 190);
            $table->string('type', 32);
            $table->string('severity', 16)->default('warning');
            $table->string('status', 20)->default('OPEN');
            $table->string('marketplace_sku', 120)->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('first_seen_at');
            $table->timestamp('last_seen_at');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            $table->unique(['marketplace_connection_id', 'alert_key'], 'miaa_connection_key_unique');
            $table->index(['business_id', 'status', 'severity'], 'miaa_business_status_severity_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_inventory_alerts');
        Schema::dropIfExists('marketplace_inventory_automation_runs');
        Schema::dropIfExists('marketplace_inventory_automation_settings');
    }
};
