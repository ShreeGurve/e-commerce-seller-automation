@extends('layouts.app')
@section('content')
<div class="container-fluid py-3"><div class="mb-3"><h3>Task Bottleneck Detection</h3><p class="text-muted mb-0">Highlights overdue, blocked, aging and dependency-heavy work.</p></div>
<div class="card"><div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th>Task</th><th>Assignee</th><th>Status</th><th>Age</th><th>Dependents</th><th>Detected signals</th></tr></thead><tbody>
@forelse($rows as $row)<tr><td><strong>{{ $row->task->title }}</strong><div class="small text-muted">#{{ $row->task->id }}</div></td><td>{{ $row->task->assignee->name ?? 'Unassigned' }}</td><td>{{ $row->task->status }}</td><td>{{ $row->age }} days</td><td>{{ $row->dependents }}</td><td>@foreach($row->reasons as $reason)<span class="badge bg-warning text-dark me-1 mb-1">{{ $reason }}</span>@endforeach</td></tr>@empty<tr><td colspan="6" class="text-center text-muted py-4">No current bottlenecks detected.</td></tr>@endforelse
</tbody></table></div></div></div>
@endsection
