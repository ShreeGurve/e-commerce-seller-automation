<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class MarketplaceWriteVerificationService
{
    public const STATUSES = ['PENDING', 'LIVE_CONFIRMED', 'NOT_VISIBLE', 'ACTION_REQUIRED', 'REJECTED'];

    public function dashboard(int $businessId): array
    {
        $attempts = DB::table('marketplace_write_attempts as a')
            ->leftJoin('marketplace_write_verifications as v', 'v.attempt_id', '=', 'a.id')
            ->leftJoin('users as preparer', 'preparer.id', '=', 'a.prepared_by')
            ->leftJoin('users as approver', 'approver.id', '=', 'a.approved_by')
            ->leftJoin('users as verifier', 'verifier.id', '=', 'v.verified_by')
            ->where('a.business_id', $businessId)
            ->whereIn('a.status', ['ACCEPTED', 'REVIEW_REQUIRED'])
            ->select([
                'a.id', 'a.marketplace_code', 'a.operation', 'a.marketplace_sku', 'a.status as submission_status',
                'a.payload_hash', 'a.submitted_at', 'a.prepared_by', 'a.approved_by',
                'preparer.name as prepared_by_name', 'approver.name as approved_by_name',
                'v.id as verification_id', 'v.status as verification_status', 'v.issue_code',
                'v.external_submission_id', 'v.verification_note', 'v.evidence_url', 'v.checked_at',
                'verifier.name as verified_by_name',
            ])
            ->orderByDesc('a.submitted_at')->limit(150)->get();

        $counts = [
            'awaiting' => $attempts->whereNull('verification_id')->count(),
            'confirmed' => $attempts->where('verification_status', 'LIVE_CONFIRMED')->count(),
            'attention' => $attempts->whereIn('verification_status', ['ACTION_REQUIRED', 'REJECTED', 'NOT_VISIBLE'])->count(),
        ];

        return compact('attempts', 'counts');
    }

    /** Record a seller-portal verification. This method never calls a marketplace API. */
    public function record(int $attemptId, User $user, array $data): object
    {
        $status = strtoupper((string) ($data['status'] ?? ''));
        if (! in_array($status, self::STATUSES, true)) {
            throw ValidationException::withMessages(['status' => 'Select a supported verification outcome.']);
        }

        $note = trim((string) ($data['verification_note'] ?? ''));
        if (mb_strlen($note) < 10 || mb_strlen($note) > 2000) {
            throw ValidationException::withMessages(['verification_note' => 'Add a verification note between 10 and 2,000 characters.']);
        }
        $evidenceUrl = trim((string) ($data['evidence_url'] ?? '')) ?: null;
        if ($evidenceUrl !== null && (! filter_var($evidenceUrl, FILTER_VALIDATE_URL) || ! str_starts_with(strtolower($evidenceUrl), 'https://'))) {
            throw ValidationException::withMessages(['evidence_url' => 'Evidence links must use HTTPS. Do not paste access tokens or private credentials.']);
        }

        return DB::transaction(function () use ($attemptId, $user, $data, $status, $note, $evidenceUrl) {
            $attempt = DB::table('marketplace_write_attempts')->where('id', $attemptId)->lockForUpdate()->first();
            if (! $attempt || (int) $attempt->business_id !== (int) $user->business_id) {
                throw ValidationException::withMessages(['attempt' => 'Marketplace write attempt was not found for this business.']);
            }
            if (! in_array($attempt->status, ['ACCEPTED', 'REVIEW_REQUIRED'], true)) {
                throw ValidationException::withMessages(['attempt' => 'Only accepted or uncertain submissions can be verified.']);
            }
            if ((int) $attempt->approved_by === (int) $user->id) {
                throw ValidationException::withMessages(['verifier' => 'The user who submitted the write cannot certify its marketplace outcome.']);
            }

            $existing = DB::table('marketplace_write_verifications')->where('attempt_id', $attempt->id)->lockForUpdate()->first();
            if ($existing && $existing->status === 'LIVE_CONFIRMED') {
                throw ValidationException::withMessages(['status' => 'A live-confirmed submission is final. Create a new reviewed write attempt for any later change.']);
            }

            $values = [
                'business_id' => $attempt->business_id,
                'marketplace_connection_id' => $attempt->marketplace_connection_id,
                'status' => $status,
                'issue_code' => trim((string) ($data['issue_code'] ?? '')) ?: null,
                'external_submission_id' => trim((string) ($data['external_submission_id'] ?? '')) ?: null,
                'verification_note' => $note,
                'evidence_url' => $evidenceUrl,
                'verified_by' => $user->id,
                'checked_at' => now(),
                'updated_at' => now(),
            ];

            if ($existing) {
                DB::table('marketplace_write_verifications')->where('id', $existing->id)->update($values);
                $verificationId = $existing->id;
                $fromStatus = $existing->status;
            } else {
                $verificationId = DB::table('marketplace_write_verifications')->insertGetId($values + [
                    'attempt_id' => $attempt->id,
                    'created_at' => now(),
                ]);
                $fromStatus = null;
            }

            DB::table('marketplace_write_verification_events')->insert([
                'verification_id' => $verificationId,
                'attempt_id' => $attempt->id,
                'from_status' => $fromStatus,
                'to_status' => $status,
                'issue_code' => $values['issue_code'],
                'verification_note' => $note,
                'evidence_url' => $evidenceUrl,
                'recorded_by' => $user->id,
                'recorded_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return DB::table('marketplace_write_verifications')->where('id', $verificationId)->first();
        });
    }

    public function history(int $attemptId, User $user)
    {
        $attempt = DB::table('marketplace_write_attempts')->where('id', $attemptId)
            ->where('business_id', $user->business_id)->first();
        if (! $attempt) {
            throw new RuntimeException('Marketplace write attempt was not found.');
        }

        return DB::table('marketplace_write_verification_events as e')
            ->leftJoin('users as u', 'u.id', '=', 'e.recorded_by')
            ->where('e.attempt_id', $attemptId)
            ->orderByDesc('e.recorded_at')
            ->select('e.*', 'u.name as recorded_by_name')->get();
    }
}
