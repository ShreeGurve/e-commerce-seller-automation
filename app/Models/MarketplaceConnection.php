<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MarketplaceConnection extends Model
{
    protected $fillable = [
        'business_id', 'marketplace_id', 'environment', 'account_label', 'credentials',
        'status', 'verified_at', 'last_verified_message', 'last_verified_at',
    ];

    protected function casts(): array
    {
        return [
            'credentials' => 'encrypted:array',
            'verified_at' => 'datetime',
            'last_verified_at' => 'datetime',
        ];
    }

    public function marketplace()
    {
        return $this->belongsTo(Marketplace::class);
    }

    public function business()
    {
        return $this->belongsTo(Business::class);
    }
}
