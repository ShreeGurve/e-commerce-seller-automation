<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class MarketplaceWriteExecutionMonitoringService
{
    public function dashboard(int $businessId): array
    {
        $incidents = DB::table('marketplace_write_incidents as i')
            ->join('marketplace_write_attempts as a', 'a.id', '=', 'i.attempt_id')
            ->where('i.business_id', $businessId)
            ->select('i.*', 'a.marketplace_code', 'a.operation', 'a.marketplace_sku', 'a.status as attempt_status', 'a.error_message', 'a.submitted_at')
            ->orderByRaw("CASE i.severity WHEN 'CRITICAL' THEN 0 WHEN 'HIGH' THEN 1 WHEN 'MEDIUM' THEN 2 ELSE 3 END")
            ->orderByDesc('i.created_at')
            ->paginate(25);

        $counts = DB::table('marketplace_write_incidents')
            ->where('business_id', $businessId)
            ->selectRaw("COUNT(*) as total")
            ->selectRaw("SUM(CASE WHEN status = 'OPEN' THEN 1 ELSE 0 END) as open")
            ->selectRaw("SUM(CASE WHEN status = 'IN_PROGRESS' THEN 1 ELSE 0 END) as in_progress")
            ->selectRaw("SUM(CASE WHEN status = 'RESOLVED' THEN 1 ELSE 0 END) as resolved")
            ->first();

        return compact('incidents', 'counts');
    }

    /**
     * Detects uncertain or failed writes. It never retries a marketplace request
     * and never changes the remote execution status automatically.
     */
    public function scan(?int $businessId = null): array
    {
        foreach (['marketplace_write_attempts', 'marketplace_write_incidents', 'marketplace_write_incident_events'] as $table) {
            if (! Schema::hasTable($table)) {
                throw new \RuntimeException("Required table {$table} is missing. Install the marketplace write incident migrations first.");
            }
        }

        $staleMinutes = max(5, (int) config('marketplace_writes.monitoring_stale_minutes', 10));
        $cutoff = now()->subMinutes($staleMinutes);
        $query = DB::table('marketplace_write_attempts')
            ->whereIn('status', ['SUBMITTING', 'REVIEW_REQUIRED', 'FAILED'])
            ->where(function ($q) use ($cutoff) {
                $q->whereIn('status', ['REVIEW_REQUIRED', 'FAILED'])
                    ->orWhere(function ($sub) use ($cutoff) {
                        $sub->where('status', 'SUBMITTING')
                            ->whereNotNull('submitted_at')
                            ->where('submitted_at', '<=', $cutoff);
                    });
            });
        if ($businessId !== null) {
            $query->where('business_id', $businessId);
        }

        $detected = 0;
        $created = 0;
        foreach ($query->orderBy('id')->limit(1000)->get() as $attempt) {
            $detected++;
            $category = match ($attempt->status) {
                'SUBMITTING' => 'STALE_SUBMISSION',
                'REVIEW_REQUIRED' => 'OUTCOME_UNCERTAIN',
                default => 'MARKETPLACE_WRITE_FAILED',
            };
            $key = 'MWMON-'.$attempt->id.'-'.$category;
            $title = match ($category) {
                'STALE_SUBMISSION' => 'Marketplace submission is stuck in SUBMITTING',
                'OUTCOME_UNCERTAIN' => 'Marketplace write requires outcome reconciliation',
                default => 'Marketplace write failed',
            };
            $description = trim(($attempt->error_message ?: 'No additional error detail was recorded.')
                .' Review the marketplace listing/order state before attempting any further write. Do not blindly retry.');

            $wasCreated = DB::transaction(function () use ($attempt, $key, $title, $description, $category) {
                $exists = DB::table('marketplace_write_incidents')
                    ->where('business_id', $attempt->business_id)
                    ->where('incident_key', $key)->exists();
                if ($exists) {
                    return false;
                }

                $incidentId = DB::table('marketplace_write_incidents')->insertGetId([
                    'business_id' => $attempt->business_id,
                    'attempt_id' => $attempt->id,
                    'incident_key' => $key,
                    'title' => $title,
                    'description' => $description,
                    'severity' => $category === 'STALE_SUBMISSION' ? 'HIGH' : ($category === 'OUTCOME_UNCERTAIN' ? 'HIGH' : 'MEDIUM'),
                    'status' => 'OPEN',
                    'assigned_to' => null,
                    'opened_by' => null,
                    'resolved_by' => null,
                    'resolved_at' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                DB::table('marketplace_write_incident_events')->insert([
                    'incident_id' => $incidentId,
                    'business_id' => $attempt->business_id,
                    'recorded_by' => null,
                    'event_type' => 'AUTO_DETECTED',
                    'from_status' => null,
                    'to_status' => 'OPEN',
                    'note' => 'Automatically detected by Marketplace Write Execution Monitor.',
                    'metadata' => json_encode(['attempt_id' => $attempt->id, 'attempt_status' => $attempt->status, 'category' => $category]),
                    'recorded_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                return true;
            });
            if ($wasCreated) {
                $created++;
            }
        }

        return ['detected' => $detected, 'created' => $created, 'stale_minutes' => $staleMinutes];
    }

    public function acknowledge(int $incidentId, int $businessId, int $userId, string $note): void
    {
        $this->transition($incidentId, $businessId, $userId, 'IN_PROGRESS', 'ACKNOWLEDGED', $note);
    }

    public function resolve(int $incidentId, int $businessId, int $userId, string $note): void
    {
        $this->transition($incidentId, $businessId, $userId, 'RESOLVED', 'RESOLVED', $note);
    }

    private function transition(int $incidentId, int $businessId, int $userId, string $to, string $event, string $note): void
    {
        DB::transaction(function () use ($incidentId, $businessId, $userId, $to, $event, $note) {
            $incident = DB::table('marketplace_write_incidents')
                ->where('business_id', $businessId)->where('id', $incidentId)->lockForUpdate()->first();
            if (! $incident) {
                throw ValidationException::withMessages(['incident' => 'Incident not found for this business.']);
            }
            if ($incident->status === 'RESOLVED') {
                throw ValidationException::withMessages(['incident' => 'Resolved incidents cannot be changed.']);
            }
            if ($to === 'RESOLVED' && trim($note) === '') {
                throw ValidationException::withMessages(['note' => 'A resolution note is required.']);
            }

            DB::table('marketplace_write_incidents')->where('id', $incidentId)->update([
                'status' => $to,
                'resolved_by' => $to === 'RESOLVED' ? $userId : null,
                'resolved_at' => $to === 'RESOLVED' ? now() : null,
                'updated_at' => now(),
            ]);
            DB::table('marketplace_write_incident_events')->insert([
                'incident_id' => $incidentId,
                'business_id' => $businessId,
                'recorded_by' => $userId,
                'event_type' => $event,
                'from_status' => $incident->status,
                'to_status' => $to,
                'note' => $note,
                'metadata' => null,
                'recorded_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }
}
