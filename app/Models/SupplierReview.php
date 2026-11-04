<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupplierReview extends Model
{
    protected $fillable = ['supplier_id', 'delivery_rating', 'quality_rating', 'pricing_rating', 'notes', 'reviewed_by', 'reviewed_at'];
    protected $casts = ['delivery_rating' => 'integer', 'quality_rating' => 'integer', 'pricing_rating' => 'integer', 'reviewed_at' => 'datetime'];

    public function supplier() { return $this->belongsTo(Supplier::class); }
    public function reviewer() { return $this->belongsTo(User::class, 'reviewed_by'); }
    public function getOverallRatingAttribute(): float
    {
        return round(((int) $this->delivery_rating + (int) $this->quality_rating + (int) $this->pricing_rating) / 3, 1);
    }
}
