<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MarketplaceWriteRetentionService
{
    public function dashboard($user): array
    {
        $businessId = $this->businessId($user);
        $policy = DB::table('marketplace_write_retention_policies')
            ->where('business_id', $businessId)->first();

        $base = DB::table('marketplace_write_evidence')->where('business_id', $businessId);

        return [
            'policy' => $policy,
            'active' => (clone $base)->where('retention_status', 'ACTIVE')->count(),
            'expiring' => (clone $base)->where('retention_status', 'ACTIVE')
                ->whereNotNull('retention_expires_at')
                ->whereBetween('retention_expires_at', [now(), now()->addDays((int) ($policy->alert_before_days ?? 30))])->count(),
            'archived' => (clone $base)->where('retention_status', 'ARCHIVED')->count(),
            'held' => DB::table('marketplace_write_evidence_holds')
                ->where('business_id', $businessId)->where('status', 'ACTIVE')->distinct('evidence_id')->count('evidence_id'),
            'pending_deletion' => (clone $base)->where('retention_status', 'PENDING_DELETION')->count(),
            'evidence' => (clone $base)->orderByDesc('created_at')->paginate(25)->withQueryString(),
            'holds' => DB::table('marketplace_write_evidence_holds')
                ->where('business_id', $businessId)->where('status', 'ACTIVE')
                ->orderByDesc('placed_at')->limit(100)->get(),
            'events' => DB::table('marketplace_write_retention_events')
                ->where('business_id', $businessId)->orderByDesc('id')->limit(30)->get(),
        ];
    }

    public function savePolicy($user, array $data): void
    {
        $businessId = $this->businessId($user);
        DB::table('marketplace_write_retention_policies')->updateOrInsert(
            ['business_id' => $businessId],
            [
                'retention_days' => $data['retention_days'],
                'alert_before_days' => $data['alert_before_days'],
                'auto_archive' => (bool) ($data['auto_archive'] ?? false),
                'deletion_requests_enabled' => (bool) ($data['deletion_requests_enabled'] ?? false),
                'updated_by' => $user->id,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
        $this->event($businessId, null, $user->id, 'POLICY_UPDATED', 'Retention policy updated.');
    }

    public function placeHold($user, int $evidenceId, string $reason): int
    {
        $businessId = $this->businessId($user);
        $evidence = $this->evidence($businessId, $evidenceId);

        return DB::transaction(function () use ($businessId, $evidence, $user, $reason) {
            $existing = DB::table('marketplace_write_evidence_holds')
                ->where('business_id', $businessId)->where('evidence_id', $evidence->id)
                ->where('status', 'ACTIVE')->exists();

            if ($existing) {
                throw ValidationException::withMessages(['reason' => 'An active legal hold already exists for this evidence.']);
            }

            $id = DB::table('marketplace_write_evidence_holds')->insertGetId([
                'business_id' => $businessId, 'evidence_id' => $evidence->id,
                'reason' => $reason, 'status' => 'ACTIVE', 'placed_by' => $user->id,
                'placed_at' => now(), 'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('marketplace_write_evidence')->where('id', $evidence->id)->update([
                'retention_status' => 'LEGAL_HOLD', 'updated_at' => now(),
            ]);
            $this->event($businessId, $evidence->id, $user->id, 'LEGAL_HOLD_PLACED', "Hold #{$id}: {$reason}");

            return (int) $id;
        });
    }

    public function releaseHold($user, int $holdId, string $reason): void
    {
        $businessId = $this->businessId($user);

        DB::transaction(function () use ($businessId, $holdId, $user, $reason) {
            $hold = DB::table('marketplace_write_evidence_holds')
                ->where('business_id', $businessId)->where('id', $holdId)
                ->where('status', 'ACTIVE')->lockForUpdate()->first();

            if (! $hold) {
                abort(404);
            }

            DB::table('marketplace_write_evidence_holds')->where('id', $holdId)->update([
                'status' => 'RELEASED', 'released_by' => $user->id,
                'released_at' => now(), 'release_reason' => $reason, 'updated_at' => now(),
            ]);

            $stillHeld = DB::table('marketplace_write_evidence_holds')
                ->where('business_id', $businessId)->where('evidence_id', $hold->evidence_id)
                ->where('status', 'ACTIVE')->exists();

            if (! $stillHeld) {
                DB::table('marketplace_write_evidence')->where('business_id', $businessId)
                    ->where('id', $hold->evidence_id)->where('retention_status', 'LEGAL_HOLD')
                    ->update(['retention_status' => 'ACTIVE', 'updated_at' => now()]);
            }

            $this->event($businessId, $hold->evidence_id, $user->id, 'LEGAL_HOLD_RELEASED', "Hold #{$holdId}: {$reason}");
        });
    }

    public function requestDeletion($user, int $evidenceId, string $reason): void
    {
        $businessId = $this->businessId($user);
        $policy = DB::table('marketplace_write_retention_policies')->where('business_id', $businessId)->first();

        if (! $policy || ! $policy->deletion_requests_enabled) {
            throw ValidationException::withMessages(['deletion' => 'Deletion requests are disabled by the business retention policy.']);
        }

        DB::transaction(function () use ($businessId, $evidenceId, $user, $reason) {
            $evidence = $this->evidence($businessId, $evidenceId, true);
            $this->assertNotHeld($businessId, $evidenceId);

            if ($evidence->retention_status === 'PENDING_DELETION') {
                throw ValidationException::withMessages(['deletion' => 'A deletion request is already pending.']);
            }

            DB::table('marketplace_write_evidence')->where('id', $evidenceId)->update([
                'retention_status' => 'PENDING_DELETION',
                'deletion_requested_at' => now(), 'deletion_requested_by' => $user->id,
                'deletion_approved_at' => null, 'deletion_approved_by' => null, 'updated_at' => now(),
            ]);
            $this->event($businessId, $evidenceId, $user->id, 'DELETION_REQUESTED', $reason);
        });
    }

    public function approveDeletion($user, int $evidenceId, string $reason): void
    {
        $businessId = $this->businessId($user);

        DB::transaction(function () use ($businessId, $evidenceId, $user, $reason) {
            $evidence = $this->evidence($businessId, $evidenceId, true);
            $this->assertNotHeld($businessId, $evidenceId);

            if ($evidence->retention_status !== 'PENDING_DELETION') {
                throw ValidationException::withMessages(['deletion' => 'This evidence has no pending deletion request.']);
            }
            if ((int) $evidence->deletion_requested_by === (int) $user->id) {
                throw ValidationException::withMessages(['deletion' => 'A different authorized user must approve the deletion request.']);
            }

            // Governance approval only. Evidence is not physically deleted by this package.
            DB::table('marketplace_write_evidence')->where('id', $evidenceId)->update([
                'deletion_approved_at' => now(), 'deletion_approved_by' => $user->id,
                'retention_status' => 'DELETION_APPROVED', 'updated_at' => now(),
            ]);
            $this->event($businessId, $evidenceId, $user->id, 'DELETION_APPROVED', $reason);
        });
    }

    public function archiveExpired(): int
    {
        $count = 0;
        $policies = DB::table('marketplace_write_retention_policies')
            ->where('auto_archive', true)->get();

        foreach ($policies as $policy) {
            $rows = DB::table('marketplace_write_evidence as e')
                ->where('e.business_id', $policy->business_id)
                ->where('e.retention_status', 'ACTIVE')
                ->whereNotNull('e.retention_expires_at')
                ->where('e.retention_expires_at', '<=', now())
                ->whereNotExists(function ($query) {
                    $query->selectRaw('1')->from('marketplace_write_evidence_holds as h')
                        ->whereColumn('h.business_id', 'e.business_id')
                        ->whereColumn('h.evidence_id', 'e.id')->where('h.status', 'ACTIVE');
                })->limit(500)->get(['e.id']);

            foreach ($rows as $row) {
                DB::table('marketplace_write_evidence')->where('id', $row->id)->update([
                    'retention_status' => 'ARCHIVED', 'archived_at' => now(), 'updated_at' => now(),
                ]);
                $this->event((int) $policy->business_id, (int) $row->id, null, 'AUTO_ARCHIVED', 'Retention period expired; evidence archived, not deleted.');
                $count++;
            }
        }

        return $count;
    }

    private function assertNotHeld(int $businessId, int $evidenceId): void
    {
        if (DB::table('marketplace_write_evidence_holds')->where('business_id', $businessId)
            ->where('evidence_id', $evidenceId)->where('status', 'ACTIVE')->exists()) {
            throw ValidationException::withMessages(['deletion' => 'Evidence is protected by an active legal hold.']);
        }
    }

    private function evidence(int $businessId, int $id, bool $lock = false): object
    {
        $query = DB::table('marketplace_write_evidence')->where('business_id', $businessId)->where('id', $id);
        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->firstOrFail();
    }

    private function event(int $businessId, ?int $evidenceId, ?int $actorId, string $type, string $details): void
    {
        DB::table('marketplace_write_retention_events')->insert([
            'business_id' => $businessId, 'evidence_id' => $evidenceId,
            'actor_id' => $actorId, 'event_type' => $type, 'details' => $details,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function businessId($user): int
    {
        $id = $user->business_id ?? null;
        if (! $id) {
            abort(403, 'No business is associated with this account.');
        }

        return (int) $id;
    }
}
