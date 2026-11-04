<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('inventory_stock_counts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->integer('system_qty');
            $table->integer('counted_qty');
            $table->integer('variance');
            $table->string('note', 255);
            $table->foreignId('counted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('counted_at');
            $table->timestamps();
            $table->index(['product_id', 'counted_at']);
        });
    }
    public function down(): void { Schema::dropIfExists('inventory_stock_counts'); }
};
