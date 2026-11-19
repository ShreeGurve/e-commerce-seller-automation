<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ListingAttributeMapping extends Model
{
    protected $fillable = ['listing_id', 'listing_version_id', 'definition_id', 'source_field', 'mapped_value', 'approval_status', 'mapped_by', 'reviewed_by', 'reviewed_at'];
    protected $casts = ['mapped_value' => 'array', 'reviewed_at' => 'datetime'];
    public function listing() { return $this->belongsTo(Listing::class); }
    public function definition() { return $this->belongsTo(MarketplaceAttributeDefinition::class, 'definition_id'); }
}
