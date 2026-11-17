<?php

namespace App\Http\Controllers;

use App\Models\AutomationTask;
use App\Models\AutomationTaskDependency;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AutomationInsightsController extends Controller
{
    private function tasks(Request $request)
    {
        return AutomationTask::query()->when(!$request->user()->isSuperAdmin(), fn ($q) => $q->where('business_id', $request->user()->business_id));
    }

    private function assertTask(Request $request, AutomationTask $task): void
    {
        if (!$request->user()->isSuperAdmin()) abort_unless($task->business_id == $request->user()->business_id, 404);
    }

    public function productivity(Request $request)
    {
        $users = User::query()->when(!$request->user()->isSuperAdmin(), fn ($q) => $q->where('business_id', $request->user()->business_id))
            ->where('is_active', true)->orderBy('name')->get(['id', 'name']);
        $base = $this->tasks($request);
        $rows = $users->map(function ($user) use ($base) {
            $q = clone $base;
            $assigned = (clone $q)->where('assigned_to', $user->id);
            $completed = (clone $assigned)->where('status', 'COMPLETED');
            $completed30 = (clone $completed)->where('completed_at', '>=', now()->subDays(30));
            $created30 = (clone $assigned)->where('created_at', '>=', now()->subDays(30))->count();
            $done30 = (clone $completed30)->count();
            return [
                'user' => $user,
                'open' => (clone $assigned)->where('status', 'OPEN')->count(),
                'overdue' => (clone $assigned)->where('status', 'OPEN')->whereNotNull('due_at')->where('due_at', '<', now())->count(),
                'completed_30d' => $done30,
                'created_30d' => $created30,
                'completion_rate' => $created30 ? round($done30 / $created30 * 100, 1) : null,
                'avg_hours' => (clone $completed30)->whereNotNull('created_at')->selectRaw('AVG(TIMESTAMPDIFF(HOUR, created_at, completed_at)) AS avg_hours')->value('avg_hours'),
            ];
        });
        return view('automation.productivity', compact('rows'));
    }

    public function dependencies(Request $request)
    {
        $tasks = $this->tasks($request)->with('assignee')->orderByDesc('created_at')->limit(250)->get();
        $taskIds = $tasks->pluck('id');
        $dependencies = AutomationTaskDependency::with(['predecessor.assignee', 'dependent.assignee'])
            ->whereIn('predecessor_task_id', $taskIds)->whereIn('dependent_task_id', $taskIds)
            ->latest()->paginate(25);
        return view('automation.dependencies', compact('tasks', 'dependencies'));
    }

    public function storeDependency(Request $request)
    {
        $data = $request->validate([
            'predecessor_task_id' => ['required', 'integer', 'different:dependent_task_id', 'exists:automation_tasks,id'],
            'dependent_task_id' => ['required', 'integer', 'exists:automation_tasks,id'],
        ]);
        $predecessor = $this->tasks($request)->findOrFail($data['predecessor_task_id']);
        $dependent = $this->tasks($request)->findOrFail($data['dependent_task_id']);
        abort_if($predecessor->id === $dependent->id, 422, 'A task cannot depend on itself.');
        // Prevent direct and indirect cycles: adding A -> B is invalid if B already reaches A.
        $frontier = [$dependent->id];
        $visited = [];
        while ($frontier) {
            $current = array_pop($frontier);
            if ($current == $predecessor->id) abort(422, 'This dependency would create a cycle.');
            if (isset($visited[$current])) continue;
            $visited[$current] = true;
            $next = AutomationTaskDependency::where('predecessor_task_id', $current)->pluck('dependent_task_id')->all();
            foreach ($next as $id) if (!isset($visited[$id])) $frontier[] = $id;
        }
        AutomationTaskDependency::firstOrCreate([
            'predecessor_task_id' => $predecessor->id,
            'dependent_task_id' => $dependent->id,
        ], ['created_by' => $request->user()->id]);
        return back()->with('success', 'Task dependency saved. The dependent task is blocked until its prerequisite is completed.');
    }

    public function destroyDependency(Request $request, AutomationTaskDependency $dependency)
    {
        $predecessor = $this->tasks($request)->find($dependency->predecessor_task_id);
        $dependent = $this->tasks($request)->find($dependency->dependent_task_id);
        abort_unless($predecessor && $dependent, 404);
        $dependency->delete();
        return back()->with('success', 'Task dependency removed.');
    }

    public function analytics(Request $request)
    {
        $days = (int) $request->query('days', 30);
        abort_unless(in_array($days, [7, 14, 30, 60, 90], true), 422, 'Invalid reporting period.');
        $base = $this->tasks($request);
        $start = now()->subDays($days)->startOfDay();
        $stats = [
            'created' => (clone $base)->where('created_at', '>=', $start)->count(),
            'completed' => (clone $base)->where('status', 'COMPLETED')->where('completed_at', '>=', $start)->count(),
            'open' => (clone $base)->where('status', 'OPEN')->count(),
            'overdue' => (clone $base)->where('status', 'OPEN')->whereNotNull('due_at')->where('due_at', '<', now())->count(),
            'avg_hours' => (clone $base)->where('status', 'COMPLETED')->where('completed_at', '>=', $start)->whereNotNull('created_at')->selectRaw('AVG(TIMESTAMPDIFF(HOUR, created_at, completed_at)) AS avg_hours')->value('avg_hours'),
            'on_time' => (clone $base)->where('status', 'COMPLETED')->where('completed_at', '>=', $start)->whereNotNull('due_at')->whereColumn('completed_at', '<=', 'due_at')->count(),
        ];
        $stats['completion_rate'] = $stats['created'] ? round($stats['completed'] / $stats['created'] * 100, 1) : null;
        $daily = (clone $base)->where('created_at', '>=', $start)
            ->selectRaw('DATE(created_at) AS day, COUNT(*) AS created_count')
            ->groupBy('day')->orderBy('day')->get();
        $completionDaily = (clone $base)->where('status', 'COMPLETED')->where('completed_at', '>=', $start)
            ->selectRaw('DATE(completed_at) AS day, COUNT(*) AS completed_count')
            ->groupBy('day')->orderBy('day')->get()->keyBy('day');
        return view('automation.advanced-analytics', compact('stats', 'daily', 'completionDaily', 'days'));
    }
}
