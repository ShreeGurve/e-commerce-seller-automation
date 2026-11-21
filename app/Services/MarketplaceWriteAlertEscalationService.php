<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\MarketplaceWriteGovernanceAlert;
use Illuminate\Support\Facades\DB;

class MarketplaceWriteAlertEscalationService
{
    public function scan(): array
    {
        $checked = 0;
        $created = 0;
        $now = now();

        $rules = DB::table('marketplace_write_alert_escalation_rules')->where('enabled', true)->get();
        foreach ($rules as $rule) {
            $users = User::query()->where('business_id', $rule->business_id)->where('is_active', true)->get();
            $recipients = $users->filter(fn (User $user) => $user->hasPermission('listings.manage')
                && (! $rule->notify_business_admins || $this->isBusinessAdmin($user)));

            if ($recipients->isEmpty()) {
                continue;
            }

            foreach ($recipients as $recipient) {
                $notifications = $recipient->notifications()
                    ->where('type', MarketplaceWriteGovernanceAlert::class)
                    ->whereNull('read_at')
                    ->where('created_at', '<=', $now->copy()->subMinutes((int) $rule->delay_minutes))
                    ->get();

                foreach ($notifications as $notification) {
                    $payload = $notification->data;
                    if (($payload['category'] ?? null) !== $rule->category || ($payload['severity'] ?? null) !== $rule->severity) {
                        continue;
                    }
                    $checked++;
                    $last = DB::table('marketplace_write_alert_escalation_logs')
                        ->where('source_notification_id', (string) $notification->id)
                        ->where('recipient_user_id', $recipient->id)
                        ->max('escalation_number');
                    $next = ((int) $last) + 1;
                    if ($next > (int) $rule->max_escalations) {
                        continue;
                    }
                    $lastAt = DB::table('marketplace_write_alert_escalation_logs')
                        ->where('source_notification_id', (string) $notification->id)
                        ->where('recipient_user_id', $recipient->id)
                        ->max('escalated_at');
                    if ($lastAt && $now->diffInMinutes($lastAt) < (int) $rule->repeat_every_minutes) {
                        continue;
                    }

                    $key = hash('sha256', implode('|', [$notification->id, $rule->id, $next]));
                    $alreadySent = $recipient->notifications()->where('type', MarketplaceWriteGovernanceAlert::class)
                        ->whereJsonContains('data->alert_key', $key)->exists();
                    if ($alreadySent) {
                        continue;
                    }

                    DB::transaction(function () use ($recipient, $rule, $notification, $payload, $next, $key, $now, &$created) {
                        $recipient->notify(new MarketplaceWriteGovernanceAlert([
                            'alert_key' => $key,
                            'category' => 'marketplace_write_escalation',
                            'severity' => $payload['severity'] ?? 'warning',
                            'title' => 'Escalated: '.($payload['title'] ?? 'Marketplace write alert'),
                            'message' => ($payload['message'] ?? 'An alert remains unread.').' Escalation '.$next.' of '.$rule->max_escalations.'.',
                            'source_notification_id' => (string) $notification->id,
                            'escalation_number' => $next,
                            'url' => $payload['url'] ?? '/marketplace-writes/alerts',
                            'occurred_at' => (string) $now,
                        ]));
                        DB::table('marketplace_write_alert_escalation_logs')->insert([
                            'business_id' => $rule->business_id,
                            'source_notification_id' => (string) $notification->id,
                            'rule_id' => $rule->id,
                            'recipient_user_id' => $recipient->id,
                            'escalation_number' => $next,
                            'escalated_at' => $now,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]);
                        $created++;
                    });
                }
            }
        }

        return ['checked' => $checked, 'created' => $created];
    }

    public function rules(User $user)
    {
        return DB::table('marketplace_write_alert_escalation_rules')->where('business_id', $user->business_id)->orderBy('category')->orderBy('severity')->get();
    }

    public function save(User $user, array $data): void
    {
        DB::table('marketplace_write_alert_escalation_rules')->updateOrInsert(
            ['business_id' => $user->business_id, 'category' => $data['category'], 'severity' => $data['severity']],
            [
                'delay_minutes' => $data['delay_minutes'],
                'repeat_every_minutes' => $data['repeat_every_minutes'],
                'max_escalations' => $data['max_escalations'],
                'notify_business_admins' => (bool) ($data['notify_business_admins'] ?? false),
                'enabled' => (bool) ($data['enabled'] ?? false),
                'created_by' => $user->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    private function isBusinessAdmin(User $user): bool
    {
        return $user->hasRole('Super Admin') || $user->hasRole('Admin');
    }
}
