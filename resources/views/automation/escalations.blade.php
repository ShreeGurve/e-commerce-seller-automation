@extends('layouts.app')
@section('title', 'Overdue Task Escalations')
@section('content')
<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-center mb-3"><h4>Overdue Task Escalations</h4><a href="{{ route('automation.workforce.workload') }}" class="btn btn-outline-secondary">Workload</a></div>
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    <div class="card"><div class="table-responsive"><table class="table table-hover mb-0"><thead><tr><th>Task</th><th>Assignee</th><th>Due</th><th>Overdue</th><th>Escalation</th></tr></thead><tbody>
    @forelse($tasks as $task)<tr><td><strong>{{ $task->title }}</strong><div class="small text-muted">{{ $task->priority }} · {{ $task->status }}</div></td><td>{{ $task->assignee?->name ?? 'Unassigned' }}</td><td>{{ $task->due_at?->format('d M Y H:i') }}</td><td>{{ $task->due_at?->diffForHumans() }}</td><td><form method="POST" action="{{ route('automation.workforce.escalate', $task) }}" class="d-flex flex-wrap gap-2">@csrf<select name="to_user_id" class="form-select form-select-sm" style="max-width:190px"><option value="">No recipient specified</option>@foreach($users as $user)<option value="{{ $user->id }}">{{ $user->name }}</option>@endforeach</select><input name="reason" required maxlength="500" class="form-control form-control-sm" placeholder="Reason" style="max-width:220px"><button class="btn btn-sm btn-warning">Record escalation</button></form></td></tr>
    @empty<tr><td colspan="5" class="text-center text-muted py-4">No overdue open tasks.</td></tr>@endforelse
    </tbody></table></div><div class="card-footer">{{ $tasks->links() }}</div></div>
</div>
@endsection
