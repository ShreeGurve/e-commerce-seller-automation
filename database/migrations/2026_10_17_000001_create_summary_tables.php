<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Pre-added daily numbers so the dashboard stays fast with hundreds of thousands of orders.
        // They are DERIVED data: rebuilt from orders / profit lines / ad reports at any time (php artisan summary:rebuild).
        Schema::create('daily_sales', function (Blueprint $t) {
            $t->id();
            $t->date('date');
            $t->foreignId('marketplace_id')->constrained()->cascadeOnDelete();
            $t->unsignedInteger('orders')->default(0);            // not cancelled
            $t->unsignedInteger('units')->default(0);
            $t->decimal('gross_sales', 15, 2)->default(0);        // order totals incl. GST, not cancelled
            $t->unsignedInteger('cancelled_orders')->default(0);
            $t->timestamp('updated_at')->nullable();
            $t->unique(['date', 'marketplace_id']);
        });
        Schema::create('daily_profit', function (Blueprint $t) {
            $t->id();
            $t->date('date');
            $t->foreignId('marketplace_id')->constrained()->cascadeOnDelete();
            $t->unsignedInteger('lines')->default(0);
            $t->unsignedInteger('units')->default(0);
            $t->decimal('revenue', 15, 2)->default(0);
            $t->decimal('cogs', 15, 2)->default(0);
            $t->decimal('fees', 15, 2)->default(0);
            $t->decimal('refunds', 15, 2)->default(0);
            $t->decimal('recovery', 15, 2)->default(0);
            $t->decimal('ads', 15, 2)->default(0);                // ad cost already placed on order lines
            $t->decimal('profit', 15, 2)->default(0);             // actual where settled, else estimated
            $t->unsignedInteger('actual_lines')->default(0);
            $t->timestamp('updated_at')->nullable();
            $t->unique(['date', 'marketplace_id']);
        });
        Schema::create('daily_advertising', function (Blueprint $t) {
            $t->id();
            $t->date('date');
            $t->foreignId('marketplace_id')->constrained()->cascadeOnDelete();
            $t->string('level', 8);                               // the report level used for totals that day
            $t->unsignedBigInteger('impressions')->default(0);
            $t->unsignedBigInteger('clicks')->default(0);
            $t->decimal('spend', 15, 2)->default(0);
            $t->decimal('sales', 15, 2)->default(0);
            $t->unsignedInteger('orders')->default(0);
            $t->unsignedInteger('units')->default(0);
            $t->timestamp('updated_at')->nullable();
            $t->unique(['date', 'marketplace_id']);
        });
        Schema::create('daily_inventory', function (Blueprint $t) {   // end-of-day stock snapshot per product (php artisan summary:snapshot)
            $t->id();
            $t->date('date');
            $t->foreignId('product_id')->constrained()->cascadeOnDelete();
            $t->integer('available_qty')->default(0);
            $t->integer('damaged_qty')->default(0);
            $t->unique(['date', 'product_id']);
        });
    }

    public function down(): void
    {
        foreach (['daily_inventory', 'daily_advertising', 'daily_profit', 'daily_sales'] as $t) Schema::dropIfExists($t);
    }
};
