<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MarketplaceWriteIncidentService
{
    private const SEVERITIES = ['LOW', 'MEDIUM', 'HIGH', 'CRITICAL'];
    private const STATUSES = ['OPEN', 'INVESTIGATING', 'RESOLVED', 'DISMISSED'];

    public function dashboard(int $businessId, array $filters = []): array
    {
        $query = DB::table('marketplace_write_incidents as i')
            ->join('marketplace_write_attempts as a', 'a.id', '=', 'i.attempt_id')
            ->leftJoin('users as assignee', 'assignee.id', '=', 'i.assigned_to')
            ->leftJoin('users as opener', 'opener.id', '=', 'i.opened_by')
            ->where('i.business_id', $businessId)
            ->select(['i.*', 'a.marketplace_code', 'a.operation', 'a.marketplace_sku', 'a.status as submission_status',
                'assignee.name as assignee_name', 'opener.name as opener_name']);
        if (!empty($filters['status']) && in_array($filters['status'], self::STATUSES, true)) $query->where('i.status', $filters['status']);
        if (!empty($filters['severity']) && in_array($filters['severity'], self::SEVERITIES, true)) $query->where('i.severity', $filters['severity']);
        $incidents = $query->orderByRaw("CASE i.severity WHEN 'CRITICAL' THEN 1 WHEN 'HIGH' THEN 2 WHEN 'MEDIUM' THEN 3 ELSE 4 END")
            ->orderByDesc('i.created_at')->limit(250)->get();
        $counts = DB::table('marketplace_write_incidents')->where('business_id', $businessId)
            ->selectRaw("COUNT(*) as total, SUM(CASE WHEN status IN ('OPEN','INVESTIGATING') THEN 1 ELSE 0 END) as active, SUM(CASE WHEN severity = 'CRITICAL' AND status IN ('OPEN','INVESTIGATING') THEN 1 ELSE 0 END) as critical, SUM(CASE WHEN status = 'RESOLVED' THEN 1 ELSE 0 END) as resolved")
            ->first();
        return compact('incidents', 'counts', 'filters');
    }

    public function openFromAttempt(int $attemptId, User $user, array $data): object
    {
        $severity = strtoupper((string) ($data['severity'] ?? 'MEDIUM'));
        if (!in_array($severity, self::SEVERITIES, true)) throw ValidationException::withMessages(['severity' => 'Choose a valid severity.']);
        $title = trim((string) ($data['title'] ?? ''));
        if ($title === '' || mb_strlen($title) > 180) throw ValidationException::withMessages(['title' => 'Enter an incident title up to 180 characters.']);
        $description = trim((string) ($data['description'] ?? ''));
        return DB::transaction(function () use ($attemptId, $user, $severity, $title, $description) {
            $attempt = DB::table('marketplace_write_attempts')->where('id', $attemptId)->where('business_id', $user->business_id)->first();
            if (!$attempt) throw ValidationException::withMessages(['attempt' => 'Marketplace write attempt was not found for this business.']);
            $key = hash('sha256', $attempt->id.'|'.mb_strtolower($title));
            $existing = DB::table('marketplace_write_incidents')->where('business_id', $user->business_id)->where('incident_key', $key)->first();
            if ($existing) return $existing;
            $id = DB::table('marketplace_write_incidents')->insertGetId([
                'business_id' => $user->business_id, 'attempt_id' => $attempt->id, 'incident_key' => $key,
                'title' => $title, 'description' => $description ?: null, 'severity' => $severity,
                'status' => 'OPEN', 'opened_by' => $user->id, 'created_at' => now(), 'updated_at' => now(),
            ]);
            $this->event($id, (int) $user->business_id, $user->id, 'OPENED', null, 'OPEN', $description ?: 'Incident created.');
            return DB::table('marketplace_write_incidents')->where('id', $id)->first();
        });
    }

    public function update(int $incidentId, User $user, array $data): object
    {
        $status = strtoupper((string) ($data['status'] ?? ''));
        $severity = strtoupper((string) ($data['severity'] ?? ''));
        if ($status !== '' && !in_array($status, self::STATUSES, true)) throw ValidationException::withMessages(['status' => 'Choose a valid incident status.']);
        if ($severity !== '' && !in_array($severity, self::SEVERITIES, true)) throw ValidationException::withMessages(['severity' => 'Choose a valid severity.']);
        $note = trim((string) ($data['note'] ?? ''));
        return DB::transaction(function () use ($incidentId, $user, $status, $severity, $note, $data) {
            $incident = DB::table('marketplace_write_incidents')->where('id', $incidentId)->where('business_id', $user->business_id)->lockForUpdate()->first();
            if (!$incident) throw ValidationException::withMessages(['incident' => 'Incident not found for this business.']);
            if (in_array($incident->status, ['RESOLVED', 'DISMISSED'], true) && $status !== '' && $status !== $incident->status) {
                throw ValidationException::withMessages(['status' => 'Closed incidents cannot be reopened; create a new incident if needed.']);
            }
            $assignee = array_key_exists('assigned_to', $data) ? ((string) $data['assigned_to'] !== '' ? (int) $data['assigned_to'] : null) : $incident->assigned_to;
            if ($assignee && !DB::table('users')->where('id', $assignee)->where('business_id', $user->business_id)->exists()) {
                throw ValidationException::withMessages(['assigned_to' => 'Assignee must belong to this business.']);
            }
            $newStatus = $status ?: $incident->status;
            $newSeverity = $severity ?: $incident->severity;
            $values = ['status' => $newStatus, 'severity' => $newSeverity, 'assigned_to' => $assignee, 'updated_at' => now()];
            if (in_array($newStatus, ['RESOLVED', 'DISMISSED'], true) && !$incident->resolved_at) {
                $values += ['resolved_at' => now(), 'resolved_by' => $user->id];
            }
            DB::table('marketplace_write_incidents')->where('id', $incidentId)->update($values);
            $changed = $newStatus !== $incident->status || $newSeverity !== $incident->severity || $assignee !== $incident->assigned_to;
            if ($changed || $note !== '') $this->event($incidentId, (int) $user->business_id, $user->id, 'UPDATED', $incident->status, $newStatus, $note ?: 'Incident details updated.');
            return DB::table('marketplace_write_incidents')->where('id', $incidentId)->first();
        });
    }

    public function history(int $incidentId, User $user)
    {
        $exists = DB::table('marketplace_write_incidents')->where('id', $incidentId)->where('business_id', $user->business_id)->exists();
        if (!$exists) throw ValidationException::withMessages(['incident' => 'Incident not found for this business.']);
        return DB::table('marketplace_write_incident_events as e')->leftJoin('users as u', 'u.id', '=', 'e.recorded_by')
            ->where('e.incident_id', $incidentId)->where('e.business_id', $user->business_id)
            ->orderByDesc('e.recorded_at')->select(['e.*', 'u.name as recorded_by_name'])->get();
    }

    private function event(int $incidentId, int $businessId, ?int $userId, string $type, ?string $from, ?string $to, string $note): void
    {
        DB::table('marketplace_write_incident_events')->insert([
            'incident_id' => $incidentId, 'business_id' => $businessId, 'recorded_by' => $userId,
            'event_type' => $type, 'from_status' => $from, 'to_status' => $to, 'note' => $note,
            'recorded_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}
