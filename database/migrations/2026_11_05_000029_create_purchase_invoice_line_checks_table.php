<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_invoice_line_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_invoice_match_id')->constrained('purchase_invoice_matches')->cascadeOnDelete();
            $table->foreignId('purchase_item_id')->constrained('purchase_items')->cascadeOnDelete();
            $table->unsignedInteger('invoice_quantity');
            $table->decimal('invoice_unit_cost', 14, 2);
            $table->decimal('invoice_line_total', 14, 2);
            $table->integer('quantity_variance')->default(0);
            $table->decimal('cost_variance', 14, 2)->default(0);
            $table->string('line_status', 24)->default('MATCHED');
            $table->text('review_note')->nullable();
            $table->timestamps();
            $table->unique(['purchase_invoice_match_id', 'purchase_item_id'], 'pilc_match_item_unique');
            $table->index(['line_status', 'purchase_invoice_match_id'], 'pilc_status_match_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_invoice_line_checks');
    }
};
