<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class MarketplaceWriteComplianceService
{
    public function dashboard($user): array
    {
        $businessId = $this->businessId($user);
        $latestRun = DB::table('marketplace_write_compliance_runs')
            ->where('business_id', $businessId)->orderByDesc('id')->first();

        $findings = DB::table('marketplace_write_compliance_findings')
            ->where('business_id', $businessId)
            ->when($latestRun, fn ($query) => $query->where('run_id', $latestRun->id))
            ->orderByRaw("CASE severity WHEN 'CRITICAL' THEN 1 WHEN 'WARNING' THEN 2 ELSE 3 END")
            ->orderByDesc('id')->paginate(25)->withQueryString();

        return [
            'latestRun' => $latestRun,
            'findings' => $findings,
            'history' => DB::table('marketplace_write_compliance_runs')
                ->where('business_id', $businessId)->orderByDesc('id')->limit(10)->get(),
            'openCount' => DB::table('marketplace_write_compliance_findings')
                ->where('business_id', $businessId)->where('status', 'OPEN')->count(),
        ];
    }

    public function runForBusiness(int $businessId, ?int $actorId = null): int
    {
        return DB::transaction(function () use ($businessId, $actorId) {
            $runId = DB::table('marketplace_write_compliance_runs')->insertGetId([
                'business_id' => $businessId,
                'started_by' => $actorId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $findings = [];
            $policy = DB::table('marketplace_write_retention_policies')
                ->where('business_id', $businessId)->first();
            $alertDays = max(1, (int) ($policy->alert_before_days ?? 30));

            $evidence = DB::table('marketplace_write_evidence')
                ->where('business_id', $businessId)->get();

            foreach ($evidence as $item) {
                if (empty($item->evidence_url) && empty($item->reference_code)) {
                    $findings[] = $this->finding($businessId, $runId, 'EVIDENCE_REFERENCE_MISSING', 'WARNING', 'EVIDENCE', $item->id,
                        'Evidence has no external link or reference code', 'Add a secure evidence URL or a traceable reference code.');
                }

                if (($item->retention_status ?? 'ACTIVE') === 'ACTIVE' && empty($item->retention_expires_at)) {
                    $findings[] = $this->finding($businessId, $runId, 'RETENTION_EXPIRY_MISSING', 'WARNING', 'EVIDENCE', $item->id,
                        'Evidence has no retention expiry date', 'Review the retention policy and assign an expiry date to this record.');
                } elseif (($item->retention_status ?? '') === 'ACTIVE' && ! empty($item->retention_expires_at)
                    && $item->retention_expires_at <= now()->addDays($alertDays)->toDateTimeString()) {
                    $findings[] = $this->finding($businessId, $runId, 'RETENTION_EXPIRING', 'INFO', 'EVIDENCE', $item->id,
                        'Evidence is approaching its retention expiry', 'Review the record before its configured retention expiry date.');
                }

                if (($item->retention_status ?? '') === 'PENDING_DELETION') {
                    $findings[] = $this->finding($businessId, $runId, 'DELETION_APPROVAL_PENDING', 'WARNING', 'EVIDENCE', $item->id,
                        'Evidence is awaiting deletion approval', 'Review the request independently. This monitor never deletes evidence.');
                }
            }

            $activeHolds = DB::table('marketplace_write_evidence_holds')
                ->where('business_id', $businessId)->where('status', 'ACTIVE')->count();
            if ($activeHolds > 0) {
                $findings[] = $this->finding($businessId, $runId, 'ACTIVE_LEGAL_HOLDS', 'INFO', 'RETENTION', null,
                    'Active legal holds require ongoing review', $activeHolds.' active legal hold(s) are protecting evidence.');
            }

            foreach ($findings as $finding) {
                DB::table('marketplace_write_compliance_findings')->insert($finding + [
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }

            $critical = collect($findings)->where('severity', 'CRITICAL')->count();
            $warnings = collect($findings)->where('severity', 'WARNING')->count();
            DB::table('marketplace_write_compliance_runs')->where('id', $runId)->update([
                'finding_count' => count($findings),
                'critical_count' => $critical,
                'warning_count' => $warnings,
                'completed_at' => now(),
                'updated_at' => now(),
            ]);

            return (int) $runId;
        });
    }

    public function export($user): string
    {
        $businessId = $this->businessId($user);
        $run = DB::table('marketplace_write_compliance_runs')->where('business_id', $businessId)->orderByDesc('id')->first();
        $rows = $run ? DB::table('marketplace_write_compliance_findings')
            ->where('business_id', $businessId)->where('run_id', $run->id)->orderBy('id')->get() : collect();

        $stream = fopen('php://temp', 'r+');
        fputcsv($stream, ['Rule', 'Severity', 'Subject', 'Subject ID', 'Finding', 'Details', 'Status', 'Created At']);
        foreach ($rows as $row) {
            fputcsv($stream, [$row->rule_key, $row->severity, $row->subject_type, $row->subject_id, $row->title, $row->details, $row->status, $row->created_at]);
        }
        rewind($stream);
        $csv = stream_get_contents($stream);
        fclose($stream);

        return $csv;
    }

    private function finding(int $businessId, int $runId, string $rule, string $severity, string $subjectType, ?int $subjectId, string $title, string $details): array
    {
        return [
            'business_id' => $businessId, 'run_id' => $runId, 'rule_key' => $rule,
            'severity' => $severity, 'subject_type' => $subjectType, 'subject_id' => $subjectId,
            'title' => $title, 'details' => $details, 'status' => 'OPEN',
        ];
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
