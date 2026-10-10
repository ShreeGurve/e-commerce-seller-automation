<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApprovalRequest extends Model
{
    protected $fillable = [
        'business_id', 'title', 'type', 'request_type', 'reference', 'amount',
        'description', 'entity_type', 'entity_id', 'current_value', 'proposed_value',
        'source', 'status', 'requested_by', 'reviewed_by', 'reviewed_at',
        'review_note', 'decision_note', 'executed_at',
    ];

    protected $casts = [
        'current_value' => 'array',
        'proposed_value' => 'array',
        'reviewed_at' => 'datetime',
        'executed_at' => 'datetime',
        'amount' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::saving(function (ApprovalRequest $approval): void {
            // Keep the old field used by the existing UI and the canonical field aligned.
            if ($approval->isDirty('request_type') && !$approval->isDirty('type')) {
                $approval->type = $approval->request_type;
            } elseif ($approval->isDirty('type') && !$approval->isDirty('request_type')) {
                $approval->request_type = $approval->type ?: 'other';
            } elseif (!$approval->type) {
                $approval->type = $approval->request_type ?: 'other';
            } elseif (!$approval->request_type) {
                $approval->request_type = $approval->type;
            }

            if ($approval->isDirty('decision_note') && !$approval->isDirty('review_note')) {
                $approval->review_note = $approval->decision_note;
            } elseif ($approval->isDirty('review_note') && !$approval->isDirty('decision_note')) {
                $approval->decision_note = $approval->review_note;
            }
        });
    }

    public function entity()
    {
        return $this->morphTo();
    }

    public function requester()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
