<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('inventory_demand_forecasts')) {
            return;
        }

        Schema::create('inventory_demand_forecasts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->date('forecast_date');
            $table->date('forecast_start_date');
            $table->date('forecast_end_date');
            $table->unsignedSmallInteger('lookback_days');
            $table->unsignedSmallInteger('lead_time_days')->default(7);
            $table->unsignedSmallInteger('safety_stock_days')->default(7);
            $table->decimal('average_daily_demand', 12, 4)->default(0);
            $table->unsignedInteger('predicted_demand')->default(0);
            $table->integer('stock_position')->default(0);
            $table->unsignedInteger('suggested_reorder_qty')->default(0);
            $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['product_id', 'forecast_date']);
            $table->index(
                ['forecast_start_date', 'forecast_end_date'],
                'idf_forecast_dates_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_demand_forecasts');
    }
};
