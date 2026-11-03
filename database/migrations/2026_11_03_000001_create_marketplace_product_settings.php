<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('marketplace_product_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('marketplace_id')->constrained()->cascadeOnDelete();
            $table->decimal('outbound_shipping_cost', 12, 2)->default(0);
            $table->decimal('handling_cost', 12, 2)->default(0);
            $table->decimal('desired_profit_min', 12, 2)->default(0);
            $table->decimal('desired_profit_max', 12, 2)->default(0);
            $table->decimal('manual_selling_price', 12, 2)->nullable();
            $table->boolean('auto_price_enabled')->default(false);
            $table->boolean('push_enabled')->default(false);
            $table->timestamps();
            $table->unique(['product_id', 'marketplace_id'], 'mps_product_marketplace_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_product_settings');
    }
};
