<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_quality_inspections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_id')->constrained('purchases')->cascadeOnDelete();
            $table->foreignId('purchase_item_id')->constrained('purchase_items')->cascadeOnDelete();
            $table->date('inspection_date');
            $table->unsignedInteger('inspected_qty');
            $table->unsignedInteger('accepted_qty')->default(0);
            $table->unsignedInteger('rejected_qty')->default(0);
            $table->string('defect_type', 100)->nullable();
            $table->string('supplier_action', 40)->default('PENDING');
            $table->string('status', 30)->default('OPEN');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['status', 'inspection_date'], 'pqi_status_date_idx');
            $table->index(['purchase_id', 'purchase_item_id'], 'pqi_purchase_item_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_quality_inspections');
    }
};
