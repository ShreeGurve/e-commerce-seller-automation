<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MarketplaceInventorySnapshot extends Model
{
    protected $fillable = [
        'business_id', 'marketplace_connection_id', 'marketplace_id', 'marketplace_sku',
        'fulfillment_channel', 'available_qty', 'reserved_qty', 'inbound_qty', 'snapshot_at', 'raw_payload',
    ];

    protected function casts(): array
    {
        return ['snapshot_at' => 'datetime', 'raw_payload' => 'array'];
    }
}
