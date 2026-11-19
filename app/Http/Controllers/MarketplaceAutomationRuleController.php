<?php

namespace App\Http\Controllers;

use App\Services\MarketplaceAutomationRuleService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MarketplaceAutomationRuleController extends Controller
{
    public function index()
    {
        return view('automation.marketplace-rules', [
            'rules' => DB::table('marketplace_automation_rules')->orderBy('recommendation_type')->get(),
            'tasks' => DB::table('marketplace_automation_tasks')->orderByRaw("CASE priority WHEN 'HIGH' THEN 0 WHEN 'MEDIUM' THEN 1 ELSE 2 END")
                ->orderByDesc('created_at')->limit(100)->get(),
            'types' => MarketplaceAutomationRuleService::TYPES,
        ]);
    }

    public function store(Request $request, MarketplaceAutomationRuleService $service)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'recommendation_type' => ['required', 'string', 'in:RESTOCK,STOCK_RISK,SALES_DECLINE,HIGH_RETURN_RATE,LOW_PROFIT_MARGIN'],
            'priority_override' => ['nullable', 'in:HIGH,MEDIUM,LOW'],
            'cooldown_hours' => ['required', 'integer', 'between:1,720'],
            'action_guidance' => ['nullable', 'string', 'max:500'],
        ]);
        $service->createRule($data, $request->user()?->id);
        return back()->with('success', 'Automation rule created.');
    }

    public function toggle(Request $request, int $rule, MarketplaceAutomationRuleService $service)
    {
        $data = $request->validate(['is_enabled' => ['required', 'boolean']]);
        $service->setRuleEnabled($rule, (bool) $data['is_enabled']);
        return back()->with('success', 'Automation rule updated.');
    }

    public function generate(Request $request, MarketplaceAutomationRuleService $service)
    {
        $filters = $request->validate(['days' => ['nullable', 'integer', 'in:7,14,30,60,90']]);
        $result = $service->generate($filters);
        return back()->with('success', "Scan complete: {$result['created']} task(s) created, {$result['existing']} already existed.");
    }

    public function review(Request $request, int $task, MarketplaceAutomationRuleService $service)
    {
        $data = $request->validate([
            'decision' => ['required', 'in:APPROVED,REJECTED,COMPLETED'],
            'review_note' => ['nullable', 'string', 'max:2000'],
        ]);
        $service->reviewTask($task, $data['decision'], $request->user()?->id, $data['review_note'] ?? null);
        return back()->with('success', 'Task status updated. No marketplace or inventory action was executed.');
    }
}
