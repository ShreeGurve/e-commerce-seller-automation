<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventoryDemandForecast extends Model
{
    protected $fillable = [
        'product_id', 'forecast_date', 'forecast_start_date', 'forecast_end_date',
        'lookback_days', 'lead_time_days', 'safety_stock_days', 'average_daily_demand',
        'predicted_demand', 'stock_position', 'suggested_reorder_qty', 'generated_by',
    ];

    protected $casts = [
        'forecast_date' => 'date',
        'forecast_start_date' => 'date',
        'forecast_end_date' => 'date',
        'average_daily_demand' => 'decimal:4',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    public function generator()
    {
        return $this->belongsTo(User::class, 'generated_by');
    }
}
