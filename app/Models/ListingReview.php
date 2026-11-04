<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ListingReview extends Model
{
    protected $fillable = [
        'listing_id', 'listing_version_id', 'status',
        'requested_by', 'reviewed_by', 'review_note',
        'requested_at', 'reviewed_at',
    ];

    protected $casts = [
        'requested_at' => 'datetime',
        'reviewed_at' => 'datetime',
    ];

    public const STATUSES = [
        'PENDING' => 'Pending review',
        'APPROVED' => 'Approved',
        'CHANGES_REQUESTED' => 'Changes requested',
    ];

    public function listing() { return $this->belongsTo(Listing::class); }
    public function version() { return $this->belongsTo(ListingVersion::class, 'listing_version_id'); }
    public function requester() { return $this->belongsTo(User::class, 'requested_by'); }
    public function reviewer() { return $this->belongsTo(User::class, 'reviewed_by'); }
}
