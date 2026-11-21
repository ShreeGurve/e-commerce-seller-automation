<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MarketplaceOrderImport extends Model
{
    protected $fillable = [
        'business_id', 'marketplace_connection_id', 'marketplace_id', 'sync_record_id', 'order_id',
        'external_order_id', 'status', 'preview_payload', 'validation_errors', 'deduct_stock',
        'reviewed_by', 'reviewed_at', 'imported_at', 'failure_message',
    ];

    protected function casts(): array
    {
        return [
            'preview_payload' => 'array',
            'validation_errors' => 'array',
            'deduct_stock' => 'boolean',
            'reviewed_at' => 'datetime',
            'imported_at' => 'datetime',
        ];
    }

    public function connection()
    {
        return $this->belongsTo(MarketplaceConnection::class, 'marketplace_connection_id');
    }

    public function syncRecord()
    {
        return $this->belongsTo(MarketplaceSyncRecord::class, 'sync_record_id');
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
