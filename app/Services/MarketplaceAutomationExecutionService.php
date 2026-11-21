<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MarketplaceAutomationExecutionService
{
    public const MODES = ['DRY_RUN', 'LIVE'];

    public const TYPES = ['RESTOCK', 'STOCK_RISK', 'SALES_DECLINE', 'HIGH_RETURN_RATE', 'LOW_PROFIT_MARGIN'];

    /**
     * Validate and record an execution attempt. This release intentionally has no live adapters.
     * DRY_RUN creates a preview only and never mutates inventory, prices, listings, orders or finance data.
     */
    public function execute(int $taskId, string $mode = 'DRY_RUN', ?int $userId = null, ?string $idempotencyKey = null): array
    {
        $mode = strtoupper($mode);
        if (! in_array($mode, self::MODES, true)) {
            throw ValidationException::withMessages(['mode' => 'Choose DRY_RUN or LIVE.']);
        }

        $key = $idempotencyKey ?: (string) Str::uuid();
        if (strlen($key) > 100) {
            throw ValidationException::withMessages(['idempotency_key' => 'The idempotency key may not exceed 100 characters.']);
        }

        return DB::transaction(function () use ($taskId, $mode, $userId, $key) {
            $existing = DB::table('marketplace_automation_executions')->where('idempotency_key', $key)->first();
            if ($existing) {
                if ((int) $existing->task_id !== $taskId || $existing->mode !== $mode) {
                    throw ValidationException::withMessages(['idempotency_key' => 'This idempotency key was already used for a different execution request.']);
                }

                return ['execution' => $existing, 'replayed' => true];
            }

            $task = DB::table('marketplace_automation_tasks')->where('id', $taskId)->lockForUpdate()->first();
            if (! $task) {
                throw ValidationException::withMessages(['task' => 'Automation task was not found.']);
            }

            $started = microtime(true);
            $executionId = DB::table('marketplace_automation_executions')->insertGetId([
                'task_id' => $taskId,
                'operation' => $task->recommendation_type,
                'mode' => $mode,
                'status' => 'STARTED',
                'idempotency_key' => $key,
                'request_payload' => json_encode(['task_id' => $taskId, 'mode' => $mode]),
                'initiated_by' => $userId,
                'started_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $policy = DB::table('marketplace_automation_execution_policies')
                ->where('recommendation_type', $task->recommendation_type)->first();

            $blockedReason = null;
            if (! in_array($task->recommendation_type, self::TYPES, true) || ! $policy) {
                $blockedReason = 'No execution policy exists for this recommendation type.';
            } elseif ($task->status !== 'APPROVED') {
                $blockedReason = 'Only APPROVED automation tasks can enter the execution workflow.';
            } elseif ($mode === 'LIVE') {
                $blockedReason = 'Live execution is disabled: this release contains no verified Amazon or Flipkart write adapter.';
            } elseif (! $policy->dry_run_enabled) {
                $blockedReason = 'Dry-run execution is disabled by the operation policy.';
            } elseif ($policy->requires_approval && $task->status !== 'APPROVED') {
                $blockedReason = 'This operation requires prior task approval.';
            }

            if ($blockedReason !== null) {
                $this->finish($executionId, 'BLOCKED', ['executed' => false], [$blockedReason], $blockedReason, $started);

                return ['execution' => DB::table('marketplace_automation_executions')->where('id', $executionId)->first(), 'replayed' => false];
            }

            $snapshot = $this->decodeJson($task->recommendation_snapshot ?? null);
            $metrics = $this->decodeJson($task->metrics ?? null);
            if (! is_array($snapshot) || ! is_array($metrics)) {
                $message = 'Task recommendation data is missing or invalid; no action was prepared.';
                $this->finish($executionId, 'FAILED', ['executed' => false], [$message], $message, $started);

                return ['execution' => DB::table('marketplace_automation_executions')->where('id', $executionId)->first(), 'replayed' => false];
            }

            $preview = [
                'task_id' => (int) $task->id,
                'product_id' => $task->product_id ? (int) $task->product_id : null,
                'recommendation_type' => $task->recommendation_type,
                'title' => $task->title,
                'proposed_action' => $task->suggested_action,
                'reason' => $task->reason,
                'metrics' => $metrics,
                'validation' => [
                    'task_approved' => true,
                    'operation_policy_found' => true,
                    'dry_run_enabled' => true,
                    'live_adapter_called' => false,
                ],
                'side_effects' => [
                    'marketplace_api_calls' => 0,
                    'inventory_changes' => 0,
                    'price_changes' => 0,
                    'listing_changes' => 0,
                    'order_or_finance_changes' => 0,
                ],
            ];

            $this->finish($executionId, 'SUCCEEDED', $preview, [], 'Dry-run preview completed. No external or operational changes were made.', $started);

            return ['execution' => DB::table('marketplace_automation_executions')->where('id', $executionId)->first(), 'replayed' => false];
        });
    }

    public function history(int $limit = 100)
    {
        return DB::table('marketplace_automation_executions as e')
            ->join('marketplace_automation_tasks as t', 't.id', '=', 'e.task_id')
            ->leftJoin('users as u', 'u.id', '=', 'e.initiated_by')
            ->select('e.*', 't.title as task_title', 't.status as task_status', 'u.name as initiated_by_name')
            ->orderByDesc('e.id')->limit(max(1, min($limit, 250)))->get();
    }

    private function finish(int $id, string $status, array $result, array $errors, string $message, float $started): void
    {
        DB::table('marketplace_automation_executions')->where('id', $id)->update([
            'status' => $status,
            'result_payload' => json_encode($result),
            'validation_errors' => json_encode($errors),
            'message' => $message,
            'finished_at' => now(),
            'duration_ms' => (int) round((microtime(true) - $started) * 1000),
            'updated_at' => now(),
        ]);
    }

    private function decodeJson(?string $value): ?array
    {
        if ($value === null || $value === '') {
            return null;
        }
        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : null;
    }
}
