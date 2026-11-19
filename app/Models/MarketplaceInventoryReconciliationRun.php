<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MarketplaceInventoryReconciliationRun extends Model
{
    protected $fillable = [
        'business_id', 'marketplace_connection_id', 'marketplace_id', 'status',
        'items_checked', 'matched_count', 'mismatch_count', 'unmapped_count',
        'managed_channel_count', 'snapshot_cutoff_at', 'completed_at',
    ];

    protected function casts(): array
    {
        return ['snapshot_cutoff_at' => 'datetime', 'completed_at' => 'datetime'];
    }

    public function connection() { return $this->belongsTo(MarketplaceConnection::class, 'marketplace_connection_id'); }
    public function items() { return $this->hasMany(MarketplaceInventoryReconciliationItem::class, 'reconciliation_run_id'); }
}
