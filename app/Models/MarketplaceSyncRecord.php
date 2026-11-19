<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MarketplaceSyncRecord extends Model
{
    protected $fillable = [
        'business_id', 'marketplace_connection_id', 'marketplace_id', 'sync_run_id',
        'resource', 'external_id', 'payload', 'fingerprint', 'first_seen_at', 'last_seen_at',
    ];

    protected function casts(): array
    {
        return ['payload' => 'array', 'first_seen_at' => 'datetime', 'last_seen_at' => 'datetime'];
    }

    public function run() { return $this->belongsTo(MarketplaceSyncRun::class, 'sync_run_id'); }
    public function connection() { return $this->belongsTo(MarketplaceConnection::class, 'marketplace_connection_id'); }
}
