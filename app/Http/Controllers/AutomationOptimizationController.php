<?php

namespace App\Http\Controllers;

use App\Models\AutomationTask;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AutomationOptimizationController extends Controller
{
    private function businessId(): ?int
    {
        $user = auth()->user();

        return $user && method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin() ? null : (int) $user->business_id;
    }

    private function tasksQuery()
    {
        $query = AutomationTask::query();
        if ($this->businessId() !== null) {
            $query->where('business_id', $this->businessId());
        }

        return $query;
    }

    private function employeesQuery()
    {
        $query = User::query()->where('is_active', true);
        if ($this->businessId() !== null) {
            $query->where('business_id', $this->businessId());
        }

        return $query;
    }

    /** Package 81: transparent, rule-based deadline risk queue; not an ML prediction. */
    public function deadlineRisk()
    {
        $now = now();
        $tasks = $this->tasksQuery()->whereNotIn('status', ['COMPLETED', 'CANCELLED'])
            ->whereNotNull('due_at')->with('assignee')->orderBy('due_at')->get();
        $rows = $tasks->map(function ($task) use ($now) {
            $hours = $now->diffInHours($task->due_at, false);
            $risk = $hours < 0 ? 'OVERDUE' : ($hours <= 24 ? 'CRITICAL' : ($hours <= 72 ? 'HIGH' : ($hours <= 168 ? 'WATCH' : 'LOW')));

            return (object) ['task' => $task, 'hours' => $hours, 'risk' => $risk];
        })->filter(fn ($row) => $row->risk !== 'LOW')->sortBy('hours')->values();

        return view('automation.deadline-risk', compact('rows'));
    }

    /** Package 82: show dependents whose prerequisites are all complete. */
    public function dependencyRelease()
    {
        $tasks = $this->tasksQuery()->whereNotIn('status', ['COMPLETED', 'CANCELLED'])->with('assignee')->get();
        $ids = $tasks->pluck('id');
        $deps = DB::table('automation_task_dependencies')->whereIn('dependent_task_id', $ids)->get()->groupBy('dependent_task_id');
        $all = $this->tasksQuery()->get()->keyBy('id');
        $rows = $tasks->map(function ($task) use ($deps, $all) {
            $required = $deps->get($task->id, collect());
            if ($required->isEmpty()) {
                return null;
            }
            $incomplete = $required->filter(function ($dep) use ($all) {
                $pre = $all->get($dep->predecessor_task_id);

                return ! $pre || $pre->status !== 'COMPLETED';
            });

            return (object) ['task' => $task, 'required' => $required->count(), 'waiting' => $incomplete->count(), 'ready' => $incomplete->isEmpty()];
        })->filter()->sortBy(fn ($row) => $row->ready ? 0 : 1)->values();

        return view('automation.dependency-release', compact('rows'));
    }

    /** Package 83: advisory workload rebalance based on open effort and weekly capacity. */
    public function rebalanceSimulator()
    {
        $employees = $this->employeesQuery()->orderBy('name')->get();
        $tasks = $this->tasksQuery()->whereNotIn('status', ['COMPLETED', 'CANCELLED'])
            ->whereNotNull('assigned_to')->whereNotNull('effort_hours')->orderByDesc('priority')->orderBy('due_at')->get();
        $capacityQuery = DB::table('automation_employee_capacities');
        if ($this->businessId() !== null) {
            $capacityQuery->where('business_id', $this->businessId());
        }
        $capacity = $capacityQuery->pluck('weekly_capacity_hours', 'user_id');
        $loads = [];
        foreach ($employees as $employee) {
            $loads[$employee->id] = ['employee' => $employee, 'hours' => 0.0, 'capacity' => (float) ($capacity[$employee->id] ?? 40)];
        }
        foreach ($tasks as $task) {
            if (isset($loads[$task->assigned_to])) {
                $loads[$task->assigned_to]['hours'] += (float) $task->effort_hours;
            }
        }
        $recommendations = [];
        foreach ($tasks as $task) {
            $current = $loads[$task->assigned_to] ?? null;
            if (! $current || $current['capacity'] <= 0 || $current['hours'] <= $current['capacity']) {
                continue;
            }
            $candidates = collect($loads)->filter(fn ($load, $id) => (int) $id !== (int) $task->assigned_to && $load['capacity'] > 0 && ($load['hours'] + (float) $task->effort_hours) <= $load['capacity'])
                ->sortBy(fn ($load) => $load['hours'] / $load['capacity']);
            $target = $candidates->first();
            if ($target) {
                $recommendations[] = (object) ['task' => $task, 'from' => $current['employee'], 'to' => $target['employee'], 'effort' => (float) $task->effort_hours];
                $loads[$task->assigned_to]['hours'] -= (float) $task->effort_hours;
                $loads[$target['employee']->id]['hours'] += (float) $task->effort_hours;
            }
        }
        $summary = collect($loads)->map(fn ($load) => (object) ['employee' => $load['employee'], 'hours' => round($load['hours'], 1), 'capacity' => $load['capacity'], 'utilization' => $load['capacity'] > 0 ? round($load['hours'] / $load['capacity'] * 100, 1) : 0])->values();

        return view('automation.rebalance-simulator', ['recommendations' => collect($recommendations), 'summary' => $summary]);
    }
}
