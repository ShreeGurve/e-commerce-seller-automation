<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MarketplaceWriteAuditService
{
    private const STATUSES = ['PREPARED', 'SUBMITTING', 'ACCEPTED', 'REVIEW_REQUIRED', 'FAILED', 'EXPIRED'];

    private const OPERATIONS = ['PRICE_UPDATE', 'INVENTORY_UPDATE'];

    public function dashboard(int $businessId, array $filters = []): array
    {
        $query = $this->baseQuery($businessId, $filters);
        $attempts = (clone $query)->orderByDesc('a.created_at')->limit(250)->get();
        $countsQuery = DB::table('marketplace_write_attempts as a')->where('a.business_id', $businessId);
        $this->applyFilters($countsQuery, $filters);
        $verificationQuery = DB::table('marketplace_write_attempts as a')
            ->leftJoin('marketplace_write_verifications as v', 'v.attempt_id', '=', 'a.id')
            ->where('a.business_id', $businessId);
        $this->applyFilters($verificationQuery, $filters);

        $counts = [
            'total' => (clone $countsQuery)->count(),
            'accepted' => (clone $countsQuery)->where('a.status', 'ACCEPTED')->count(),
            'failed' => (clone $countsQuery)->where('a.status', 'FAILED')->count(),
            'review_required' => (clone $countsQuery)->where('a.status', 'REVIEW_REQUIRED')->count(),
            'prepared' => (clone $countsQuery)->whereIn('a.status', ['PREPARED', 'SUBMITTING'])->count(),
            'live_confirmed' => (clone $verificationQuery)->where('v.status', 'LIVE_CONFIRMED')->count(),
            'verification_pending' => (clone $verificationQuery)->whereNull('v.id')->whereIn('a.status', ['ACCEPTED', 'REVIEW_REQUIRED'])->count(),
            'independent_approvals' => (clone $countsQuery)->whereNotNull('a.approved_by')->whereColumn('a.prepared_by', '<>', 'a.approved_by')->count(),
            'approval_exceptions' => (clone $countsQuery)->whereNotNull('a.approved_by')->whereColumn('a.prepared_by', '=', 'a.approved_by')->count(),
        ];

        return compact('attempts', 'counts', 'filters');
    }

    public function exportRows(int $businessId, array $filters = [])
    {
        return $this->baseQuery($businessId, $filters)->orderByDesc('a.created_at')->limit(5000)->get();
    }

    public function normalizeFilters(array $filters): array
    {
        $status = strtoupper(trim((string) ($filters['status'] ?? '')));
        $operation = strtoupper(trim((string) ($filters['operation'] ?? '')));
        $marketplace = strtolower(trim((string) ($filters['marketplace'] ?? '')));
        if ($status !== '' && ! in_array($status, self::STATUSES, true)) {
            throw ValidationException::withMessages(['status' => 'Choose a valid write status.']);
        }
        if ($operation !== '' && ! in_array($operation, self::OPERATIONS, true)) {
            throw ValidationException::withMessages(['operation' => 'Choose a valid marketplace write operation.']);
        }
        if ($marketplace !== '' && ! in_array($marketplace, ['amazon', 'flipkart'], true)) {
            throw ValidationException::withMessages(['marketplace' => 'Choose Amazon or Flipkart.']);
        }
        foreach (['from', 'to'] as $field) {
            if (! empty($filters[$field]) && ! preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $filters[$field])) {
                throw ValidationException::withMessages([$field => 'Use a valid date in YYYY-MM-DD format.']);
            }
        }
        if (! empty($filters['from']) && ! empty($filters['to']) && $filters['from'] > $filters['to']) {
            throw ValidationException::withMessages(['to' => 'The end date must be on or after the start date.']);
        }

        return ['status' => $status, 'operation' => $operation, 'marketplace' => $marketplace,
            'from' => $filters['from'] ?? '', 'to' => $filters['to'] ?? ''];
    }

    private function baseQuery(int $businessId, array $filters)
    {
        $query = DB::table('marketplace_write_attempts as a')
            ->leftJoin('marketplace_write_verifications as v', 'v.attempt_id', '=', 'a.id')
            ->leftJoin('users as preparer', 'preparer.id', '=', 'a.prepared_by')
            ->leftJoin('users as approver', 'approver.id', '=', 'a.approved_by')
            ->leftJoin('users as verifier', 'verifier.id', '=', 'v.verified_by')
            ->where('a.business_id', $businessId)
            ->select([
                'a.id', 'a.marketplace_code', 'a.operation', 'a.marketplace_sku', 'a.status',
                'a.payload_hash', 'a.idempotency_key', 'a.error_message', 'a.prepared_by', 'a.approved_by',
                'a.created_at', 'a.submitted_at', 'a.approved_at',
                'preparer.name as prepared_by_name', 'approver.name as approved_by_name',
                'v.status as verification_status', 'v.issue_code', 'v.external_submission_id',
                'v.checked_at as verified_at', 'verifier.name as verified_by_name',
            ]);
        $this->applyFilters($query, $filters);

        return $query;
    }

    private function applyFilters($query, array $filters): void
    {
        if (! empty($filters['status'])) {
            $query->where('a.status', strtoupper($filters['status']));
        }
        if (! empty($filters['operation'])) {
            $query->where('a.operation', strtoupper($filters['operation']));
        }
        if (! empty($filters['marketplace'])) {
            $query->where('a.marketplace_code', strtolower($filters['marketplace']));
        }
        if (! empty($filters['from'])) {
            $query->whereDate('a.created_at', '>=', $filters['from']);
        }
        if (! empty($filters['to'])) {
            $query->whereDate('a.created_at', '<=', $filters['to']);
        }
    }
}
