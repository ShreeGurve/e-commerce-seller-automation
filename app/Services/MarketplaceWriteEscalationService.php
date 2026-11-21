<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MarketplaceWriteEscalationService
{
    public function dashboard(object $user): array
    {
        $businessId = (int) $user->business_id;

        return [
            'rules' => DB::table('marketplace_write_escalation_rules as r')->leftJoin('users as u', 'u.id', '=', 'r.escalate_to')
                ->where('r.business_id', $businessId)->select('r.*', 'u.name as assignee_name')->orderBy('r.severity')->orderBy('r.breach_type')->get(),
            'history' => DB::table('marketplace_write_escalations as e')->join('marketplace_write_incidents as i', 'i.id', '=', 'e.incident_id')
                ->leftJoin('users as u', 'u.id', '=', 'e.escalated_to')->where('e.business_id', $businessId)
                ->select('e.*', 'i.title as incident_title', 'i.severity', 'u.name as assignee_name')->orderByDesc('e.escalated_at')->limit(100)->get(),
            'users' => DB::table('users')->where('business_id', $businessId)->orderBy('name')->get(['id', 'name']),
        ];
    }

    public function saveRule(object $user, array $data): void
    {
        $severity = strtoupper((string) ($data['severity'] ?? ''));
        $type = strtoupper((string) ($data['breach_type'] ?? 'RESPONSE'));
        $minutes = filter_var($data['after_minutes'] ?? null, FILTER_VALIDATE_INT);
        if (! in_array($severity, ['LOW', 'MEDIUM', 'HIGH', 'CRITICAL'], true) || ! in_array($type, ['RESPONSE', 'RESOLUTION'], true) || $minutes === false || $minutes < 1 || $minutes > 43200) {
            throw ValidationException::withMessages(['severity' => 'Select a valid severity, breach type and escalation delay (1–43200 minutes).']);
        }
        $businessId = (int) $user->business_id;
        $target = $data['escalate_to'] ?? null;
        if ($target !== null && $target !== '' && ! DB::table('users')->where('id', $target)->where('business_id', $businessId)->exists()) {
            throw ValidationException::withMessages(['escalate_to' => 'Choose a user belonging to this business.']);
        }
        DB::table('marketplace_write_escalation_rules')->updateOrInsert(
            ['business_id' => $businessId, 'severity' => $severity, 'breach_type' => $type],
            ['after_minutes' => $minutes, 'escalate_to' => $target ?: null, 'enabled' => (bool) ($data['enabled'] ?? true), 'updated_by' => $user->id, 'updated_at' => now(), 'created_at' => now()]
        );
    }

    /** Apply eligible rules once per incident/rule pair. Safe to run repeatedly. */
    public function process(): int
    {
        $count = 0;
        $rules = DB::table('marketplace_write_escalation_rules')->where('enabled', true)->get();
        foreach ($rules as $rule) {
            $incidents = DB::table('marketplace_write_incidents')->where('business_id', $rule->business_id)
                ->where('severity', $rule->severity)->whereIn('status', ['OPEN', 'INVESTIGATING'])->get();
            foreach ($incidents as $incident) {
                $dueAt = Carbon::parse($incident->created_at)->addMinutes((int) $rule->after_minutes);
                if (now()->lessThanOrEqualTo($dueAt)) {
                    continue;
                }
                $slaType = $rule->breach_type === 'RESPONSE' ? 'RESPONDED' : 'RESOLVED';
                if (DB::table('marketplace_write_sla_events')->where('incident_id', $incident->id)->where('event_type', $slaType)->exists()) {
                    continue;
                }
                $inserted = DB::table('marketplace_write_escalations')->insertOrIgnore([
                    'business_id' => $rule->business_id, 'incident_id' => $incident->id, 'rule_id' => $rule->id,
                    'escalated_to' => $rule->escalate_to, 'escalated_at' => now(),
                    'metadata' => json_encode(['breach_type' => $rule->breach_type, 'after_minutes' => (int) $rule->after_minutes]),
                    'created_at' => now(), 'updated_at' => now(),
                ]);
                if ($inserted) {
                    if ($rule->escalate_to) {
                        DB::table('marketplace_write_incidents')->where('id', $incident->id)->where('business_id', $rule->business_id)->update(['assigned_to' => $rule->escalate_to, 'updated_at' => now()]);
                    }
                    DB::table('marketplace_write_incident_events')->insert([
                        'incident_id' => $incident->id, 'business_id' => $rule->business_id, 'recorded_by' => null,
                        'event_type' => 'ESCALATED', 'from_status' => $incident->status, 'to_status' => $incident->status,
                        'note' => 'Automatically escalated by SLA rule '.$rule->id.'.',
                        'metadata' => json_encode(['rule_id' => $rule->id, 'breach_type' => $rule->breach_type, 'escalated_to' => $rule->escalate_to]),
                        'recorded_at' => now(), 'created_at' => now(), 'updated_at' => now(),
                    ]);
                    $count++;
                }
            }
        }

        return $count;
    }
}
