@extends('layouts.app')
@section('content')
<div class="container-fluid py-3">
 <div class="d-flex flex-wrap justify-content-between align-items-center mb-3"><div><h3 class="mb-1">Open Task Aging Analysis</h3><p class="text-muted mb-0">How long active work has remained open, grouped into age cohorts.</p></div><span class="badge bg-secondary">{{ $rows->count() }} open tasks</span></div>
 <div class="row g-3 mb-3">@foreach($cohorts as $cohort)<div class="col-6 col-xl-3"><div class="card h-100"><div class="card-body"><div class="text-muted small">{{ $cohort->band }}</div><div class="fs-3 fw-semibold">{{ $cohort->count }}</div></div></div></div>@endforeach</div>
 <div class="card"><div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th>Task</th><th>Assignee</th><th>Status</th><th>Created</th><th>Age</th><th>Age group</th></tr></thead><tbody>
 @forelse($rows as $row)<tr><td class="fw-semibold">{{ $row->task->title }}</td><td>{{ $row->task->assignee->name ?? 'Unassigned' }}</td><td>{{ $row->task->status }}</td><td>{{ $row->task->created_at?->format('d M Y') }}</td><td>{{ $row->age }} days</td><td><span class="badge {{ $row->age >= 30 ? 'bg-danger' : ($row->age >= 14 ? 'bg-warning text-dark' : 'bg-secondary') }}">{{ $row->band }}</span></td></tr>
 @empty<tr><td colspan="6" class="text-center text-muted py-4">No open tasks found.</td></tr>@endforelse
 </tbody></table></div></div>
 <small class="text-muted d-block mt-2">Age is measured from task creation. Cohorts are descriptive and do not imply an SLA breach.</small>
</div>
@endsection
