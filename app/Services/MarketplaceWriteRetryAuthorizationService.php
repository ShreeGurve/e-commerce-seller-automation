<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MarketplaceWriteRetryAuthorizationService
{
    public function dashboard(int $businessId): array
    {
        $requests = DB::table('marketplace_write_retry_authorizations as r')
            ->join('marketplace_write_incidents as i', 'i.id', '=', 'r.incident_id')
            ->join('marketplace_write_attempts as a', 'a.id', '=', 'r.attempt_id')
            ->leftJoin('users as requester', 'requester.id', '=', 'r.requested_by')
            ->leftJoin('users as reviewer', 'reviewer.id', '=', 'r.reviewed_by')
            ->where('r.business_id', $businessId)
            ->select('r.*', 'i.title as incident_title', 'a.marketplace_code', 'a.operation', 'a.marketplace_sku', 'a.status as attempt_status', 'requester.name as requester_name', 'reviewer.name as reviewer_name')
            ->orderByDesc('r.id')->paginate(25);

        $eligibleIncidents = DB::table('marketplace_write_incidents as i')
            ->join('marketplace_write_attempts as a', 'a.id', '=', 'i.attempt_id')
            ->where('i.business_id', $businessId)
            ->whereIn('i.status', ['OPEN', 'IN_PROGRESS', 'INVESTIGATING'])
            ->where('a.status', 'FAILED')
            ->whereNotExists(function ($q) {
                $q->selectRaw('1')->from('marketplace_write_retry_authorizations as r')
                    ->whereColumn('r.incident_id', 'i.id')
                    ->whereIn('r.status', ['PENDING', 'APPROVED']);
            })
            ->select('i.id', 'i.title', 'a.id as attempt_id', 'a.marketplace_code', 'a.operation', 'a.marketplace_sku')
            ->orderByDesc('i.id')->limit(100)->get();

        return compact('requests', 'eligibleIncidents');
    }

    public function request(int $businessId, int $userId, array $data): int
    {
        return DB::transaction(function () use ($businessId, $userId, $data) {
            $incident = DB::table('marketplace_write_incidents')->where('business_id', $businessId)
                ->where('id', (int) $data['incident_id'])->lockForUpdate()->first();
            if (! $incident) {
                throw ValidationException::withMessages(['incident_id' => 'Select an incident belonging to your business.']);
            }
            $attempt = DB::table('marketplace_write_attempts')->where('business_id', $businessId)
                ->where('id', $incident->attempt_id)->lockForUpdate()->first();
            if (! $attempt || $attempt->status !== 'FAILED') {
                throw ValidationException::withMessages(['incident_id' => 'Only definitively FAILED attempts can be considered. SUBMITTING and REVIEW_REQUIRED attempts are not retry eligible.']);
            }
            if (! in_array($incident->status, ['OPEN', 'IN_PROGRESS', 'INVESTIGATING'], true)) {
                throw ValidationException::withMessages(['incident_id' => 'The incident is not active.']);
            }
            if (DB::table('marketplace_write_retry_authorizations')->where('incident_id', $incident->id)
                ->whereIn('status', ['PENDING', 'APPROVED'])->exists()) {
                throw ValidationException::withMessages(['incident_id' => 'An active retry authorization already exists for this incident.']);
            }
            if (trim((string) $data['verification_outcome']) !== 'NOT_APPLIED_CONFIRMED') {
                throw ValidationException::withMessages(['verification_outcome' => 'A retry can only be requested after confirming that the marketplace did not apply the original change.']);
            }

            return DB::table('marketplace_write_retry_authorizations')->insertGetId([
                'business_id' => $businessId,
                'incident_id' => $incident->id,
                'attempt_id' => $attempt->id,
                'requested_by' => $userId,
                'status' => 'PENDING',
                'verification_outcome' => 'NOT_APPLIED_CONFIRMED',
                'evidence_reference' => trim((string) $data['evidence_reference']),
                'request_reason' => trim((string) $data['request_reason']),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }

    public function review(int $businessId, int $requestId, int $reviewerId, string $decision, string $note): void
    {
        DB::transaction(function () use ($businessId, $requestId, $reviewerId, $decision, $note) {
            $request = DB::table('marketplace_write_retry_authorizations')->where('business_id', $businessId)
                ->where('id', $requestId)->lockForUpdate()->first();
            if (! $request || $request->status !== 'PENDING') {
                throw ValidationException::withMessages(['request' => 'Pending retry authorization not found.']);
            }
            if ((int) $request->requested_by === $reviewerId) {
                throw ValidationException::withMessages(['review' => 'The requester cannot approve their own retry authorization.']);
            }
            if (! in_array($decision, ['APPROVED', 'REJECTED'], true)) {
                throw ValidationException::withMessages(['decision' => 'Choose approve or reject.']);
            }
            if (trim($note) === '') {
                throw ValidationException::withMessages(['review_note' => 'A review note is required.']);
            }

            $attempt = DB::table('marketplace_write_attempts')->where('business_id', $businessId)
                ->where('id', $request->attempt_id)->lockForUpdate()->first();
            $incident = DB::table('marketplace_write_incidents')->where('business_id', $businessId)
                ->where('id', $request->incident_id)->lockForUpdate()->first();
            if (! $attempt || $attempt->status !== 'FAILED' || ! $incident || ! in_array($incident->status, ['OPEN', 'IN_PROGRESS', 'INVESTIGATING'], true)) {
                throw ValidationException::withMessages(['request' => 'The original attempt or incident changed. This authorization can no longer be approved.']);
            }

            DB::table('marketplace_write_retry_authorizations')->where('id', $requestId)->update([
                'status' => $decision,
                'reviewed_by' => $reviewerId,
                'review_note' => trim($note),
                'reviewed_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('marketplace_write_incident_events')->insert([
                'incident_id' => $incident->id,
                'business_id' => $businessId,
                'recorded_by' => $reviewerId,
                'event_type' => $decision === 'APPROVED' ? 'RETRY_AUTHORIZED' : 'RETRY_REJECTED',
                'from_status' => $incident->status,
                'to_status' => $incident->status,
                'note' => 'Retry authorization '.$decision.'. '.$note,
                'metadata' => json_encode(['retry_authorization_id' => $requestId, 'attempt_id' => $attempt->id]),
                'recorded_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }
}
