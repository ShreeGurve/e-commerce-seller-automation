<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MarketplaceWriteSlaService
{
    public function dashboard(object $user): array
    {
        $businessId = (int) $user->business_id;
        $policies = DB::table('marketplace_write_sla_policies')->where('business_id', $businessId)->orderBy('severity')->get();
        $incidents = DB::table('marketplace_write_incidents as i')
            ->leftJoin('marketplace_write_sla_policies as p', function ($join) use ($businessId) {
                $join->on('p.severity', '=', 'i.severity')->where('p.business_id', '=', $businessId)->where('p.enabled', '=', 1);
            })
            ->where('i.business_id', $businessId)->whereIn('i.status', ['OPEN', 'INVESTIGATING'])
            ->select('i.*', 'p.response_minutes', 'p.resolution_minutes')
            ->orderByRaw("CASE i.severity WHEN 'CRITICAL' THEN 1 WHEN 'HIGH' THEN 2 WHEN 'MEDIUM' THEN 3 ELSE 4 END")
            ->orderBy('i.created_at')->get();

        $now = now();
        $rows = $incidents->map(function ($incident) use ($now) {
            $responseDue = $incident->response_minutes ? \Illuminate\Support\Carbon::parse($incident->created_at)->addMinutes((int) $incident->response_minutes) : null;
            $resolutionDue = $incident->resolution_minutes ? \Illuminate\Support\Carbon::parse($incident->created_at)->addMinutes((int) $incident->resolution_minutes) : null;
            $responseEvent = DB::table('marketplace_write_sla_events')->where('incident_id', $incident->id)->where('event_type', 'RESPONDED')->exists();
            return (object) array_merge((array) $incident, [
                'response_due_at' => $responseDue,
                'resolution_due_at' => $resolutionDue,
                'response_breached' => !$responseEvent && $responseDue && $now->greaterThan($responseDue),
                'resolution_breached' => $resolutionDue && $now->greaterThan($resolutionDue),
            ]);
        });

        return ['policies' => $policies, 'incidents' => $rows,
            'response_breaches' => $rows->where('response_breached', true)->count(),
            'resolution_breaches' => $rows->where('resolution_breached', true)->count()];
    }

    public function savePolicy(object $user, array $data): object
    {
        $severity = strtoupper((string) ($data['severity'] ?? ''));
        if (!in_array($severity, ['LOW', 'MEDIUM', 'HIGH', 'CRITICAL'], true)) {
            throw ValidationException::withMessages(['severity' => 'Choose a supported incident severity.']);
        }
        $response = filter_var($data['response_minutes'] ?? null, FILTER_VALIDATE_INT);
        $resolution = filter_var($data['resolution_minutes'] ?? null, FILTER_VALIDATE_INT);
        if ($response === false || $response < 1 || $response > 10080 || $resolution === false || $resolution < 1 || $resolution > 43200 || $resolution < $response) {
            throw ValidationException::withMessages(['response_minutes' => 'SLA values must be valid minutes; resolution must be at least the response target.']);
        }
        $businessId = (int) $user->business_id;
        DB::table('marketplace_write_sla_policies')->updateOrInsert(
            ['business_id' => $businessId, 'severity' => $severity],
            ['response_minutes' => $response, 'resolution_minutes' => $resolution, 'enabled' => (bool) ($data['enabled'] ?? true), 'updated_by' => $user->id, 'updated_at' => now(), 'created_at' => now()]
        );
        return DB::table('marketplace_write_sla_policies')->where('business_id', $businessId)->where('severity', $severity)->first();
    }

    public function recordResponse(object $user, int $incidentId): void
    {
        $incident = DB::table('marketplace_write_incidents')->where('business_id', (int) $user->business_id)->where('id', $incidentId)->first();
        if (!$incident) {
            throw ValidationException::withMessages(['incident' => 'Incident not found for this business.']);
        }
        DB::table('marketplace_write_sla_events')->insertOrIgnore([
            'business_id' => (int) $user->business_id, 'incident_id' => $incidentId,
            'event_type' => 'RESPONDED', 'occurred_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}
