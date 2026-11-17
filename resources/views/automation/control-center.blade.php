@extends('layouts.app')
@section('title', 'Automation Control Center')
@section('content')
<div class="container-fluid py-3">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
        <div><h3 class="mb-1">Automation Control Center</h3><p class="text-muted mb-0">A consolidated, read-only view of task operations and governance.</p></div>
        <a class="btn btn-outline-primary" href="{{ route('automation.control-center.export') }}"><i class="fas fa-file-csv me-1"></i> Export task register</a>
    </div>
    <div class="row g-3 mb-4">
        @foreach ([['Open tasks',$openCount,'primary'],['Overdue',$overdue,'danger'],['Due in 3 days',$dueSoon,'warning'],['Unassigned',$unassigned,'secondary'],['Completed · 30 days',$completed30,'success'],['Active employees',$activeEmployees,'info']] as [$label,$value,$tone])
        <div class="col-6 col-md-4 col-xl-2"><div class="card h-100 border-0 shadow-sm"><div class="card-body"><div class="small text-muted">{{ $label }}</div><div class="fs-3 fw-semibold text-{{ $tone }}">{{ number_format($value) }}</div></div></div></div>
        @endforeach
    </div>
    <div class="row g-3">
        <div class="col-lg-4"><div class="card h-100"><div class="card-header bg-white fw-semibold">Open task priority mix</div><div class="table-responsive"><table class="table table-sm mb-0"><thead><tr><th>Priority</th><th class="text-end">Tasks</th></tr></thead><tbody>
        @forelse($priorityMix as $item)<tr><td>{{ $item->priority ?: 'Not set' }}</td><td class="text-end">{{ number_format($item->total) }}</td></tr>@empty<tr><td colspan="2" class="text-muted text-center py-3">No open tasks</td></tr>@endforelse
        </tbody></table></div></div></div>
        <div class="col-lg-8"><div class="card h-100"><div class="card-header bg-white fw-semibold">Recently updated tasks</div><div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th>Task</th><th>Assignee</th><th>Status</th><th>Priority</th><th>Updated</th></tr></thead><tbody>
        @forelse($recent as $task)<tr><td><span class="fw-medium">{{ $task->title }}</span><div class="small text-muted">#{{ $task->id }}</div></td><td>{{ optional($task->assignee)->name ?: 'Unassigned' }}</td><td>{{ $task->status }}</td><td>{{ $task->priority ?: '—' }}</td><td class="text-muted small">{{ optional($task->updated_at)->format('d M Y H:i') }}</td></tr>@empty<tr><td colspan="5" class="text-center text-muted py-3">No tasks found</td></tr>@endforelse
        </tbody></table></div></div></div>
    </div>
    <div class="mt-3"><a href="{{ route('automation.workflow-readiness') }}" class="btn btn-link px-0">View workflow readiness and blockers →</a></div>
</div>
@endsection
