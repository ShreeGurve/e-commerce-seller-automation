<?php

namespace App\Http\Controllers;

use App\Models\AutomationTask;
use App\Models\AutomationTaskEscalation;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AutomationWorkforceController extends Controller
{
    public function escalationIndex(Request $request)
    {
        $tasks = $this->tasks($request)->with(['assignee','creator','escalations.toUser'])
            ->where('status', 'OPEN')->whereNotNull('due_at')->where('due_at', '<', now())
            ->orderBy('due_at')->paginate(25)->withQueryString();
        $users = $this->users($request);
        return view('automation.escalations', compact('tasks', 'users'));
    }

    public function escalate(Request $request, AutomationTask $task)
    {
        $this->assertTask($request, $task);
        abort_unless($task->status === 'OPEN', 422, 'Only open tasks can be escalated.');
        $data = $request->validate([
            'to_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'reason' => ['required', 'string', 'max:500'],
        ]);
        $this->assertUser($request, $data['to_user_id'] ?? null);
        DB::transaction(function () use ($request, $task, $data) {
            AutomationTaskEscalation::create([
                'task_id' => $task->id,
                'from_user_id' => $task->assigned_to,
                'to_user_id' => $data['to_user_id'] ?? null,
                'escalated_by' => $request->user()->id,
                'reason' => $data['reason'],
                'created_at' => now(),
            ]);
            $task->update(['escalated_at' => now()]);
        });
        return back()->with('success', 'Escalation recorded. The task assignee was not changed.');
    }

    public function workloadIndex(Request $request)
    {
        $base = $this->tasks($request)->where('status', 'OPEN');
        $tasks = (clone $base)->with('assignee')->orderBy('due_at')->paginate(25)->withQueryString();
        $users = $this->users($request);
        $workload = $users->map(function ($user) use ($base) {
            $q = clone $base;
            $assigned = (clone $q)->where('assigned_to', $user->id);
            return [
                'user' => $user,
                'open' => (clone $assigned)->count(),
                'overdue' => (clone $assigned)->whereNotNull('due_at')->where('due_at', '<', now())->count(),
                'due_today' => (clone $assigned)->whereDate('due_at', today())->count(),
                'urgent' => (clone $assigned)->whereIn('priority', ['urgent','high'])->count(),
            ];
        });
        $unassigned = (clone $base)->whereNull('assigned_to')->count();
        return view('automation.workload', compact('tasks', 'workload', 'unassigned'));
    }

    public function reassign(Request $request, AutomationTask $task)
    {
        $this->assertTask($request, $task);
        abort_unless($task->status === 'OPEN', 422, 'Only open tasks can be reassigned.');
        $data = $request->validate(['assigned_to' => ['required','integer','exists:users,id']]);
        $this->assertUser($request, $data['assigned_to']);
        $old = $task->assigned_to;
        $task->update(['assigned_to' => $data['assigned_to']]);
        AutomationTaskEscalation::create([
            'task_id' => $task->id, 'from_user_id' => $old, 'to_user_id' => $data['assigned_to'],
            'escalated_by' => $request->user()->id, 'reason' => 'Manual workload rebalance', 'created_at' => now(),
        ]);
        return back()->with('success', 'Task reassigned and recorded in the audit history.');
    }

    public function performanceIndex(Request $request)
    {
        $base = $this->tasks($request);
        $start = now()->subDays(30)->startOfDay();
        $created = (clone $base)->where('created_at', '>=', $start)->count();
        $completed = (clone $base)->where('status', 'COMPLETED')->where('completed_at', '>=', $start)->count();
        $stats = [
            'open' => (clone $base)->where('status', 'OPEN')->count(),
            'overdue' => (clone $base)->where('status', 'OPEN')->whereNotNull('due_at')->where('due_at', '<', now())->count(),
            'created_30d' => $created,
            'completed_30d' => $completed,
            'completion_rate' => $created ? round($completed / $created * 100, 1) : null,
            'escalated_30d' => AutomationTaskEscalation::whereHas('task', fn ($q) => $this->scope($q, $request))->where('created_at', '>=', $start)->count(),
            'avg_completion_hours' => (clone $base)->where('status','COMPLETED')->where('completed_at','>=',$start)->whereNotNull('created_at')->selectRaw('AVG(TIMESTAMPDIFF(HOUR, created_at, completed_at)) as avg_hours')->value('avg_hours'),
        ];
        $daily = (clone $base)->where('created_at', '>=', now()->subDays(13)->startOfDay())->selectRaw('DATE(created_at) as day, COUNT(*) as created_count')->groupBy('day')->orderBy('day')->get();
        $recent = (clone $base)->with(['assignee','creator'])->latest()->limit(15)->get();
        return view('automation.performance', compact('stats', 'daily', 'recent'));
    }

    private function tasks(Request $request)
    {
        return $this->scope(AutomationTask::query(), $request);
    }

    private function scope($query, Request $request)
    {
        if (!$request->user()->isSuperAdmin()) $query->where('business_id', $request->user()->business_id);
        return $query;
    }

    private function users(Request $request)
    {
        return User::query()->when(!$request->user()->isSuperAdmin(), fn ($q) => $q->where('business_id', $request->user()->business_id))->orderBy('name')->get(['id','name']);
    }

    private function assertTask(Request $request, AutomationTask $task): void
    {
        if (!$request->user()->isSuperAdmin()) abort_unless($task->business_id == $request->user()->business_id, 404);
    }

    private function assertUser(Request $request, ?int $id): void
    {
        if (!$id || $request->user()->isSuperAdmin()) return;
        abort_unless(User::where('id', $id)->where('business_id', $request->user()->business_id)->exists(), 422, 'User must belong to your business.');
    }
}
