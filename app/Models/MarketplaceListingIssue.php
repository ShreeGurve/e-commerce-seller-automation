<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MarketplaceListingIssue extends Model
{
    protected $fillable = ['listing_id', 'listing_version_id', 'external_issue_id', 'issue_code', 'issue_type', 'severity', 'title', 'description', 'source', 'status', 'is_suppression', 'detected_at', 'due_at', 'resolved_at', 'evidence', 'resolution_notes', 'created_by', 'updated_by'];

    protected $casts = ['is_suppression' => 'boolean', 'detected_at' => 'datetime', 'due_at' => 'datetime', 'resolved_at' => 'datetime', 'evidence' => 'array'];

    public function listing()
    {
        return $this->belongsTo(Listing::class);
    }

    public function version()
    {
        return $this->belongsTo(ListingVersion::class, 'listing_version_id');
    }

    public function events()
    {
        return $this->hasMany(MarketplaceListingIssueEvent::class, 'issue_id')->orderByDesc('id');
    }
}
