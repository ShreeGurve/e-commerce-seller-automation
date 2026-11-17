@extends('layouts.app')
@section('content')
<div class="container-fluid py-3">
 <div class="d-flex justify-content-between align-items-center mb-3"><div><h3 class="mb-1">Task Deadline Risk</h3><p class="text-muted mb-0">Rule-based urgency queue for open tasks with a due date.</p></div><span class="badge bg-secondary">{{ $rows->count() }} items</span></div>
 <div class="card"><div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th>Task</th><th>Assignee</th><th>Priority</th><th>Due</th><th>Time remaining</th><th>Risk</th></tr></thead><tbody>
 @forelse($rows as $row)<tr><td class="fw-semibold">{{ $row->task->title }}</td><td>{{ $row->task->assignee->name ?? 'Unassigned' }}</td><td>{{ $row->task->priority }}</td><td>{{ $row->task->due_at?->format('d M Y, h:i A') }}</td><td>{{ $row->hours < 0 ? abs($row->hours).' hours overdue' : $row->hours.' hours' }}</td><td><span class="badge {{ $row->risk === 'OVERDUE' || $row->risk === 'CRITICAL' ? 'bg-danger' : ($row->risk === 'HIGH' ? 'bg-warning text-dark' : 'bg-info text-dark') }}">{{ $row->risk }}</span></td></tr>
 @empty<tr><td colspan="6" class="text-center text-muted py-4">No tasks currently require deadline attention.</td></tr>@endforelse
 </tbody></table></div></div>
 <small class="text-muted d-block mt-2">Rules: overdue, due within 24 hours (critical), 72 hours (high), or 7 days (watch). This is a transparent heuristic, not an AI forecast.</small>
</div>
@endsection
