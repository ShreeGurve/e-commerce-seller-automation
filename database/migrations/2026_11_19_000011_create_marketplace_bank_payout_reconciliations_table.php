<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('marketplace_bank_payouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('marketplace_id')->constrained()->restrictOnDelete();
            $table->foreignId('settlement_id')->nullable()->constrained('settlements')->nullOnDelete();
            $table->string('bank_reference', 100);
            $table->string('external_settlement_id', 60)->nullable();
            $table->date('payout_date')->nullable();
            $table->decimal('bank_amount', 15, 2);
            $table->decimal('settlement_amount', 15, 2)->nullable();
            $table->decimal('difference', 15, 2)->nullable();
            $table->string('currency', 3)->default('INR');
            $table->string('status', 12)->default('UNMATCHED')->index(); // UNMATCHED|MATCHED|MISMATCH|IGNORED
            $table->string('note', 500)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->unique(['marketplace_id', 'bank_reference'], 'mbp_market_ref_uq');
            $table->unique('settlement_id', 'mbp_settlement_uq');
            $table->index(['marketplace_id', 'payout_date'], 'mbp_market_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_bank_payouts');
    }
};
