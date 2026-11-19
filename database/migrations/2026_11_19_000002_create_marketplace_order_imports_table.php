<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('marketplace_order_imports', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('business_id');
            $table->foreign('business_id', 'moi_business_fk')->references('id')->on('businesses')->cascadeOnDelete();
            $table->unsignedBigInteger('marketplace_connection_id');
            $table->foreign('marketplace_connection_id', 'moi_connection_fk')->references('id')->on('marketplace_connections')->cascadeOnDelete();
            $table->unsignedBigInteger('marketplace_id');
            $table->foreign('marketplace_id', 'moi_marketplace_fk')->references('id')->on('marketplaces')->restrictOnDelete();
            $table->unsignedBigInteger('sync_record_id');
            $table->foreign('sync_record_id', 'moi_sync_record_fk')->references('id')->on('marketplace_sync_records')->cascadeOnDelete();
            $table->unsignedBigInteger('order_id')->nullable();
            $table->foreign('order_id', 'moi_order_fk')->references('id')->on('orders')->nullOnDelete();
            $table->string('external_order_id', 60);
            $table->string('status', 20)->default('PENDING'); // PENDING|APPROVED|REJECTED|IMPORTED|FAILED
            $table->json('preview_payload');
            $table->json('validation_errors')->nullable();
            $table->boolean('deduct_stock')->default(false);
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->foreign('reviewed_by', 'moi_reviewer_fk')->references('id')->on('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('imported_at')->nullable();
            $table->text('failure_message')->nullable();
            $table->timestamps();
            $table->unique(['marketplace_connection_id', 'external_order_id'], 'moi_connection_external_unique');
            $table->unique('sync_record_id', 'moi_sync_record_unique');
            $table->index(['business_id', 'status', 'created_at'], 'moi_business_status_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_order_imports');
    }
};
