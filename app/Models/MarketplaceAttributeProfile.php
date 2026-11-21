<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MarketplaceAttributeProfile extends Model
{
    protected $fillable = ['marketplace_id', 'category_key', 'category_label', 'schema_source', 'schema_version', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function marketplace()
    {
        return $this->belongsTo(Marketplace::class);
    }

    public function definitions()
    {
        return $this->hasMany(MarketplaceAttributeDefinition::class, 'profile_id')->orderBy('sort_order');
    }
}
