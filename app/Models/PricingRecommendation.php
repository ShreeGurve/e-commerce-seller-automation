<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PricingRecommendation extends Model
{
    protected $fillable = [
        'business_id', 'product_id', 'marketplace_id', 'current_price', 'proposed_price',
        'minimum_price', 'expected_profit', 'expected_margin', 'reason', 'status',
        'requested_by', 'reviewed_by', 'reviewed_at', 'review_note', 'applied_at',
    ];

    protected $casts = [
        'current_price' => 'decimal:2',
        'proposed_price' => 'decimal:2',
        'minimum_price' => 'decimal:2',
        'expected_profit' => 'decimal:2',
        'expected_margin' => 'decimal:2',
        'reviewed_at' => 'datetime',
        'applied_at' => 'datetime',
    ];

    public function product() { return $this->belongsTo(Product::class)->withTrashed(); }
    public function marketplace() { return $this->belongsTo(Marketplace::class); }
    public function requester() { return $this->belongsTo(User::class, 'requested_by'); }
    public function reviewer() { return $this->belongsTo(User::class, 'reviewed_by'); }
}
