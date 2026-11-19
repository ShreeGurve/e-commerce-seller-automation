<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('marketplace_return_imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('marketplace_id')->constrained()->restrictOnDelete();
            $table->string('external_return_id', 80);
            $table->string('external_order_id', 60);
            $table->string('status', 16)->default('PENDING')->index(); // PENDING, IMPORTED, REJECTED, FAILED
            $table->json('payload');
            $table->json('validation_errors')->nullable();
            $table->foreignId('return_id')->nullable()->constrained('returns')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();
            $table->timestamps();
            $table->unique(['marketplace_id', 'external_return_id'], 'mkt_ret_imp_ext_uq');
        });

        Schema::create('marketplace_refund_imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('marketplace_id')->constrained()->restrictOnDelete();
            $table->string('external_refund_id', 80);
            $table->string('external_order_id', 60);
            $table->string('external_return_id', 80)->nullable();
            $table->decimal('amount', 15, 2);
            $table->char('currency', 3)->default('INR');
            $table->date('refund_date');
            $table->string('reason', 180)->nullable();
            $table->string('status', 16)->default('PENDING')->index(); // PENDING, RECONCILED, REJECTED, FAILED
            $table->foreignId('refund_id')->nullable()->constrained('refunds')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();
            $table->timestamps();
            $table->unique(['marketplace_id', 'external_refund_id'], 'mkt_ref_imp_ext_uq');
        });

        Schema::create('return_inspections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('return_id')->constrained('returns')->cascadeOnDelete();
            $table->foreignId('return_item_id')->constrained('return_items')->cascadeOnDelete();
            $table->unsignedInteger('received_qty');
            $table->string('condition', 10); // SELLABLE, USED, DAMAGED, SCRAP, MISSING
            $table->unsignedInteger('restock_qty')->default(0);
            $table->string('disposition', 20); // RESTOCK, QUARANTINE, SCRAP, MISSING, REVIEW
            $table->string('note', 500)->nullable();
            $table->foreignId('inspected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('marketplace_return_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('return_id')->constrained('returns')->cascadeOnDelete();
            $table->string('event_type', 24);
            $table->string('from_status', 16)->nullable();
            $table->string('to_status', 16)->nullable();
            $table->json('details')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['return_id', 'id'], 'mkt_ret_evt_lookup_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_return_events');
        Schema::dropIfExists('return_inspections');
        Schema::dropIfExists('marketplace_refund_imports');
        Schema::dropIfExists('marketplace_return_imports');
    }
};
