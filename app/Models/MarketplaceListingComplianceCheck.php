<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MarketplaceListingComplianceCheck extends Model
{
    protected $fillable = ['listing_id', 'check_type', 'result', 'open_issues', 'suppression_issues', 'summary', 'checked_by'];

    protected $casts = ['summary' => 'array'];

    public function listing()
    {
        return $this->belongsTo(Listing::class);
    }
}
