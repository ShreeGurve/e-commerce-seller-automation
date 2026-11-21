<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MarketplaceWriteEvidenceService
{
    public function listFor($user, array $filters = [])
    {
        $businessId = $this->businessId($user);

        return DB::table('marketplace_write_evidence')
            ->where('business_id', $businessId)
            ->when(! empty($filters['type']), fn ($q) => $q->where('evidence_type', $filters['type']))
            ->when(! empty($filters['subject_type']), fn ($q) => $q->where('subject_type', $filters['subject_type']))
            ->when(! empty($filters['search']), function ($q) use ($filters) {
                $term = trim($filters['search']);
                $q->where(function ($sub) use ($term) {
                    $sub->where('title', 'like', "%{$term}%")
                        ->orWhere('reference_code', 'like', "%{$term}%");
                });
            })
            ->orderByDesc('created_at')
            ->paginate(25)
            ->withQueryString();
    }

    public function create($user, array $data): int
    {
        $businessId = $this->businessId($user);
        $url = $data['evidence_url'] ?? null;

        if ($url && ! str_starts_with(strtolower($url), 'https://')) {
            throw ValidationException::withMessages([
                'evidence_url' => 'Evidence links must use HTTPS.',
            ]);
        }

        $policy = DB::table('marketplace_write_retention_policies')
            ->where('business_id', $businessId)->first();
        $retentionDays = (int) ($policy->retention_days ?? 365);

        return (int) DB::table('marketplace_write_evidence')->insertGetId([
            'business_id' => $businessId,
            'subject_type' => $data['subject_type'] ?? 'GENERAL',
            'subject_id' => $data['subject_id'] ?? null,
            'evidence_type' => $data['evidence_type'],
            'title' => trim($data['title']),
            'description' => $data['description'] ?? null,
            'evidence_url' => $url,
            'reference_code' => $data['reference_code'] ?? null,
            'retention_status' => 'ACTIVE',
            'retention_expires_at' => now()->addDays($retentionDays),
            'recorded_by' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function findFor($user, int $id): object
    {
        return DB::table('marketplace_write_evidence')
            ->where('business_id', $this->businessId($user))
            ->where('id', $id)
            ->firstOrFail();
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
