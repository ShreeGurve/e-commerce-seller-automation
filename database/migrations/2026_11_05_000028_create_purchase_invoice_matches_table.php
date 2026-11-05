<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_invoice_matches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_id')->unique()->constrained('purchases')->cascadeOnDelete();
            $table->string('supplier_invoice_number', 100);
            $table->date('supplier_invoice_date');
            $table->decimal('invoice_subtotal', 14, 2);
            $table->decimal('invoice_tax', 14, 2)->default(0);
            $table->decimal('invoice_shipping', 14, 2)->default(0);
            $table->decimal('invoice_other', 14, 2)->default(0);
            $table->decimal('invoice_total', 14, 2);
            $table->decimal('tolerance_amount', 12, 2)->default(0.01);
            $table->decimal('variance_amount', 14, 2)->default(0);
            $table->string('match_status', 24)->default('REVIEW_REQUIRED');
            $table->text('review_notes')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('reviewed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['match_status', 'supplier_invoice_date'], 'pim_status_date_idx');
        });
    }

    public function down(): void { Schema::dropIfExists('purchase_invoice_matches'); }
};
