<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MarketplaceWriteRecoveryService
{
    public function dashboard(object $user): array
    {
        $businessId = (int) $user->business_id;
        return [
            'cases' => DB::table('marketplace_write_recovery_cases as c')
                ->join('marketplace_write_incidents as i', 'i.id', '=', 'c.incident_id')
                ->leftJoin('users as u', 'u.id', '=', 'c.owner_id')
                ->where('c.business_id', $businessId)
                ->select('c.*', 'i.title as incident_title', 'i.severity', 'i.status as incident_status', 'u.name as owner_name')
                ->orderByRaw("CASE WHEN c.status = 'OPEN' THEN 0 WHEN c.status = 'IN_PROGRESS' THEN 1 ELSE 2 END")
                ->orderBy('c.target_at')->limit(200)->get(),
            'incidents' => DB::table('marketplace_write_incidents')->where('business_id', $businessId)
                ->whereIn('status', ['OPEN', 'INVESTIGATING'])->orderByDesc('created_at')->get(['id', 'title', 'severity']),
            'users' => DB::table('users')->where('business_id', $businessId)->orderBy('name')->get(['id', 'name']),
        ];
    }

    public function create(object $user, array $data): int
    {
        $businessId = (int) $user->business_id;
        $incidentId = (int) ($data['incident_id'] ?? 0);
        if (!DB::table('marketplace_write_incidents')->where('id', $incidentId)->where('business_id', $businessId)->exists()) {
            throw ValidationException::withMessages(['incident_id' => 'Select an incident belonging to your business.']);
        }
        $ownerId = $data['owner_id'] ?? null;
        if ($ownerId && !DB::table('users')->where('id', $ownerId)->where('business_id', $businessId)->exists()) {
            throw ValidationException::withMessages(['owner_id' => 'Select an owner belonging to your business.']);
        }
        if (DB::table('marketplace_write_recovery_cases')->where('business_id', $businessId)->where('incident_id', $incidentId)->whereIn('status', ['OPEN', 'IN_PROGRESS'])->exists()) {
            throw ValidationException::withMessages(['incident_id' => 'An active recovery case already exists for this incident.']);
        }
        $playbook = trim((string) ($data['playbook'] ?? ''));
        if ($playbook === '' || mb_strlen($playbook) > 80) throw ValidationException::withMessages(['playbook' => 'Enter a playbook name (maximum 80 characters).']);
        $caseId = DB::table('marketplace_write_recovery_cases')->insertGetId([
            'business_id' => $businessId, 'incident_id' => $incidentId, 'playbook' => $playbook,
            'status' => 'OPEN', 'owner_id' => $ownerId ?: null,
            'target_at' => !empty($data['target_at']) ? $data['target_at'] : null,
            'created_by' => $user->id, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $steps = [
            'Confirm the failed marketplace operation and affected listing/order',
            'Review request payload, validation errors and marketplace response',
            'Check marketplace connection, permissions and rate limits',
            'Correct the root cause and validate the proposed change',
            'Retry through the approved marketplace write workflow',
            'Verify the result in the marketplace and attach evidence',
        ];
        foreach ($steps as $index => $title) {
            DB::table('marketplace_write_recovery_steps')->insert([
                'recovery_case_id' => $caseId, 'step_title' => $title, 'status' => 'PENDING',
                'sort_order' => $index + 1, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        return $caseId;
    }

    public function updateStep(object $user, int $caseId, int $stepId, array $data): void
    {
        $businessId = (int) $user->business_id;
        $case = DB::table('marketplace_write_recovery_cases')->where('id', $caseId)->where('business_id', $businessId)->first();
        if (!$case) abort(404);
        $status = strtoupper((string) ($data['status'] ?? ''));
        if (!in_array($status, ['PENDING', 'DONE', 'BLOCKED'], true)) throw ValidationException::withMessages(['status' => 'Invalid recovery step status.']);
        $updated = DB::table('marketplace_write_recovery_steps')->where('id', $stepId)->where('recovery_case_id', $caseId)->update([
            'status' => $status, 'step_notes' => $data['step_notes'] ?? null,
            'completed_by' => $status === 'DONE' ? $user->id : null,
            'completed_at' => $status === 'DONE' ? now() : null, 'updated_at' => now(),
        ]);
        if (!$updated) abort(404);
        $remaining = DB::table('marketplace_write_recovery_steps')->where('recovery_case_id', $caseId)->where('status', '!=', 'DONE')->count();
        DB::table('marketplace_write_recovery_cases')->where('id', $caseId)->update([
            'status' => $remaining === 0 ? 'READY_TO_VERIFY' : 'IN_PROGRESS', 'updated_at' => now(),
        ]);
    }

    public function close(object $user, int $caseId, array $data): void
    {
        $summary = trim((string) ($data['resolution_summary'] ?? ''));
        if ($summary === '') throw ValidationException::withMessages(['resolution_summary' => 'Add a resolution summary before closing.']);
        $case = DB::table('marketplace_write_recovery_cases')->where('id', $caseId)->where('business_id', (int) $user->business_id)->first();
        if (!$case) abort(404);
        $pending = DB::table('marketplace_write_recovery_steps')->where('recovery_case_id', $caseId)->where('status', '!=', 'DONE')->exists();
        if ($pending) throw ValidationException::withMessages(['resolution_summary' => 'Complete all recovery steps before closing the case.']);
        DB::table('marketplace_write_recovery_cases')->where('id', $caseId)->update(['status' => 'CLOSED', 'resolution_summary' => $summary, 'updated_at' => now()]);
    }
}
