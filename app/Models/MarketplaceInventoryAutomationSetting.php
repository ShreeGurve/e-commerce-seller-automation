<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MarketplaceInventoryAutomationSetting extends Model
{
    protected $fillable = ['business_id', 'marketplace_connection_id', 'enabled', 'interval_hours', 'low_stock_threshold', 'last_scheduled_at', 'next_run_at'];

    protected function casts(): array
    {
        return ['enabled' => 'boolean', 'last_scheduled_at' => 'datetime', 'next_run_at' => 'datetime'];
    }

    public function connection() { return $this->belongsTo(MarketplaceConnection::class, 'marketplace_connection_id'); }
}
