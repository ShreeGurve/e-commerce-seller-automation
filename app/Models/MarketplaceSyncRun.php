<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MarketplaceSyncRun extends Model
{
    protected $fillable = [
        'business_id', 'marketplace_connection_id', 'marketplace_id', 'resource', 'status',
        'requested_from', 'requested_to', 'pages_processed', 'records_fetched',
        'records_created', 'records_updated', 'records_skipped', 'started_at', 'finished_at', 'error_message',
    ];

    protected function casts(): array
    {
        return [
            'requested_from' => 'date',
            'requested_to' => 'date',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function connection() { return $this->belongsTo(MarketplaceConnection::class, 'marketplace_connection_id'); }
    public function records() { return $this->hasMany(MarketplaceSyncRecord::class, 'sync_run_id'); }
}
