<?php

namespace App\Http\Controllers;

use App\Models\AutomationTask;
use Illuminate\Support\Facades\DB;

class AutomationIntelligenceController extends Controller
{
    private function businessId(): ?int
    {
        $user = auth()->user();

        return $user && method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin()
            ? null
            : (int) ($user->business_id ?? 0);
    }

    private function tasksQuery()
    {
        $query = AutomationTask::query();
        if ($this->businessId() !== null) {
            $query->where('business_id', $this->businessId());
        }

        return $query;
    }

    /** Package 84: open-task age cohorts for operational aging review. */
    public function agingAnalysis()
    {
        $tasks = $this->tasksQuery()
            ->whereNotIn('status', ['COMPLETED', 'CANCELLED'])
            ->with('assignee')
            ->orderBy('created_at')
            ->get();

        $rows = $tasks->map(function ($task) {
            $age = $task->created_at ? (int) $task->created_at->diffInDays(now()) : 0;
            $band = $age >= 30 ? '30+ days' : ($age >= 14 ? '14–29 days' : ($age >= 7 ? '7–13 days' : '0–6 days'));

            return (object) ['task' => $task, 'age' => $age, 'band' => $band];
        })->sortByDesc('age')->values();

        $cohorts = collect(['0–6 days', '7–13 days', '14–29 days', '30+ days'])
            ->map(fn ($band) => (object) ['band' => $band, 'count' => $rows->where('band', $band)->count()]);

        return view('automation.aging-analysis', compact('rows', 'cohorts'));
    }

    /** Package 85: completed-task throughput, grouped by completion week and assignee. */
    public function throughputAnalysis()
    {
        $since = now()->subDays(55)->startOfDay();
        $completed = $this->tasksQuery()
            ->where('status', 'COMPLETED')
            ->whereNotNull('completed_at')
            ->where('completed_at', '>=', $since)
            ->with('assignee')
            ->orderBy('completed_at')
            ->get();

        $weekly = $completed->groupBy(fn ($task) => $task->completed_at->copy()->startOfWeek()->format('d M Y'))
            ->map(fn ($items, $week) => (object) ['week' => $week, 'count' => $items->count()])->values();
        $people = $completed->groupBy(fn ($task) => $task->assigned_to ?: 0)
            ->map(function ($items) {
                $first = $items->first();

                return (object) [
                    'name' => $first->assignee->name ?? 'Unassigned',
                    'count' => $items->count(),
                    'on_time' => $items->filter(fn ($task) => ! $task->due_at || $task->completed_at <= $task->due_at)->count(),
                ];
            })->sortByDesc('count')->values();

        return view('automation.throughput-analysis', [
            'weekly' => $weekly,
            'people' => $people,
            'total' => $completed->count(),
            'since' => $since,
        ]);
    }

    /** Package 86: identify high-impact prerequisites in the dependency graph. */
    public function dependencyImpact()
    {
        $tasks = $this->tasksQuery()->get()->keyBy('id');
        $taskIds = $tasks->keys();
        $dependencies = DB::table('automation_task_dependencies')
            ->whereIn('predecessor_task_id', $taskIds)
            ->whereIn('dependent_task_id', $taskIds)
            ->get();

        $impact = $dependencies->groupBy('predecessor_task_id')->map(function ($links, $id) use ($tasks) {
            $task = $tasks->get($id);
            $dependents = $links->map(fn ($link) => $tasks->get($link->dependent_task_id))->filter();
            $openDependents = $dependents->filter(fn ($dependent) => ! in_array($dependent->status, ['COMPLETED', 'CANCELLED'], true));
            $blockedDependents = $openDependents->filter(fn ($dependent) => $dependent->status !== 'COMPLETED');

            return (object) [
                'task' => $task,
                'dependent_count' => $dependents->count(),
                'open_count' => $openDependents->count(),
                'blocked_count' => $blockedDependents->count(),
            ];
        })->filter(fn ($row) => $row->task && $row->dependent_count > 0)
            ->sortByDesc(fn ($row) => ($row->blocked_count * 3) + $row->open_count)->values();

        return view('automation.dependency-impact', [
            'rows' => $impact,
            'links' => $dependencies->count(),
            'critical' => $impact->where('blocked_count', '>', 0)->count(),
        ]);
    }
}
