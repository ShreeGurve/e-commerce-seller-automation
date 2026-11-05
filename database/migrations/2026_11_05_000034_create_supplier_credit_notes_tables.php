<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('supplier_credit_notes')) {
            Schema::create('supplier_credit_notes', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('supplier_id');
                $table->unsignedBigInteger('purchase_invoice_match_id')->nullable();
                $table->unsignedBigInteger('supplier_return_id')->nullable();
                $table->string('credit_note_number', 100);
                $table->date('credit_date');
                $table->decimal('credit_amount', 14, 2);
                $table->string('status', 20)->default('OPEN');
                $table->text('notes')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();

                $table->foreign('supplier_id', 'scn_supplier_fk')
                    ->references('id')->on('suppliers')->cascadeOnDelete();
                $table->foreign('purchase_invoice_match_id', 'scn_invoice_fk')
                    ->references('id')->on('purchase_invoice_matches')->nullOnDelete();
                $table->foreign('supplier_return_id', 'scn_return_fk')
                    ->references('id')->on('supplier_returns')->nullOnDelete();
                $table->foreign('created_by', 'scn_creator_fk')
                    ->references('id')->on('users')->nullOnDelete();
                $table->index(['supplier_id', 'credit_date'], 'scn_supplier_date_idx');
            });
        }

        if (!Schema::hasTable('supplier_credit_note_applications')) {
            Schema::create('supplier_credit_note_applications', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('supplier_credit_note_id');
                $table->unsignedBigInteger('purchase_invoice_match_id');
                $table->decimal('amount', 14, 2);
                $table->date('applied_date');
                $table->text('notes')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();

                $table->foreign('supplier_credit_note_id', 'scna_credit_fk')
                    ->references('id')->on('supplier_credit_notes')->cascadeOnDelete();
                $table->foreign('purchase_invoice_match_id', 'scna_invoice_fk')
                    ->references('id')->on('purchase_invoice_matches')->cascadeOnDelete();
                $table->foreign('created_by', 'scna_creator_fk')
                    ->references('id')->on('users')->nullOnDelete();
                $table->index(['purchase_invoice_match_id', 'applied_date'], 'scna_invoice_date_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_credit_note_applications');
        Schema::dropIfExists('supplier_credit_notes');
    }
};
