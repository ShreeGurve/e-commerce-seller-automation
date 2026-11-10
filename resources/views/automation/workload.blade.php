@extends('layouts.app')
@section('title', 'Employee Task Workload')
@section('content')
<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-center mb-3"><h4>Employee Task Workload</h4><a href="{{ route('automation.workforce.performance') }}" class="btn btn-outline-secondary">Performance</a></div>
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    <div class="alert alert-info">Workload counts are advisory. Tasks are never automatically reassigned.</div>
    <div class="row g-3 mb-3"><div class="col-md-3"><div class="card card-body"><small>Unassigned open tasks</small><h3>{{ $unassigned }}</h3></div></div>@foreach($workload as $row)<div class="col-md-3"><div class="card card-body"><strong>{{ $row['user']->name }}</strong><div>Open: {{ $row['open'] }} · Overdue: {{ $row['overdue'] }}</div><div>Due today: {{ $row['due_today'] }} · High/urgent: {{ $row['urgent'] }}</div></div></div>@endforeach</div>
    <div class="card"><div class="table-responsive"><table class="table table-hover mb-0"><thead><tr><th>Task</th><th>Current assignee</th><th>Due</th><th>Priority</th><th>Reassign</th></tr></thead><tbody>
    @forelse($tasks as $task)<tr><td>{{ $task->title }}</td><td>{{ $task->assignee?->name ?? 'Unassigned' }}</td><td>{{ $task->due_at?->format('d M Y H:i') ?? '—' }}</td><td>{{ ucfirst($task->priority) }}</td><td><form method="POST" action="{{ route('automation.workforce.reassign', $task) }}" class="d-flex gap-2">@csrf<select name="assigned_to" required class="form-select form-select-sm" style="max-width:180px"><option value="">Select employee</option>@foreach($workload as $row)<option value="{{ $row['user']->id }}" @selected($task->assigned_to == $row['user']->id)>{{ $row['user']->name }}</option>@endforeach</select><button class="btn btn-sm btn-outline-primary">Reassign</button></form></td></tr>
    @empty<tr><td colspan="5" class="text-center text-muted py-4">No open tasks.</td></tr>@endforelse
    </tbody></table></div><div class="card-footer">{{ $tasks->links() }}</div></div>
</div>
@endsection
