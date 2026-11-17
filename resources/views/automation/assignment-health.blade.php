@extends('layouts.app')
@section('content')
<div class="container-fluid py-3">
    <div class="mb-3"><h4 class="mb-1">Task Assignment Health</h4><div class="text-muted">Open-task assignment checks against active users in this business.</div></div>
    <div class="row g-3 mb-4">
        <div class="col-md-4"><div class="card"><div class="card-body"><div class="text-muted small">Open tasks reviewed</div><div class="fs-3 fw-semibold">{{ $openCount }}</div></div></div></div>
        <div class="col-md-4"><div class="card"><div class="card-body"><div class="text-muted small">Unassigned</div><div class="fs-3 fw-semibold text-warning">{{ $unassigned }}</div></div></div></div>
        <div class="col-md-4"><div class="card"><div class="card-body"><div class="text-muted small">Inactive / unavailable assignee</div><div class="fs-3 fw-semibold text-danger">{{ $invalidAssignee }}</div></div></div></div>
    </div>
    <div class="card"><div class="card-header fw-semibold">Assignment exceptions</div><div class="table-responsive"><table class="table table-hover mb-0"><thead><tr><th>Task</th><th>Assignee</th><th>Due</th><th>Issue</th></tr></thead><tbody>
    @forelse($rows as $row)<tr><td>{{ $row->task->title }}</td><td>{{ $row->task->assignee->name ?? '—' }}</td><td>{{ $row->task->due_at ? $row->task->due_at->format('d M Y, h:i A') : '—' }}</td><td><span class="badge bg-warning text-dark">{{ $row->issue }}</span></td></tr>@empty<tr><td colspan="4" class="text-center text-muted py-4">No assignment exceptions found.</td></tr>@endforelse
    </tbody></table></div></div>
    <p class="text-muted small mt-2">This page is read-only. It does not reassign tasks or modify employee accounts.</p>
</div>
@endsection
