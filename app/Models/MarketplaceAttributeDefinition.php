<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MarketplaceAttributeDefinition extends Model
{
    protected $fillable = ['profile_id', 'attribute_key', 'label', 'data_type', 'is_required', 'is_variant_attribute', 'allowed_values', 'min_length', 'max_length', 'sort_order'];
    protected $casts = ['is_required' => 'boolean', 'is_variant_attribute' => 'boolean', 'allowed_values' => 'array'];
    public function profile() { return $this->belongsTo(MarketplaceAttributeProfile::class, 'profile_id'); }
    public function mappings() { return $this->hasMany(ListingAttributeMapping::class, 'definition_id'); }
}
