<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MarketplaceWriteGovernanceService
{
    private const ALLOWED = [
        'writes_enabled',
        'inventory_auto_sync',
        'require_second_person',
        'require_evidence_reference',
    ];

    public function dashboard($user): array
    {
        $businessId = $this->businessId($user);
        $policy = DB::table('marketplace_write_governance_policies')->where('business_id', $businessId)->first();

        return [
            'policy' => $policy,
            'requests' => DB::table('marketplace_write_governance_requests')->where('business_id', $businessId)
                ->orderByRaw("CASE status WHEN 'PENDING' THEN 0 ELSE 1 END")
                ->orderByDesc('id')->paginate(20)->withQueryString(),
            'events' => DB::table('marketplace_write_governance_events')->where('business_id', $businessId)
                ->orderByDesc('id')->limit(30)->get(),
        ];
    }

    public function requestChanges($user, array $changes, string $reason): int
    {
        $businessId = $this->businessId($user);
        $clean = [];
        foreach (self::ALLOWED as $key) {
            if (array_key_exists($key, $changes) && $changes[$key] !== null && $changes[$key] !== '') {
                $clean[$key] = (bool) $changes[$key];
            }
        }

        if ($clean === []) {
            throw ValidationException::withMessages(['changes' => 'Select at least one governance setting to change.']);
        }

        return DB::transaction(function () use ($businessId, $user, $clean, $reason) {
            $pending = DB::table('marketplace_write_governance_requests')->where('business_id', $businessId)
                ->where('status', 'PENDING')->exists();
            if ($pending) {
                throw ValidationException::withMessages(['request' => 'Resolve the existing pending governance request before submitting another.']);
            }

            $id = DB::table('marketplace_write_governance_requests')->insertGetId([
                'business_id' => $businessId,
                'requested_by' => $user->id,
                'status' => 'PENDING',
                'proposed_changes' => json_encode($clean, JSON_THROW_ON_ERROR),
                'request_reason' => $reason,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->event($businessId, (int) $user->id, $id, 'CHANGE_REQUESTED', $reason);

            return (int) $id;
        });
    }

    public function review($user, int $requestId, string $decision, string $reason): void
    {
        $businessId = $this->businessId($user);
        $decision = strtoupper($decision);
        if (! in_array($decision, ['APPROVE', 'REJECT'], true)) {
            throw ValidationException::withMessages(['decision' => 'Choose approve or reject.']);
        }

        DB::transaction(function () use ($businessId, $user, $requestId, $decision, $reason) {
            $request = DB::table('marketplace_write_governance_requests')->where('business_id', $businessId)
                ->where('id', $requestId)->lockForUpdate()->first();
            if (! $request) {
                abort(404);
            }
            if ($request->status !== 'PENDING') {
                throw ValidationException::withMessages(['request' => 'This governance request has already been reviewed.']);
            }
            if ((int) $request->requested_by === (int) $user->id) {
                throw ValidationException::withMessages(['review' => 'The requester cannot review their own governance request.']);
            }

            $status = $decision === 'APPROVE' ? 'APPROVED' : 'REJECTED';
            DB::table('marketplace_write_governance_requests')->where('id', $requestId)->update([
                'status' => $status,
                'reviewed_by' => $user->id,
                'review_reason' => $reason,
                'reviewed_at' => now(),
                'updated_at' => now(),
            ]);

            if ($decision === 'APPROVE') {
                $changes = json_decode($request->proposed_changes, true, 512, JSON_THROW_ON_ERROR);
                $safe = array_intersect_key($changes, array_flip(self::ALLOWED));
                $existing = DB::table('marketplace_write_governance_policies')->where('business_id', $businessId)->first();
                $values = [];
                foreach (self::ALLOWED as $key) {
                    $values[$key] = array_key_exists($key, $safe)
                        ? (bool) $safe[$key]
                        : (bool) ($existing->{$key} ?? ($key === 'require_second_person' || $key === 'require_evidence_reference'));
                }
                if ($existing) {
                    DB::table('marketplace_write_governance_policies')->where('business_id', $businessId)->update(
                        $values + ['updated_by' => $user->id, 'updated_at' => now()]
                    );
                } else {
                    DB::table('marketplace_write_governance_policies')->insert(
                        $values + ['business_id' => $businessId, 'updated_by' => $user->id, 'created_at' => now(), 'updated_at' => now()]
                    );
                }
            }

            $this->event($businessId, (int) $user->id, $requestId, 'CHANGE_'.$status, $reason);
        });
    }

    private function event(int $businessId, ?int $actorId, ?int $requestId, string $type, string $details): void
    {
        DB::table('marketplace_write_governance_events')->insert([
            'business_id' => $businessId, 'actor_id' => $actorId, 'request_id' => $requestId,
            'event_type' => $type, 'details' => $details, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function businessId($user): int
    {
        $id = $user->business_id ?? null;
        abort_unless($id, 403, 'No business is associated with this account.');

        return (int) $id;
    }
}
