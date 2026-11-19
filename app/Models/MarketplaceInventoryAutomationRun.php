<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MarketplaceInventoryAutomationRun extends Model
{
    protected $fillable = ['business_id', 'marketplace_connection_id', 'sync_run_id', 'reconciliation_run_id', 'status', 'message', 'started_at', 'finished_at'];

    protected function casts(): array { return ['started_at' => 'datetime', 'finished_at' => 'datetime']; }

    public function connection() { return $this->belongsTo(MarketplaceConnection::class, 'marketplace_connection_id'); }
}
