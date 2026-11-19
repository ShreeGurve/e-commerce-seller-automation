<?php

namespace App\Services;

use App\Models\{Listing, MarketplaceListingIssue, MarketplaceListingIssueEvent, MarketplaceListingComplianceCheck};
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Tracks seller-reported/imported listing issues; it does not poll marketplaces or alter listing status. */
class MarketplaceListingComplianceService
{
    private const TYPES = ['COMPLIANCE', 'CONTENT', 'IMAGE', 'DOCUMENTATION', 'IDENTITY', 'PRODUCT', 'OTHER'];
    private const SEVERITIES = ['INFO', 'WARNING', 'CRITICAL', 'BLOCKER'];
    private const STATUSES = ['OPEN', 'IN_PROGRESS', 'RESOLVED', 'DISMISSED'];

    public function report(Listing $listing, array $data, ?int $userId = null): MarketplaceListingIssue
    {
        $type = strtoupper($data['issue_type'] ?? 'COMPLIANCE');
        $severity = strtoupper($data['severity'] ?? 'WARNING');
        if (!in_array($type, self::TYPES, true)) throw ValidationException::withMessages(['issue_type' => 'Unsupported issue type.']);
        if (!in_array($severity, self::SEVERITIES, true)) throw ValidationException::withMessages(['severity' => 'Unsupported severity.']);
        $source = strtoupper($data['source'] ?? 'MANUAL');
        if (!in_array($source, ['MANUAL', 'IMPORT'], true)) throw ValidationException::withMessages(['source' => 'Unsupported issue source.']);
        if (!preg_match('/^[A-Z0-9._:-]{2,100}$/', strtoupper($data['issue_code'] ?? ''))) throw ValidationException::withMessages(['issue_code' => 'Use 2–100 letters, numbers, dots, underscores, colons or hyphens.']);
        return DB::transaction(function () use ($listing, $data, $userId, $type, $severity) {
            $listing = Listing::query()->lockForUpdate()->findOrFail($listing->id);
            $versionId = $data['listing_version_id'] ?? $listing->current_version_id;
            if ($versionId && !\App\Models\ListingVersion::query()->where('id', $versionId)->where('listing_id', $listing->id)->exists()) {
                throw ValidationException::withMessages(['listing_version_id' => 'The selected version does not belong to this listing.']);
            }
            $issue = MarketplaceListingIssue::create([
                'listing_id' => $listing->id, 'listing_version_id' => $versionId,
                'external_issue_id' => $data['external_issue_id'] ?? null, 'issue_code' => strtoupper($data['issue_code']),
                'issue_type' => $type, 'severity' => $severity, 'title' => trim($data['title']),
                'description' => $data['description'] ?? null, 'source' => $source,
                'status' => 'OPEN', 'is_suppression' => (bool) ($data['is_suppression'] ?? false),
                'detected_at' => $data['detected_at'] ?? now(), 'due_at' => $data['due_at'] ?? null,
                'evidence' => $this->cleanEvidence($data['evidence'] ?? []), 'created_by' => $userId, 'updated_by' => $userId,
            ]);
            $this->event($issue, 'REPORTED', null, 'OPEN', $data['description'] ?? 'Issue reported.', $issue->evidence, $userId);
            return $issue->refresh();
        });
    }

    public function transition(MarketplaceListingIssue $issue, string $status, ?string $note = null, array $evidence = [], ?int $userId = null): MarketplaceListingIssue
    {
        $status = strtoupper($status);
        if (!in_array($status, self::STATUSES, true)) throw ValidationException::withMessages(['status' => 'Unsupported issue status.']);
        return DB::transaction(function () use ($issue, $status, $note, $evidence, $userId) {
            $issue = MarketplaceListingIssue::query()->lockForUpdate()->findOrFail($issue->id);
            $from = $issue->status;
            if ($from === $status) throw ValidationException::withMessages(['status' => 'Issue is already in this status.']);
            if (in_array($from, ['RESOLVED', 'DISMISSED'], true) && $status !== 'OPEN') {
                throw ValidationException::withMessages(['status' => 'Reopen a closed issue before moving it to another active status.']);
            }
            if (in_array($status, ['RESOLVED', 'DISMISSED'], true) && trim((string) $note) === '') {
                throw ValidationException::withMessages(['note' => 'Add an audit note before resolving or dismissing an issue.']);
            }
            $issue->update(['status' => $status, 'resolved_at' => $status === 'RESOLVED' ? now() : null,
                'resolution_notes' => $status === 'RESOLVED' ? $note : $issue->resolution_notes,
                'evidence' => array_values(array_merge($issue->evidence ?? [], $this->cleanEvidence($evidence))), 'updated_by' => $userId]);
            $this->event($issue, 'STATUS_CHANGED', $from, $status, $note, $this->cleanEvidence($evidence), $userId);
            return $issue->refresh();
        });
    }

    /** A manual recheck snapshot only; no external marketplace request is made. */
    public function recheck(Listing $listing, ?int $userId = null): MarketplaceListingComplianceCheck
    {
        $listing = Listing::query()->findOrFail($listing->id);
        $open = MarketplaceListingIssue::query()->where('listing_id', $listing->id)->whereIn('status', ['OPEN', 'IN_PROGRESS']);
        $openCount = (clone $open)->count();
        $suppressionCount = (clone $open)->where('is_suppression', true)->count();
        $bySeverity = (clone $open)->selectRaw('severity, COUNT(*) as total')->groupBy('severity')->pluck('total', 'severity')->all();
        return MarketplaceListingComplianceCheck::create([
            'listing_id' => $listing->id, 'check_type' => 'MANUAL_RECHECK',
            'result' => $openCount === 0 ? 'CLEAR' : ($suppressionCount > 0 ? 'SUPPRESSED_OR_AT_RISK' : 'ISSUES_OPEN'),
            'open_issues' => $openCount, 'suppression_issues' => $suppressionCount,
            'summary' => ['severity_counts' => $bySeverity, 'checked_at' => now()->toIso8601String(), 'source' => 'LOCAL_ISSUE_REGISTER'],
            'checked_by' => $userId,
        ]);
    }

    private function event(MarketplaceListingIssue $issue, string $type, ?string $from, ?string $to, ?string $note, array $evidence, ?int $userId): void
    {
        MarketplaceListingIssueEvent::create(['issue_id' => $issue->id, 'event_type' => $type, 'from_status' => $from, 'to_status' => $to,
            'note' => $note, 'evidence' => $evidence, 'user_id' => $userId, 'created_at' => now()]);
    }

    private function cleanEvidence(array $evidence): array
    {
        return array_values(array_filter(array_map(function ($item) {
            if (!is_array($item)) return null;
            $label = trim((string) ($item['label'] ?? ''));
            $reference = trim((string) ($item['reference'] ?? ''));
            if ($label === '' || $reference === '') return null;
            return ['label' => mb_substr($label, 0, 160), 'reference' => mb_substr($reference, 0, 500), 'note' => isset($item['note']) ? mb_substr((string) $item['note'], 0, 500) : null];
        }, $evidence)));
    }
}
