<?php

namespace Tests\Feature;

use App\Services\MarketplaceAutomationRuleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class MarketplaceAutomationRuleServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_migration_installs_default_enabled_rules(): void
    {
        $this->assertDatabaseHas('marketplace_automation_rules', ['recommendation_type' => 'RESTOCK', 'is_enabled' => 1]);
        $this->assertDatabaseHas('marketplace_automation_rules', ['recommendation_type' => 'LOW_PROFIT_MARGIN', 'is_enabled' => 1]);
    }

    public function test_custom_rule_can_be_created_and_disabled(): void
    {
        $service = app(MarketplaceAutomationRuleService::class);
        $id = $service->createRule(['name' => 'My stock rule', 'recommendation_type' => 'RESTOCK', 'cooldown_hours' => 12]);
        $service->setRuleEnabled($id, false);
        $this->assertDatabaseHas('marketplace_automation_rules', ['id' => $id, 'is_enabled' => 0]);
    }

    public function test_unsupported_recommendation_type_is_rejected(): void
    {
        $this->expectException(ValidationException::class);
        app(MarketplaceAutomationRuleService::class)->createRule(['name' => 'Bad', 'recommendation_type' => 'AUTO_PRICE', 'cooldown_hours' => 24]);
    }

    public function test_task_requires_pending_to_approved_then_approved_to_completed(): void
    {
        $ruleId = DB::table('marketplace_automation_rules')->value('id');
        $taskId = DB::table('marketplace_automation_tasks')->insertGetId([
            'rule_id' => $ruleId, 'recommendation_type' => 'RESTOCK', 'priority' => 'HIGH',
            'title' => 'Review stock', 'reason' => 'Low stock', 'suggested_action' => 'Review replenishment',
            'dedupe_key' => hash('sha256', 'test-task'), 'status' => 'PENDING',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $service = app(MarketplaceAutomationRuleService::class);
        $service->reviewTask($taskId, 'APPROVED', null, 'Reviewed');
        $this->assertDatabaseHas('marketplace_automation_tasks', ['id' => $taskId, 'status' => 'APPROVED']);
        $service->reviewTask($taskId, 'COMPLETED', null, 'Work completed manually');
        $this->assertDatabaseHas('marketplace_automation_tasks', ['id' => $taskId, 'status' => 'COMPLETED']);
    }

    public function test_pending_task_cannot_be_marked_completed_directly(): void
    {
        $ruleId = DB::table('marketplace_automation_rules')->value('id');
        $taskId = DB::table('marketplace_automation_tasks')->insertGetId([
            'rule_id' => $ruleId, 'recommendation_type' => 'RESTOCK', 'priority' => 'HIGH',
            'title' => 'Review stock', 'reason' => 'Low stock', 'suggested_action' => 'Review replenishment',
            'dedupe_key' => hash('sha256', 'test-task-2'), 'status' => 'PENDING',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->expectException(ValidationException::class);
        app(MarketplaceAutomationRuleService::class)->reviewTask($taskId, 'COMPLETED', null);
    }
}
