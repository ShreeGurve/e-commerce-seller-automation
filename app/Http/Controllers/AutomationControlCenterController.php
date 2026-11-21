<?php

namespace App\Http\Controllers;

use App\Models\AutomationTask;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AutomationControlCenterController extends Controller
{
    private function businessId(): ?int
    {
        $user = auth()->user();

        return $user && method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin()
            ? null : (int) ($user->business_id ?? 0);
    }

    private function tasksQuery()
    {
        $query = AutomationTask::query();
        if ($this->businessId() !== null) {
            $query->where('business_id', $this->businessId());
        }

        return $query;
    }

    /** Package 90: consolidated, read-only automation governance summary. */
    public function overview()
    {
        $tasks = $this->tasksQuery();
        $open = (clone $tasks)->whereNotIn('status', ['COMPLETED', 'CANCELLED']);
        $now = now();
        $openCount = (clone $open)->count();
        $overdue = (clone $open)->whereNotNull('due_at')->where('due_at', '<', $now)->count();
        $dueSoon = (clone $open)->whereNotNull('due_at')->whereBetween('due_at', [$now, $now->copy()->addDays(3)])->count();
        $unassigned = (clone $open)->whereNull('assigned_to')->count();
        $completed30 = (clone $tasks)->where('status', 'COMPLETED')->where('completed_at', '>=', $now->copy()->subDays(30))->count();
        $activeEmployees = User::query()->where('is_active', true)
            ->when($this->businessId() !== null, fn ($q) => $q->where('business_id', $this->businessId()))->count();
        $priorityMix = (clone $open)->select('priority', DB::raw('COUNT(*) as total'))
            ->groupBy('priority')->orderBy('priority')->get();
        $recent = (clone $tasks)->with('assignee')->orderByDesc('updated_at')->limit(10)->get();

        return view('automation.control-center', compact(
            'openCount', 'overdue', 'dueSoon', 'unassigned', 'completed30', 'activeEmployees', 'priorityMix', 'recent'
        ));
    }

    /** Package 91: export a scoped, read-only task governance register. */
    public function export(Request $request): StreamedResponse
    {
        $status = strtoupper((string) $request->query('status', 'OPEN'));
        $allowed = ['OPEN', 'IN_PROGRESS', 'COMPLETED', 'CANCELLED', 'ALL'];
        if (! in_array($status, $allowed, true)) {
            $status = 'OPEN';
        }

        $query = $this->tasksQuery()->with('assignee')->orderBy('due_at')->orderBy('id');
        if ($status === 'OPEN') {
            $query->whereNotIn('status', ['COMPLETED', 'CANCELLED']);
        } elseif ($status !== 'ALL') {
            $query->where('status', $status);
        }

        $filename = 'automation-task-register-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Task ID', 'Title', 'Status', 'Priority', 'Assignee', 'Due At', 'Completed At', 'Created At', 'Updated At']);
            $query->chunk(250, function ($tasks) use ($out) {
                foreach ($tasks as $task) {
                    $cells = [
                        $task->id, $task->title, $task->status, $task->priority,
                        optional($task->assignee)->name, optional($task->due_at)->toDateTimeString(),
                        optional($task->completed_at)->toDateTimeString(), optional($task->created_at)->toDateTimeString(),
                        optional($task->updated_at)->toDateTimeString(),
                    ];
                    // Neutralize spreadsheet formulas in untrusted text fields.
                    $cells = array_map(static function ($value) {
                        if (is_string($value) && preg_match('/^[\s]*[=+\-@]/u', $value)) {
                            return "'".$value;
                        }

                        return $value;
                    }, $cells);
                    fputcsv($out, $cells);
                }
            });
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Package 92: monitor workflow dependency readiness when package 75–77 table exists. */
    public function readiness()
    {
        $base = $this->tasksQuery()->whereNotIn('status', ['COMPLETED', 'CANCELLED']);
        $openCount = (clone $base)->count();
        $overdue = (clone $base)->whereNotNull('due_at')->where('due_at', '<', now())->count();
        $unassigned = (clone $base)->whereNull('assigned_to')->count();
        $dependencyAvailable = DB::getSchemaBuilder()->hasTable('automation_task_dependencies');
        $blocked = 0;
        $released = 0;
        $dependencyRows = collect();

        if ($dependencyAvailable) {
            $taskTable = (new AutomationTask)->getTable();
            $businessId = $this->businessId();
            $deps = DB::table('automation_task_dependencies as d')
                ->join($taskTable.' as prerequisite', 'prerequisite.id', '=', 'd.prerequisite_task_id')
                ->join($taskTable.' as dependent', 'dependent.id', '=', 'd.dependent_task_id')
                ->whereNotIn('dependent.status', ['COMPLETED', 'CANCELLED'])
                ->when($businessId !== null, fn ($q) => $q->where('dependent.business_id', $businessId)->where('prerequisite.business_id', $businessId))
                ->select('d.*', 'prerequisite.title as prerequisite_title', 'prerequisite.status as prerequisite_status', 'dependent.title as dependent_title', 'dependent.status as dependent_status')
                ->get();
            $blocked = $deps->filter(fn ($d) => strtoupper((string) $d->prerequisite_status) !== 'COMPLETED')->count();
            $released = $deps->filter(fn ($d) => strtoupper((string) $d->prerequisite_status) === 'COMPLETED')->count();
            $dependencyRows = $deps->filter(fn ($d) => strtoupper((string) $d->prerequisite_status) !== 'COMPLETED')->take(50)->values();
        }

        return view('automation.workflow-readiness', compact(
            'openCount', 'overdue', 'unassigned', 'dependencyAvailable', 'blocked', 'released', 'dependencyRows'
        ));
    }
}
