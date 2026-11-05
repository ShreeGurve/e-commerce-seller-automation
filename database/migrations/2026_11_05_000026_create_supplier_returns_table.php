<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_returns', function (Blueprint $table) {
            $table->id();
            $table->string('return_number', 40)->unique();
            $table->foreignId('purchase_id')->constrained('purchases')->restrictOnDelete();
            $table->foreignId('purchase_item_id')->constrained('purchase_items')->restrictOnDelete();
            $table->foreignId('quality_inspection_id')->nullable()->constrained('purchase_quality_inspections')->nullOnDelete();
            $table->date('return_date');
            $table->unsignedInteger('quantity');
            $table->string('reason', 120);
            $table->string('resolution', 30)->default('REPLACEMENT');
            $table->string('status', 30)->default('DRAFT');
            $table->string('supplier_reference', 100)->nullable();
            $table->dateTime('dispatched_at')->nullable();
            $table->dateTime('resolved_at')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['status', 'return_date'], 'sr_status_date_idx');
            $table->index(['purchase_id', 'purchase_item_id'], 'sr_purchase_item_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_returns');
    }
};
