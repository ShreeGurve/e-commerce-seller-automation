<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MarketplaceReturnImport extends Model
{
    protected $fillable = ['marketplace_id', 'external_return_id', 'external_order_id', 'status', 'payload', 'validation_errors', 'return_id', 'reviewed_by', 'reviewed_at', 'review_note'];
    protected $casts = ['payload' => 'array', 'validation_errors' => 'array', 'reviewed_at' => 'datetime'];
    public function marketplace() { return $this->belongsTo(Marketplace::class); }
    public function productReturn() { return $this->belongsTo(ProductReturn::class, 'return_id'); }
}
