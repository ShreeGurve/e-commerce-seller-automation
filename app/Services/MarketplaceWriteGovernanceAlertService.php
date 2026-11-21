<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\MarketplaceWriteGovernanceAlert;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class MarketplaceWriteGovernanceAlertService
{
    /** Create one database notification per alert and eligible business user. */
    public function scan(): array
    {
        $created = 0;
        $checked = 0;
        $now = now();

        $attempts = DB::table('marketplace_write_attempts as a')
            ->where(function ($query) use ($now) {
                $query->whereIn('a.status', ['FAILED', 'REVIEW_REQUIRED'])
                    ->orWhere(function ($stale) use ($now) {
                        $stale->where('a.status', 'SUBMITTING')
                            ->where('a.created_at', '<=', $now->copy()->subMinutes(5));
                    });
            })
            ->where('a.created_at', '>=', $now->copy()->subDays(30))
            ->select('a.id', 'a.business_id', 'a.marketplace_code', 'a.operation', 'a.marketplace_sku', 'a.status', 'a.created_at')
            ->orderBy('a.id')->get();

        foreach ($attempts as $attempt) {
            $checked++;
            $severity = match ($attempt->status) {
                'FAILED' => 'high',
                'REVIEW_REQUIRED' => 'critical',
                default => 'warning',
            };
            $title = match ($attempt->status) {
                'FAILED' => 'Marketplace write failed',
                'REVIEW_REQUIRED' => 'Marketplace write needs outcome verification',
                default => 'Marketplace write may be stuck',
            };
            $message = sprintf('%s %s for SKU %s is %s.', strtoupper((string) $attempt->marketplace_code), (string) $attempt->operation, (string) $attempt->marketplace_sku, strtolower((string) $attempt->status));
            $key = hash('sha256', implode('|', [$attempt->business_id, $attempt->id, $attempt->status]));
            $created += $this->notifyBusiness((int) $attempt->business_id, [
                'alert_key' => $key,
                'category' => 'marketplace_write_attempt',
                'severity' => $severity,
                'title' => $title,
                'message' => $message,
                'attempt_id' => (int) $attempt->id,
                'marketplace' => (string) $attempt->marketplace_code,
                'status' => (string) $attempt->status,
                'url' => '/marketplace-writes/monitoring',
                'occurred_at' => (string) $attempt->created_at,
            ]);
        }

        $incidents = DB::table('marketplace_write_incidents')
            ->where('status', 'OPEN')
            ->whereIn('severity', ['HIGH', 'CRITICAL'])
            ->orderBy('id')->get(['id', 'business_id', 'title', 'severity', 'created_at']);

        foreach ($incidents as $incident) {
            $checked++;
            $key = hash('sha256', implode('|', [$incident->business_id, 'incident', $incident->id, $incident->status ?? 'OPEN']));
            $created += $this->notifyBusiness((int) $incident->business_id, [
                'alert_key' => $key,
                'category' => 'marketplace_write_incident',
                'severity' => strtolower((string) $incident->severity),
                'title' => 'High-priority marketplace write incident',
                'message' => (string) $incident->title,
                'incident_id' => (int) $incident->id,
                'url' => '/marketplace-writes/monitoring',
                'occurred_at' => (string) ($incident->created_at ?? $now),
            ]);
        }

        return ['checked' => $checked, 'created' => $created];
    }

    private function notifyBusiness(int $businessId, array $payload): int
    {
        $created = 0;
        $users = User::query()->where('business_id', $businessId)->where('is_active', true)->get();

        foreach ($users as $user) {
            if (! $user->hasPermission('listings.manage') || ! $this->allowsUserAlert($user, $payload)) {
                continue;
            }
            $exists = $user->notifications()
                ->where('type', MarketplaceWriteGovernanceAlert::class)
                ->whereJsonContains('data->alert_key', $payload['alert_key'])
                ->exists();
            if ($exists) {
                continue;
            }
            $user->notify(new MarketplaceWriteGovernanceAlert($payload));
            $created++;
        }

        return $created;
    }

    private function allowsUserAlert(User $user, array $payload): bool
    {
        $preferences = DB::table('marketplace_write_alert_preferences')->where('user_id', $user->id)->first();
        if (! $preferences) {
            return true; // Existing users retain Package 38 defaults until they save preferences.
        }

        if (($payload['category'] ?? '') === 'marketplace_write_attempt' && ! $preferences->attempt_alerts) {
            return false;
        }
        if (($payload['category'] ?? '') === 'marketplace_write_incident' && ! $preferences->incident_alerts) {
            return false;
        }

        $severityColumn = match ($payload['severity'] ?? 'warning') {
            'critical' => 'critical_alerts',
            'high' => 'high_alerts',
            default => 'warning_alerts',
        };
        if (! (bool) $preferences->{$severityColumn}) {
            return false;
        }

        if (($payload['status'] ?? null) === 'SUBMITTING' && isset($payload['occurred_at'])) {
            return now()->diffInMinutes($payload['occurred_at']) >= (int) $preferences->stale_after_minutes;
        }

        return true;
    }

    public function preferences(User $user): object
    {
        return DB::table('marketplace_write_alert_preferences')->where('user_id', $user->id)->first()
            ?? (object) [
                'attempt_alerts' => true, 'incident_alerts' => true, 'critical_alerts' => true,
                'high_alerts' => true, 'warning_alerts' => true, 'stale_after_minutes' => 10,
            ];
    }

    public function savePreferences(User $user, array $data): void
    {
        DB::table('marketplace_write_alert_preferences')->updateOrInsert(
            ['user_id' => $user->id],
            [
                'business_id' => $user->business_id,
                'attempt_alerts' => (bool) ($data['attempt_alerts'] ?? false),
                'incident_alerts' => (bool) ($data['incident_alerts'] ?? false),
                'critical_alerts' => (bool) ($data['critical_alerts'] ?? false),
                'high_alerts' => (bool) ($data['high_alerts'] ?? false),
                'warning_alerts' => (bool) ($data['warning_alerts'] ?? false),
                'stale_after_minutes' => (int) $data['stale_after_minutes'],
                'updated_at' => now(), 'created_at' => now(),
            ]
        );
    }

    public function inbox(User $user, int $limit = 100): array
    {
        $query = $user->notifications()->where('type', MarketplaceWriteGovernanceAlert::class);

        return [
            'notifications' => (clone $query)->latest()->limit($limit)->get(),
            'unreadCount' => (clone $query)->whereNull('read_at')->count(),
        ];
    }
}
