<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MarketplaceListingIssueEvent extends Model
{
    public $timestamps = false;

    protected $fillable = ['issue_id', 'event_type', 'from_status', 'to_status', 'note', 'evidence', 'user_id', 'created_at'];

    protected $casts = ['evidence' => 'array', 'created_at' => 'datetime'];

    public function issue()
    {
        return $this->belongsTo(MarketplaceListingIssue::class, 'issue_id');
    }
}
