<?php

namespace App\Http\Controllers;

use App\Models\AutomationTask;
use App\Models\User;

class AutomationGovernanceController extends Controller
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

    private function employeesQuery()
    {
        $query = User::query()->where('is_active', true);
        if ($this->businessId() !== null) {
            $query->where('business_id', $this->businessId());
        }
        return $query;
    }

    /** Package 87: compare due-date outcomes for tasks completed in the last 30 days. */
    public function dueDateCompliance()
    {
        $since = now()->subDays(30)->startOfDay();
        $completed = $this->tasksQuery()->where('status', 'COMPLETED')
            ->whereNotNull('completed_at')->where('completed_at', '>=', $since)
            ->whereNotNull('due_at')->with('assignee')->get();

        $onTime = $completed->filter(fn ($task) => $task->completed_at <= $task->due_at)->count();
        $late = $completed->count() - $onTime;
        $byPriority = $completed->groupBy(fn ($task) => strtoupper((string) ($task->priority ?: 'UNSET')))
            ->map(function ($items, $priority) {
                $good = $items->filter(fn ($task) => $task->completed_at <= $task->due_at)->count();
                return (object) ['priority' => $priority, 'total' => $items->count(), 'on_time' => $good, 'late' => $items->count() - $good];
            })->sortBy('priority')->values();

        return view('automation.due-date-compliance', [
            'total' => $completed->count(), 'onTime' => $onTime, 'late' => $late,
            'rate' => $completed->count() ? round($onTime / $completed->count() * 100, 1) : null,
            'byPriority' => $byPriority, 'since' => $since,
        ]);
    }

    /** Package 88: assignment hygiene checks for active tasks and inactive/missing assignees. */
    public function assignmentHealth()
    {
        $employees = $this->employeesQuery()->get()->keyBy('id');
        $tasks = $this->tasksQuery()->whereNotIn('status', ['COMPLETED', 'CANCELLED'])
            ->with('assignee')->orderBy('due_at')->get();

        $rows = $tasks->map(function ($task) use ($employees) {
            $assignee = $task->assigned_to ? $employees->get($task->assigned_to) : null;
            $issue = !$task->assigned_to ? 'UNASSIGNED' : (!$assignee ? 'ASSIGNEE INACTIVE OR OUTSIDE BUSINESS' : null);
            return $issue ? (object) ['task' => $task, 'issue' => $issue] : null;
        })->filter()->values();

        return view('automation.assignment-health', [
            'rows' => $rows, 'openCount' => $tasks->count(),
            'unassigned' => $rows->where('issue', 'UNASSIGNED')->count(),
            'invalidAssignee' => $rows->where('issue', '!=', 'UNASSIGNED')->count(),
        ]);
    }

    /** Package 89: surface urgency/priority mismatches for human review; no task data is changed. */
    public function priorityAlignment()
    {
        $now = now();
        $tasks = $this->tasksQuery()->whereNotIn('status', ['COMPLETED', 'CANCELLED'])
            ->whereNotNull('due_at')->with('assignee')->orderBy('due_at')->get();

        $rows = $tasks->map(function ($task) use ($now) {
            $hours = $now->diffInHours($task->due_at, false);
            $priority = strtoupper((string) ($task->priority ?: 'NORMAL'));
            $urgentPriority = in_array($priority, ['URGENT', 'CRITICAL', 'HIGH'], true);
            $dueSoon = $hours <= 48;
            if ($hours < 0 && !$urgentPriority) $reason = 'Overdue task without high/urgent priority';
            elseif ($dueSoon && !$urgentPriority) $reason = 'Due within 48 hours without high/urgent priority';
            elseif ($hours > 168 && $urgentPriority) $reason = 'High/urgent priority but due more than 7 days away';
            else return null;
            return (object) ['task' => $task, 'hours' => $hours, 'reason' => $reason];
        })->filter()->sortBy('hours')->values();

        return view('automation.priority-alignment', [
            'rows' => $rows,
            'overdue' => $rows->filter(fn ($row) => $row->hours < 0)->count(),
            'dueSoon' => $rows->filter(fn ($row) => $row->hours >= 0 && $row->hours <= 48)->count(),
            'lowUrgency' => $rows->filter(fn ($row) => str_contains($row->reason, 'more than 7 days'))->count(),
        ]);
    }
}
