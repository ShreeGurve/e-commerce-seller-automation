<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('marketplace_inventory_reconciliation_runs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('business_id');
            $table->foreign('business_id', 'mirr_business_fk')->references('id')->on('businesses')->cascadeOnDelete();
            $table->unsignedBigInteger('marketplace_connection_id');
            $table->foreign('marketplace_connection_id', 'mirr_connection_fk')->references('id')->on('marketplace_connections')->cascadeOnDelete();
            $table->unsignedBigInteger('marketplace_id');
            $table->foreign('marketplace_id', 'mirr_marketplace_fk')->references('id')->on('marketplaces')->restrictOnDelete();
            $table->string('status', 20)->default('COMPLETED');
            $table->unsignedInteger('items_checked')->default(0);
            $table->unsignedInteger('matched_count')->default(0);
            $table->unsignedInteger('mismatch_count')->default(0);
            $table->unsignedInteger('unmapped_count')->default(0);
            $table->unsignedInteger('managed_channel_count')->default(0);
            $table->timestamp('snapshot_cutoff_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['business_id', 'created_at'], 'mirr_business_created_idx');
            $table->index(['marketplace_connection_id', 'created_at'], 'mirr_connection_created_idx');
        });

        Schema::create('marketplace_inventory_reconciliation_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('reconciliation_run_id');
            $table->foreign('reconciliation_run_id', 'miri_run_fk')->references('id')->on('marketplace_inventory_reconciliation_runs')->cascadeOnDelete();
            $table->unsignedBigInteger('inventory_snapshot_id');
            $table->foreign('inventory_snapshot_id', 'miri_snapshot_fk')->references('id')->on('marketplace_inventory_snapshots')->cascadeOnDelete();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->foreign('product_id', 'miri_product_fk')->references('id')->on('products')->nullOnDelete();
            $table->unsignedBigInteger('inventory_correction_request_id')->nullable();
            $table->foreign('inventory_correction_request_id', 'miri_correction_fk')->references('id')->on('inventory_correction_requests')->nullOnDelete();
            $table->string('marketplace_sku', 120);
            $table->string('fulfillment_channel', 30)->default('UNKNOWN');
            $table->integer('marketplace_qty')->default(0);
            $table->integer('internal_qty')->nullable();
            $table->integer('variance')->nullable();
            $table->string('status', 24)->default('UNMAPPED');
            $table->boolean('correction_eligible')->default(false);
            $table->timestamp('snapshot_at')->nullable();
            $table->timestamps();
            $table->unique(['reconciliation_run_id', 'inventory_snapshot_id'], 'miri_run_snapshot_unique');
            $table->index(['reconciliation_run_id', 'status'], 'miri_run_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_inventory_reconciliation_items');
        Schema::dropIfExists('marketplace_inventory_reconciliation_runs');
    }
};
