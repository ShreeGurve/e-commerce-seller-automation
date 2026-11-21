<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MarketplaceWriteRecoveryClosureService
{
    public function request(object $user, int $caseId, array $data): int
    {
        $businessId = (int) $user->business_id;
        $summary = trim((string) ($data['resolution_summary'] ?? ''));
        if ($summary === '') {
            throw ValidationException::withMessages(['resolution_summary' => 'A resolution summary is required.']);
        }

        return DB::transaction(function () use ($user, $businessId, $caseId, $summary) {
            $case = DB::table('marketplace_write_recovery_cases')->where('id', $caseId)
                ->where('business_id', $businessId)->lockForUpdate()->first();
            if (! $case) {
                abort(404);
            }
            if ($case->status === 'CLOSED') {
                throw ValidationException::withMessages(['case' => 'This recovery case is already closed.']);
            }
            $pendingSteps = DB::table('marketplace_write_recovery_steps')
                ->where('recovery_case_id', $caseId)->where('status', '!=', 'DONE')->exists();
            if ($pendingSteps) {
                throw ValidationException::withMessages(['case' => 'Complete all recovery steps before requesting closure.']);
            }
            $evidenceExists = DB::table('marketplace_write_evidence')->where('business_id', $businessId)
                ->where('subject_type', 'RECOVERY')->where('subject_id', $caseId)->exists();
            if (! $evidenceExists) {
                throw ValidationException::withMessages(['evidence' => 'Attach at least one evidence record to this recovery case before requesting closure.']);
            }
            $pendingReview = DB::table('marketplace_write_recovery_closure_reviews')
                ->where('business_id', $businessId)->where('recovery_case_id', $caseId)->where('status', 'PENDING')->exists();
            if ($pendingReview) {
                throw ValidationException::withMessages(['case' => 'A closure review is already pending.']);
            }

            return (int) DB::table('marketplace_write_recovery_closure_reviews')->insertGetId([
                'business_id' => $businessId,
                'recovery_case_id' => $caseId,
                'requested_by' => $user->id,
                'status' => 'PENDING',
                'resolution_summary' => $summary,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }

    public function review(object $user, int $reviewId, array $data): void
    {
        $decision = strtoupper((string) ($data['decision'] ?? ''));
        if (! in_array($decision, ['APPROVED', 'REJECTED'], true)) {
            throw ValidationException::withMessages(['decision' => 'Choose approve or reject.']);
        }
        $note = trim((string) ($data['review_note'] ?? ''));
        if ($decision === 'REJECTED' && $note === '') {
            throw ValidationException::withMessages(['review_note' => 'A reason is required when rejecting closure.']);
        }

        DB::transaction(function () use ($user, $reviewId, $decision, $note) {
            $review = DB::table('marketplace_write_recovery_closure_reviews')
                ->where('id', $reviewId)->where('business_id', (int) $user->business_id)
                ->lockForUpdate()->first();
            if (! $review) {
                abort(404);
            }
            if ($review->status !== 'PENDING') {
                throw ValidationException::withMessages(['review' => 'This closure request has already been reviewed.']);
            }
            if ((int) $review->requested_by === (int) $user->id) {
                throw ValidationException::withMessages(['review' => 'The requester cannot review their own closure request.']);
            }
            $case = DB::table('marketplace_write_recovery_cases')->where('id', $review->recovery_case_id)
                ->where('business_id', (int) $user->business_id)->lockForUpdate()->first();
            if (! $case || $case->status === 'CLOSED') {
                throw ValidationException::withMessages(['case' => 'The recovery case is unavailable or already closed.']);
            }
            if (DB::table('marketplace_write_recovery_steps')->where('recovery_case_id', $case->id)->where('status', '!=', 'DONE')->exists()) {
                throw ValidationException::withMessages(['case' => 'The recovery steps are no longer complete.']);
            }
            if (! DB::table('marketplace_write_evidence')->where('business_id', (int) $user->business_id)
                ->where('subject_type', 'RECOVERY')->where('subject_id', $case->id)->exists()) {
                throw ValidationException::withMessages(['evidence' => 'Recovery evidence is missing.']);
            }

            DB::table('marketplace_write_recovery_closure_reviews')->where('id', $reviewId)->update([
                'status' => $decision,
                'reviewed_by' => $user->id,
                'review_note' => $note !== '' ? $note : null,
                'reviewed_at' => now(),
                'updated_at' => now(),
            ]);
            if ($decision === 'APPROVED') {
                DB::table('marketplace_write_recovery_cases')->where('id', $case->id)->update([
                    'status' => 'CLOSED',
                    'resolution_summary' => $review->resolution_summary,
                    'updated_at' => now(),
                ]);
            }
        });
    }
}
