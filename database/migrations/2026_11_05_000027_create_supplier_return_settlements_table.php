<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_return_settlements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_return_id')->constrained('supplier_returns')->cascadeOnDelete();
            $table->string('settlement_type', 20); // REFUND, CREDIT, REPLACEMENT
            $table->decimal('amount', 14, 2)->default(0);
            $table->unsignedInteger('replacement_quantity')->default(0);
            $table->string('reference_number', 120)->nullable();
            $table->date('settlement_date')->nullable();
            $table->string('status', 20)->default('PENDING'); // PENDING, CONFIRMED, CANCELLED
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('confirmed_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'settlement_type'], 'srs_status_type_idx');
            $table->index(['supplier_return_id', 'settlement_date'], 'srs_return_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_return_settlements');
    }
};
