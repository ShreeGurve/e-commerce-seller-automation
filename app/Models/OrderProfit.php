<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class OrderProfit extends Model
{
    protected $fillable = ['order_item_id', 'order_id', 'product_id', 'marketplace_id', 'order_date', 'qty', 'revenue', 'cogs', 'fees', 'return_loss',
        'recovery', 'ads_cost', 'estimated_profit', 'actual_profit', 'profit_status', 'calculated_at'];
    protected $casts = ['order_date' => 'date', 'calculated_at' => 'datetime', 'revenue' => 'decimal:2', 'cogs' => 'decimal:2', 'fees' => 'decimal:2',
        'return_loss' => 'decimal:2', 'recovery' => 'decimal:2', 'ads_cost' => 'decimal:2', 'estimated_profit' => 'decimal:2', 'actual_profit' => 'decimal:2'];
    public function order() { return $this->belongsTo(Order::class); }
    public function product() { return $this->belongsTo(Product::class)->withTrashed(); }
    public function profit(): string { return (string) ($this->actual_profit ?? $this->estimated_profit); }
}
