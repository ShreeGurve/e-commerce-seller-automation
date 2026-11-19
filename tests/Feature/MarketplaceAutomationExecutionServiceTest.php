<?php

namespace Tests\Feature;

use App\Services\MarketplaceAutomationExecutionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class MarketplaceAutomationExecutionServiceTest extends TestCase
{
    use RefreshDatabase;

    private function approvedTask(string $type = 'RESTOCK', array $overrides = []): int
    {
        $ruleId = DB::table('marketplace_automation_rules')->where('recommendation_type', $type)->value('id');
        return (int) DB::table('marketplace_automation_tasks')->insertGetId(array_merge([
            'rule_id' => $ruleId,
            'recommendation_type' => $type,
            'priority' => 'HIGH',
            'title' => 'Execution test task',
            'reason' => 'Low stock requires review',
            'suggested_action' => 'Review replenishment quantity',
            'metrics' => json_encode(['available' => 2, 'minimum' => 5]),
            'recommendation_snapshot' => json_encode(['type' => $type, 'product_id' => 99]),
            'dedupe_key' => hash('sha256', uniqid('exec-task-', true)),
            'status' => 'APPROVED',
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));
    }

    public function test_approved_task_dry_run_records_preview_without_changing_task_or_inventory(): void
    {
        $taskId = $this->approvedTask();
        $result = app(MarketplaceAutomationExecutionService::class)->execute($taskId, 'DRY_RUN', null, 'dry-run-test-1');

        $this->assertSame('SUCCEEDED', $result['execution']->status);
        $this->assertSame('DRY_RUN', $result['execution']->mode);
        $this->assertDatabaseHas('marketplace_automation_tasks', ['id' => $taskId, 'status' => 'APPROVED']);
        $payload = json_decode($result['execution']->result_payload, true);
        $this->assertSame(0, $payload['side_effects']['marketplace_api_calls']);
        $this->assertSame(0, $payload['side_effects']['inventory_changes']);
    }

    public function test_pending_task_is_blocked_from_execution(): void
    {
        $taskId = $this->approvedTask('RESTOCK', ['status' => 'PENDING']);
        $result = app(MarketplaceAutomationExecutionService::class)->execute($taskId, 'DRY_RUN', null, 'pending-test-1');
        $this->assertSame('BLOCKED', $result['execution']->status);
        $this->assertStringContainsString('APPROVED', $result['execution']->message);
    }

    public function test_live_execution_is_always_blocked_in_this_release(): void
    {
        $taskId = $this->approvedTask();
        $result = app(MarketplaceAutomationExecutionService::class)->execute($taskId, 'LIVE', null, 'live-test-1');
        $this->assertSame('BLOCKED', $result['execution']->status);
        $this->assertStringContainsString('no verified Amazon or Flipkart write adapter', $result['execution']->message);
    }

    public function test_idempotency_key_replays_existing_execution(): void
    {
        $taskId = $this->approvedTask();
        $service = app(MarketplaceAutomationExecutionService::class);
        $first = $service->execute($taskId, 'DRY_RUN', null, 'repeatable-key-1');
        $second = $service->execute($taskId, 'DRY_RUN', null, 'repeatable-key-1');
        $this->assertSame($first['execution']->id, $second['execution']->id);
        $this->assertTrue($second['replayed']);
        $this->assertDatabaseCount('marketplace_automation_executions', 1);
    }

    public function test_invalid_mode_is_rejected(): void
    {
        $taskId = $this->approvedTask();
        $this->expectException(ValidationException::class);
        app(MarketplaceAutomationExecutionService::class)->execute($taskId, 'AUTO', null, 'invalid-mode-1');
    }

    public function test_missing_recommendation_snapshot_fails_safely(): void
    {
        $taskId = $this->approvedTask('RESTOCK', ['recommendation_snapshot' => null]);
        $result = app(MarketplaceAutomationExecutionService::class)->execute($taskId, 'DRY_RUN', null, 'missing-snapshot-1');
        $this->assertSame('FAILED', $result['execution']->status);
        $this->assertDatabaseHas('marketplace_automation_tasks', ['id' => $taskId, 'status' => 'APPROVED']);
    }
}
