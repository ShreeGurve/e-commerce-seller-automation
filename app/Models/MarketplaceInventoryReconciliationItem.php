<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MarketplaceInventoryReconciliationItem extends Model
{
    protected $fillable = [
        'reconciliation_run_id', 'inventory_snapshot_id', 'product_id',
        'inventory_correction_request_id', 'marketplace_sku', 'fulfillment_channel',
        'marketplace_qty', 'internal_qty', 'variance', 'status', 'correction_eligible', 'snapshot_at',
    ];

    protected function casts(): array
    {
        return ['correction_eligible' => 'boolean', 'snapshot_at' => 'datetime'];
    }

    public function run() { return $this->belongsTo(MarketplaceInventoryReconciliationRun::class, 'reconciliation_run_id'); }
    public function snapshot() { return $this->belongsTo(MarketplaceInventorySnapshot::class, 'inventory_snapshot_id'); }
    public function product() { return $this->belongsTo(Product::class); }
    public function correctionRequest() { return $this->belongsTo(InventoryCorrectionRequest::class, 'inventory_correction_request_id'); }
}
