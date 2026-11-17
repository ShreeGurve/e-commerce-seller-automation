@extends('layouts.app')
@section('content')
<div class="container-fluid py-3">
    <div class="mb-3"><h4 class="mb-1">Task Priority Alignment</h4><div class="text-muted">Rule-based review of due dates against task priority. All findings require human review.</div></div>
    <div class="row g-3 mb-4">
        <div class="col-md-4"><div class="card"><div class="card-body"><div class="text-muted small">Overdue without high priority</div><div class="fs-3 fw-semibold text-danger">{{ $overdue }}</div></div></div></div>
        <div class="col-md-4"><div class="card"><div class="card-body"><div class="text-muted small">Due within 48 hours</div><div class="fs-3 fw-semibold text-warning">{{ $dueSoon }}</div></div></div></div>
        <div class="col-md-4"><div class="card"><div class="card-body"><div class="text-muted small">High priority, due after 7+ days</div><div class="fs-3 fw-semibold">{{ $lowUrgency }}</div></div></div></div>
    </div>
    <div class="card"><div class="card-header fw-semibold">Priority review queue</div><div class="table-responsive"><table class="table table-hover mb-0"><thead><tr><th>Task</th><th>Assignee</th><th>Priority</th><th>Due</th><th>Review reason</th></tr></thead><tbody>
    @forelse($rows as $row)<tr><td>{{ $row->task->title }}</td><td>{{ $row->task->assignee->name ?? 'Unassigned' }}</td><td>{{ $row->task->priority ?: 'NORMAL' }}</td><td>{{ $row->task->due_at->format('d M Y, h:i A') }}</td><td>{{ $row->reason }}</td></tr>@empty<tr><td colspan="5" class="text-center text-muted py-4">No priority alignment exceptions found.</td></tr>@endforelse
    </tbody></table></div></div>
    <p class="text-muted small mt-2">Rules: overdue or due within 48 hours with priority below HIGH; or HIGH/URGENT/CRITICAL with a due date more than 7 days away. Adjust priority policy to match your business process.</p>
</div>
@endsection
