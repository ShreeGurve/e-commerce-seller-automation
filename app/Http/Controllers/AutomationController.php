<?php

namespace App\Http\Controllers;

use App\Models\AutomationExecution;
use App\Models\AutomationLog;
use App\Models\AutomationRule;
use App\Models\AutomationRuleVersion;
use App\Services\AutomationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Throwable;

class AutomationController extends Controller
{
    public function index(Request $request)
    {
        $query = AutomationLog::with('rule')->latest();
        if ($request->filled('severity') && in_array($request->severity, ['critical','high','medium','low'], true)) {
            $query->where('severity', $request->severity);
        }
        if ($request->filled('q')) {
            $query->where(function ($q) use ($request) {
                $term = '%'.$request->q.'%';
                $q->where('title','like',$term)->orWhere('message','like',$term);
            });
        }

        $rules = AutomationRule::withCount('logs')->orderByDesc('is_active')->orderBy('name')->get();
        $versions = AutomationRuleVersion::with(['rule','actor'])->latest()->limit(40)->get();
        $executions = AutomationExecution::with('actor')->latest()->limit(30)->get();

        return view('automation.index', [
            'rules' => $rules,
            'logs' => $query->limit(100)->get(),
            'triggers' => AutomationRule::TRIGGERS,
            'alertCounts' => [
                'total' => AutomationLog::count(),
                'critical' => AutomationLog::where('severity','critical')->count(),
                'high' => AutomationLog::where('severity','high')->count(),
                'medium' => AutomationLog::where('severity','medium')->count(),
            ],
            'filters' => $request->only('severity','q'),
            'versions' => $versions,
            'executions' => $executions,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required','string','max:120'],
            'trigger_key' => ['required', Rule::in(array_keys(AutomationRule::TRIGGERS))],
            'threshold' => ['required','numeric','min:0','max:999999999'],
        ]);
        $data['created_by'] = $request->user()->id;
        $data['is_active'] = $request->boolean('is_active');
        $rule = AutomationRule::create($data);
        $this->snapshot($rule, $request, 'created', 'Rule created');
        return back()->with('success','Automation rule created and version 1 recorded.');
    }

    public function update(Request $request, AutomationRule $automationRule)
    {
        $data = $request->validate([
            'name' => ['required','string','max:120'],
            'trigger_key' => ['required', Rule::in(array_keys(AutomationRule::TRIGGERS))],
            'threshold' => ['required','numeric','min:0','max:999999999'],
        ]);
        $before = $automationRule->only(['name','trigger_key','threshold','is_active']);
        $automationRule->update($data);
        $this->snapshot($automationRule, $request, 'updated', 'Rule settings changed from '.json_encode($before));
        return back()->with('success','Automation rule updated; a new version was recorded.');
    }

    public function toggle(Request $request, AutomationRule $automationRule)
    {
        $automationRule->update(['is_active' => !$automationRule->is_active]);
        $this->snapshot($automationRule, $request, $automationRule->is_active ? 'enabled' : 'paused', 'Rule status changed');
        return back()->with('success','Rule status updated and version history recorded.');
    }

    public function destroy(Request $request, AutomationRule $automationRule)
    {
        $this->snapshot($automationRule, $request, 'deleted', 'Rule deleted');
        $automationRule->delete();
        return back()->with('success','Rule deleted; its final version remains in history.');
    }

    public function run(Request $request, AutomationService $service)
    {
        return $this->execute($request, $service, null);
    }

    public function retry(Request $request, AutomationExecution $execution, AutomationService $service)
    {
        abort_unless($execution->status === 'failed', 422, 'Only failed executions can be retried.');
        abort_unless($execution->scope === 'all_active_rules', 422, 'This execution scope cannot be retried.');
        return $this->execute($request, $service, $execution);
    }

    public function preview(Request $request, AutomationRule $automationRule, AutomationService $service)
    {
        $matches = $service->previewRule($automationRule);
        return view('automation.preview', [
            'rule' => $automationRule,
            'matches' => $matches,
            'previewedAt' => now(),
        ]);
    }

    private function execute(Request $request, AutomationService $service, ?AutomationExecution $retryOf)
    {
        $started = microtime(true);
        $execution = AutomationExecution::create([
            'scope' => 'all_active_rules',
            'status' => 'running',
            'started_at' => now(),
            'retry_of_id' => $retryOf?->id,
            'triggered_by' => $request->user()->id,
        ]);

        try {
            $result = $service->run($request->user());
            $execution->update([
                'status' => 'succeeded',
                'finished_at' => now(),
                'duration_ms' => (int) round((microtime(true) - $started) * 1000),
                'rules_count' => (int) ($result['rules'] ?? 0),
                'matched_count' => (int) ($result['matched'] ?? 0),
                'created_count' => (int) ($result['created'] ?? 0),
            ]);
            return back()->with('success', "Run complete: {$result['rules']} active rules, {$result['matched']} matches, {$result['created']} new alerts. Execution #{$execution->id} recorded.");
        } catch (Throwable $e) {
            $execution->update([
                'status' => 'failed',
                'finished_at' => now(),
                'duration_ms' => (int) round((microtime(true) - $started) * 1000),
                'error_message' => mb_substr($e->getMessage(), 0, 5000),
            ]);
            report($e);
            return back()->withErrors(['automation' => 'Automation run failed. Execution #'.$execution->id.' was recorded; review its error details before retrying.']);
        }
    }

    private function snapshot(AutomationRule $rule, Request $request, string $type, string $summary): void
    {
        $next = ((int) AutomationRuleVersion::where('automation_rule_id', $rule->id)->max('version_number')) + 1;
        AutomationRuleVersion::create([
            'automation_rule_id' => $rule->id,
            'version_number' => $next,
            'snapshot' => $rule->only(['name','trigger_key','threshold','is_active','created_by']),
            'change_type' => $type,
            'change_summary' => $summary,
            'changed_by' => $request->user()->id,
        ]);
    }
}
