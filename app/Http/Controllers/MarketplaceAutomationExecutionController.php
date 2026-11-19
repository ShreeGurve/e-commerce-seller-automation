<?php

namespace App\Http\Controllers;

use App\Services\MarketplaceAutomationExecutionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MarketplaceAutomationExecutionController extends Controller
{
    public function index(MarketplaceAutomationExecutionService $service)
    {
        return view('automation.marketplace-executions', [
            'tasks' => DB::table('marketplace_automation_tasks')
                ->where('status', 'APPROVED')->orderByDesc('updated_at')->limit(100)->get(),
            'executions' => $service->history(100),
            'policies' => DB::table('marketplace_automation_execution_policies')->orderBy('recommendation_type')->get(),
        ]);
    }

    public function dryRun(Request $request, int $task, MarketplaceAutomationExecutionService $service)
    {
        $data = $request->validate(['idempotency_key' => ['nullable', 'string', 'max:100']]);
        $result = $service->execute($task, 'DRY_RUN', $request->user()?->id, $data['idempotency_key'] ?? null);
        $status = $result['execution']->status;
        return back()->with($status === 'SUCCEEDED' ? 'success' : 'warning', $result['execution']->message ?: 'Execution preview recorded.');
    }

    public function live(Request $request, int $task, MarketplaceAutomationExecutionService $service)
    {
        $data = $request->validate(['confirm_live_block' => ['required', 'accepted']]);
        $result = $service->execute($task, 'LIVE', $request->user()?->id, $request->header('Idempotency-Key'));
        return back()->with('warning', $result['execution']->message ?: 'Live execution was blocked.');
    }
}
