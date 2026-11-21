<?php

namespace App\Http\Controllers;

use App\Models\AutomationTask;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AutomationPlanningController extends Controller
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

    private function activeEmployees()
    {
        $query = User::query()->where('is_active', true);
        if ($this->businessId() !== null) {
            $query->where('business_id', $this->businessId());
        }

        return $query;
    }

    public function recommendations()
    {
        $employees = $this->activeEmployees()->get();
        $tasks = $this->tasksQuery()->whereNotIn('status', ['COMPLETED', 'CANCELLED'])->get();
        $rows = $employees->map(function ($employee) use ($tasks) {
            $assigned = $tasks->where('assigned_to', $employee->id);
            $open = $assigned->count();
            $overdue = $assigned->filter(fn ($task) => $task->due_at && $task->due_at->isPast())->count();
            $urgent = $assigned->whereIn('priority', ['URGENT', 'HIGH'])->count();

            return (object) ['employee' => $employee, 'open' => $open, 'overdue' => $overdue, 'urgent' => $urgent, 'score' => round($open + ($overdue * 2) + ($urgent * 1.5), 1)];
        })->sortByDesc('score')->values();

        return view('automation.workload-recommendations', ['rows' => $rows, 'unassigned' => $tasks->whereNull('assigned_to')->count()]);
    }

    public function bottlenecks()
    {
        $allTasks = $this->tasksQuery()->get();
        $tasks = $allTasks->whereNotIn('status', ['COMPLETED', 'CANCELLED'])->values()->load('assignee');
        $dependencies = DB::table('automation_task_dependencies')->get()->whereIn('dependent_task_id', $tasks->pluck('id'))->groupBy('dependent_task_id');
        $dependentCounts = DB::table('automation_task_dependencies')->select('predecessor_task_id', DB::raw('COUNT(*) as total'))->whereIn('predecessor_task_id', $tasks->pluck('id'))->groupBy('predecessor_task_id')->pluck('total', 'predecessor_task_id');
        $taskStatuses = $allTasks->keyBy('id');
        $rows = $tasks->map(function ($task) use ($dependencies, $dependentCounts, $taskStatuses) {
            $age = $task->created_at ? (int) $task->created_at->diffInDays(now()) : 0;
            $prereqs = $dependencies->get($task->id, collect());
            $blocked = $prereqs->contains(function ($dependency) use ($taskStatuses) {
                $prerequisite = $taskStatuses->get($dependency->predecessor_task_id);

                return $prerequisite && $prerequisite->status !== 'COMPLETED';
            });
            $dependents = (int) ($dependentCounts[$task->id] ?? 0);
            $reasons = [];
            if ($blocked) {
                $reasons[] = 'Waiting on prerequisite';
            }
            if ($task->due_at && $task->due_at->isPast()) {
                $reasons[] = 'Overdue';
            }
            if ($age >= 7) {
                $reasons[] = 'Open for '.$age.' days';
            }
            if ($dependents >= 2) {
                $reasons[] = 'Blocks '.$dependents.' tasks';
            }

            return (object) ['task' => $task, 'age' => $age, 'dependents' => $dependents, 'reasons' => $reasons, 'score' => ($blocked ? 4 : 0) + ($task->due_at && $task->due_at->isPast() ? 3 : 0) + ($age >= 7 ? min(3, $age / 7) : 0) + min(3, $dependents)];
        })->filter(fn ($row) => count($row->reasons) > 0)->sortByDesc('score')->values();

        return view('automation.bottlenecks', compact('rows'));
    }

    public function capacity()
    {
        $employees = $this->activeEmployees()->get();
        $tasks = $this->tasksQuery()->whereNotIn('status', ['COMPLETED', 'CANCELLED'])->get();
        $capacityQuery = DB::table('automation_employee_capacities');
        if ($this->businessId() !== null) {
            $capacityQuery->where('business_id', $this->businessId());
        }
        $capacities = $capacityQuery->pluck('weekly_capacity_hours', 'user_id');
        $rows = $employees->map(function ($employee) use ($tasks, $capacities) {
            $assigned = $tasks->where('assigned_to', $employee->id);
            $hours = (float) $assigned->sum(fn ($task) => (float) ($task->effort_hours ?? 0));
            $capacity = (float) ($capacities[$employee->id] ?? 40);

            return (object) ['employee' => $employee, 'hours' => $hours, 'capacity' => $capacity, 'utilization' => $capacity > 0 ? round(($hours / $capacity) * 100, 1) : 0, 'task_count' => $assigned->count()];
        });

        return view('automation.capacity-planning', ['rows' => $rows, 'tasks' => $tasks]);
    }

    public function saveEffort(Request $request)
    {
        $request->validate(['task_id' => ['required', 'integer'], 'effort_hours' => ['nullable', 'numeric', 'min:0', 'max:10000']]);
        $task = $this->tasksQuery()->whereKey($request->integer('task_id'))->firstOrFail();
        $task->effort_hours = $request->input('effort_hours');
        $task->save();

        return back()->with('success', 'Task effort estimate updated.');
    }

    public function saveCapacity(Request $request)
    {
        $request->validate(['user_id' => ['required', 'integer', Rule::exists('users', 'id')], 'weekly_capacity_hours' => ['required', 'numeric', 'min:1', 'max:168']]);
        $employee = $this->activeEmployees()->whereKey($request->integer('user_id'))->firstOrFail();
        DB::table('automation_employee_capacities')->updateOrInsert(
            ['business_id' => $employee->business_id, 'user_id' => $employee->id],
            ['weekly_capacity_hours' => $request->input('weekly_capacity_hours'), 'updated_at' => now(), 'created_at' => now()]
        );

        return back()->with('success', 'Weekly capacity updated.');
    }
}
