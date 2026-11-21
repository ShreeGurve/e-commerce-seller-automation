<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ListingAttributeMappingAudit extends Model
{
    protected $fillable = ['listing_id', 'listing_version_id', 'status', 'total_attributes', 'valid_attributes', 'missing_required', 'invalid_attributes', 'results', 'created_by'];

    protected $casts = ['results' => 'array'];

    public function listing()
    {
        return $this->belongsTo(Listing::class);
    }
}
