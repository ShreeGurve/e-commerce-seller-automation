<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('marketplace_sync_runs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('business_id');
            $table->foreign('business_id', 'msr_business_fk')->references('id')->on('businesses')->cascadeOnDelete();
            $table->unsignedBigInteger('marketplace_connection_id');
            $table->foreign('marketplace_connection_id', 'msr_connection_fk')->references('id')->on('marketplace_connections')->cascadeOnDelete();
            $table->unsignedBigInteger('marketplace_id');
            $table->foreign('marketplace_id', 'msr_marketplace_fk')->references('id')->on('marketplaces')->restrictOnDelete();
            $table->string('resource', 24);
            $table->string('status', 20)->default('QUEUED');
            $table->date('requested_from')->nullable();
            $table->date('requested_to')->nullable();
            $table->unsignedInteger('pages_processed')->default(0);
            $table->unsignedInteger('records_fetched')->default(0);
            $table->unsignedInteger('records_created')->default(0);
            $table->unsignedInteger('records_updated')->default(0);
            $table->unsignedInteger('records_skipped')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
            $table->index(['business_id', 'marketplace_connection_id', 'created_at'], 'msr_business_connection_created_idx');
            $table->index(['resource', 'status'], 'msr_resource_status_idx');
        });

        Schema::create('marketplace_sync_records', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('business_id');
            $table->foreign('business_id', 'msrec_business_fk')->references('id')->on('businesses')->cascadeOnDelete();
            $table->unsignedBigInteger('marketplace_connection_id');
            $table->foreign('marketplace_connection_id', 'msrec_connection_fk')->references('id')->on('marketplace_connections')->cascadeOnDelete();
            $table->unsignedBigInteger('marketplace_id');
            $table->foreign('marketplace_id', 'msrec_marketplace_fk')->references('id')->on('marketplaces')->restrictOnDelete();
            $table->unsignedBigInteger('sync_run_id')->nullable();
            $table->foreign('sync_run_id', 'msrec_sync_run_fk')->references('id')->on('marketplace_sync_runs')->nullOnDelete();
            $table->string('resource', 24);
            $table->string('external_id', 150);
            $table->json('payload');
            $table->char('fingerprint', 64);
            $table->timestamp('first_seen_at');
            $table->timestamp('last_seen_at');
            $table->timestamps();
            $table->unique(['marketplace_connection_id', 'resource', 'external_id'], 'msrec_connection_resource_external_unique');
            $table->index(['business_id', 'resource', 'last_seen_at'], 'msrec_business_resource_seen_idx');
        });

        Schema::create('marketplace_inventory_snapshots', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('business_id');
            $table->foreign('business_id', 'mis_business_fk')->references('id')->on('businesses')->cascadeOnDelete();
            $table->unsignedBigInteger('marketplace_connection_id');
            $table->foreign('marketplace_connection_id', 'mis_connection_fk')->references('id')->on('marketplace_connections')->cascadeOnDelete();
            $table->unsignedBigInteger('marketplace_id');
            $table->foreign('marketplace_id', 'mis_marketplace_fk')->references('id')->on('marketplaces')->restrictOnDelete();
            $table->string('marketplace_sku', 120);
            $table->string('fulfillment_channel', 30)->default('UNKNOWN');
            $table->integer('available_qty')->default(0);
            $table->integer('reserved_qty')->default(0);
            $table->integer('inbound_qty')->default(0);
            $table->timestamp('snapshot_at');
            $table->json('raw_payload')->nullable();
            $table->timestamps();
            $table->unique(['marketplace_connection_id', 'marketplace_sku', 'fulfillment_channel'], 'mis_connection_sku_channel_unique');
            $table->index(['business_id', 'marketplace_id', 'snapshot_at'], 'mis_business_marketplace_snapshot_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_inventory_snapshots');
        Schema::dropIfExists('marketplace_sync_records');
        Schema::dropIfExists('marketplace_sync_runs');
    }
};
