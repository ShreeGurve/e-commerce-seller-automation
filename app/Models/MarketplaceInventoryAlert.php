<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MarketplaceInventoryAlert extends Model
{
    protected $fillable = ['business_id', 'marketplace_connection_id', 'alert_key', 'type', 'severity', 'status', 'marketplace_sku', 'payload', 'first_seen_at', 'last_seen_at', 'resolved_at'];

    protected function casts(): array
    {
        return ['payload' => 'array', 'first_seen_at' => 'datetime', 'last_seen_at' => 'datetime', 'resolved_at' => 'datetime'];
    }

    public function connection()
    {
        return $this->belongsTo(MarketplaceConnection::class, 'marketplace_connection_id');
    }
}
