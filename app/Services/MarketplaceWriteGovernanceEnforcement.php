<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MarketplaceWriteGovernanceEnforcement
{
    /** Fail closed: every live write must have an approved business policy. */
    public function assertCanExecute(object $attempt, object $approver): void
    {
        $businessId = (int) $attempt->business_id;
        $policy = DB::table('marketplace_write_governance_policies')
            ->where('business_id', $businessId)->first();

        if (! $policy || ! $policy->writes_enabled) {
            $this->blocked('Marketplace writes are disabled by the approved business governance policy.');
        }

        if ($attempt->operation === 'INVENTORY_UPDATE' && ! $policy->inventory_auto_sync) {
            $this->blocked('Inventory writes are disabled by the approved business governance policy.');
        }

        $emergency = DB::table('marketplace_write_emergency_controls')
            ->where('business_id', $businessId)->first();
        if ($emergency && $emergency->is_active) {
            $this->blocked('The marketplace write emergency stop is active: '.$emergency->reason);
        }

        // The existing execution workflow already enforces a different approver.
        // Keep that stricter requirement even if a policy is configured otherwise.
        if ($policy->require_second_person && (int) $attempt->prepared_by === (int) $approver->id) {
            $this->blocked('Independent second-person approval is required.');
        }

        if ($policy->require_evidence_reference) {
            $evidence = DB::table('marketplace_write_evidence')
                ->where('business_id', $businessId)
                ->where('subject_type', 'WRITE_ATTEMPT')
                ->where('subject_id', $attempt->id)
                ->where('retention_status', 'ACTIVE')
                ->where(function ($query) {
                    $query->whereNotNull('reference_code')->orWhereNotNull('evidence_url');
                })
                ->exists();

            if (! $evidence) {
                $this->blocked('An active evidence record with a reference code or HTTPS link must be attached to this write attempt before submission.');
            }
        }
    }

    private function blocked(string $message): never
    {
        throw ValidationException::withMessages(['governance' => $message]);
    }
}
