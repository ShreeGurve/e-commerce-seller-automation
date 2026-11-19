<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ListingOptimizationAudit extends Model
{
    protected $fillable = ['listing_id', 'listing_version_id', 'score', 'status', 'issue_count', 'blocker_count', 'warning_count', 'results', 'created_by'];

    protected $casts = ['results' => 'array', 'score' => 'decimal:2'];

    public function listing() { return $this->belongsTo(Listing::class); }
    public function version() { return $this->belongsTo(ListingVersion::class, 'listing_version_id'); }
    public function author() { return $this->belongsTo(User::class, 'created_by'); }
}
