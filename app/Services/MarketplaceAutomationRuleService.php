<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MarketplaceAutomationRuleService
{
    public const TYPES = ['RESTOCK', 'STOCK_RISK', 'SALES_DECLINE', 'HIGH_RETURN_RATE', 'LOW_PROFIT_MARGIN'];

    public function generate(array $filters = []): array
    {
        $report = app(MarketplaceRecommendationService::class)->dashboard($filters);
        $rules = DB::table('marketplace_automation_rules')->where('is_enabled', true)->get()->keyBy('recommendation_type');
        $created = 0;
        $existing = 0;

        foreach ($report['recommendations'] as $recommendation) {
            $rule = $rules->get($recommendation['type']);
            if (! $rule) {
                continue;
            }

            $dedupe = hash('sha256', implode('|', [
                $rule->id, $recommendation['type'], $recommendation['product_id'] ?? 0,
                $report['period']['from'], $report['period']['to'],
            ]));

            $cooldownActive = DB::table('marketplace_automation_tasks')
                ->where('rule_id', $rule->id)
                ->where('recommendation_type', $recommendation['type'])
                ->where('product_id', $recommendation['product_id'] ?? 0)
                ->where('created_at', '>=', now()->subHours((int) $rule->cooldown_hours))
                ->exists();
            if ($cooldownActive) {
                $existing++;

                continue;
            }

            $inserted = DB::table('marketplace_automation_tasks')->insertOrIgnore([
                'rule_id' => $rule->id,
                'product_id' => $recommendation['product_id'] ?? null,
                'recommendation_type' => $recommendation['type'],
                'priority' => $rule->priority_override ?: $recommendation['priority'],
                'title' => Str::limit(($recommendation['product_name'] ?? 'Marketplace').' — '.str_replace('_', ' ', $recommendation['type']), 178, ''),
                'reason' => $recommendation['reason'],
                'suggested_action' => $rule->action_guidance ?: $recommendation['suggested_action'],
                'metrics' => json_encode($recommendation['metrics'] ?? []),
                'recommendation_snapshot' => json_encode($recommendation),
                'period_from' => $report['period']['from'],
                'period_to' => $report['period']['to'],
                'dedupe_key' => $dedupe,
                'status' => 'PENDING',
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $inserted ? $created++ : $existing++;
        }

        return ['created' => $created, 'existing' => $existing, 'scanned' => count($report['recommendations']), 'period' => $report['period']];
    }

    public function createRule(array $data, ?int $userId = null): int
    {
        if (! in_array($data['recommendation_type'], self::TYPES, true)) {
            throw ValidationException::withMessages(['recommendation_type' => 'Choose a supported recommendation type.']);
        }

        return (int) DB::table('marketplace_automation_rules')->insertGetId([
            'name' => $data['name'], 'recommendation_type' => $data['recommendation_type'],
            'is_enabled' => (bool) ($data['is_enabled'] ?? true),
            'priority_override' => $data['priority_override'] ?? null,
            'cooldown_hours' => (int) ($data['cooldown_hours'] ?? 24),
            'action_guidance' => $data['action_guidance'] ?? null,
            'created_by' => $userId, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function setRuleEnabled(int $ruleId, bool $enabled): void
    {
        $updated = DB::table('marketplace_automation_rules')->where('id', $ruleId)->update(['is_enabled' => $enabled, 'updated_at' => now()]);
        if (! $updated) {
            throw ValidationException::withMessages(['rule' => 'Automation rule was not found.']);
        }
    }

    public function reviewTask(int $taskId, string $decision, ?int $userId, ?string $note = null): void
    {
        if (! in_array($decision, ['APPROVED', 'REJECTED', 'COMPLETED'], true)) {
            throw ValidationException::withMessages(['decision' => 'Invalid task decision.']);
        }
        $task = DB::table('marketplace_automation_tasks')->where('id', $taskId)->first();
        if (! $task) {
            throw ValidationException::withMessages(['task' => 'Task was not found.']);
        }
        $allowed = match ($decision) {
            'APPROVED', 'REJECTED' => ['PENDING'],
            'COMPLETED' => ['APPROVED'],
        };
        if (! in_array($task->status, $allowed, true)) {
            throw ValidationException::withMessages(['task' => 'This task cannot transition from '.$task->status.' to '.$decision.'.']);
        }
        DB::table('marketplace_automation_tasks')->where('id', $taskId)->update([
            'status' => $decision, 'reviewed_by' => $userId, 'reviewed_at' => now(),
            'review_note' => $note, 'updated_at' => now(),
        ]);
    }
}
