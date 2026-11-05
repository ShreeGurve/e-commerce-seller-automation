<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        if (!Schema::hasTable('procurement_supplier_quotes')) Schema::create('procurement_supplier_quotes', function(Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('requisition_id'); $t->unsignedBigInteger('supplier_id');
            $t->string('quote_reference',100)->nullable(); $t->date('quote_date'); $t->date('valid_until')->nullable();
            $t->decimal('shipping_cost',14,2)->default(0); $t->decimal('tax_amount',14,2)->default(0);
            $t->decimal('other_cost',14,2)->default(0); $t->decimal('quoted_total',14,2)->default(0);
            $t->unsignedInteger('lead_time_days')->nullable(); $t->string('status',20)->default('RECEIVED');
            $t->text('terms')->nullable(); $t->text('notes')->nullable();
            $t->unsignedBigInteger('recorded_by')->nullable(); $t->timestamps();
            $t->foreign('requisition_id','psq_req_fk')->references('id')->on('procurement_requisitions')->cascadeOnDelete();
            $t->foreign('supplier_id','psq_supplier_fk')->references('id')->on('suppliers')->restrictOnDelete();
            $t->foreign('recorded_by','psq_user_fk')->references('id')->on('users')->nullOnDelete();
            $t->index(['requisition_id','status'],'psq_req_status_idx');
        });
        if (!Schema::hasTable('procurement_supplier_quote_items')) Schema::create('procurement_supplier_quote_items', function(Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('quote_id'); $t->unsignedBigInteger('requisition_item_id');
            $t->decimal('unit_cost',14,2); $t->decimal('quantity',14,3); $t->decimal('line_total',14,2);
            $t->string('availability',24)->default('AVAILABLE'); $t->text('notes')->nullable(); $t->timestamps();
            $t->foreign('quote_id','psqi_quote_fk')->references('id')->on('procurement_supplier_quotes')->cascadeOnDelete();
            $t->foreign('requisition_item_id','psqi_req_item_fk')->references('id')->on('procurement_requisition_items')->cascadeOnDelete();
            $t->unique(['quote_id','requisition_item_id'],'psqi_quote_item_uq');
        });
    }
    public function down(): void { Schema::dropIfExists('procurement_supplier_quote_items'); Schema::dropIfExists('procurement_supplier_quotes'); }
};
