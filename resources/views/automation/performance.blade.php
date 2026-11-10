@extends('layouts.app')
@section('title', 'Automation Performance')
@section('content')
<div class="container-fluid py-3"><h4 class="mb-3">Automation Performance Dashboard</h4>
<div class="row g-3 mb-4">
@foreach(['Open tasks'=>'open','Overdue'=>'overdue','Created · 30 days'=>'created_30d','Completed · 30 days'=>'completed_30d','Escalations · 30 days'=>'escalated_30d'] as $label=>$key)<div class="col-md-2 col-sm-6"><div class="card card-body h-100"><small class="text-muted">{{ $label }}</small><h3 class="mb-0">{{ number_format($stats[$key]) }}</h3></div></div>@endforeach
<div class="col-md-2 col-sm-6"><div class="card card-body h-100"><small class="text-muted">Completion rate · 30 days</small><h3 class="mb-0">{{ $stats['completion_rate'] === null ? '—' : $stats['completion_rate'].'%' }}</h3></div></div>
</div>
<div class="row g-3 mb-4"><div class="col-md-4"><div class="card card-body"><small class="text-muted">Average completion time · 30 days</small><h4>{{ $stats['avg_completion_hours'] === null ? '—' : number_format((float)$stats['avg_completion_hours'], 1).' hours' }}</h4></div></div><div class="col-md-8"><div class="card card-body"><strong>Task creation · Last 14 days</strong><div class="d-flex flex-wrap gap-2 mt-3">@forelse($daily as $day)<span class="badge bg-light text-dark border">{{ \Illuminate\Support\Carbon::parse($day->day)->format('d M') }}: {{ $day->created_count }}</span>@empty<span class="text-muted">No task creation data.</span>@endforelse</div></div></div></div>
<div class="card"><div class="card-header">Recent tasks</div><div class="table-responsive"><table class="table mb-0"><thead><tr><th>Task</th><th>Assignee</th><th>Status</th><th>Created</th><th>Completed</th></tr></thead><tbody>@forelse($recent as $task)<tr><td>{{ $task->title }}</td><td>{{ $task->assignee?->name ?? 'Unassigned' }}</td><td>{{ $task->status }}</td><td>{{ $task->created_at?->format('d M Y') }}</td><td>{{ $task->completed_at?->format('d M Y') ?? '—' }}</td></tr>@empty<tr><td colspan="5" class="text-center text-muted py-4">No tasks recorded.</td></tr>@endforelse</tbody></table></div></div>
</div>
@endsection
