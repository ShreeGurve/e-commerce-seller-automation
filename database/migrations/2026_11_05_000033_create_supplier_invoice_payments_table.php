<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('supplier_invoice_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_invoice_match_id')->constrained('purchase_invoice_matches')->cascadeOnDelete();
            $table->decimal('amount', 14, 2);
            $table->date('payment_date');
            $table->string('payment_method', 30)->default('BANK_TRANSFER');
            $table->string('reference_number', 120)->nullable();
            $table->string('status', 20)->default('CONFIRMED');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['payment_date','status'], 'sip_date_status_idx');
        });
    }
    public function down(): void { Schema::dropIfExists('supplier_invoice_payments'); }
};
