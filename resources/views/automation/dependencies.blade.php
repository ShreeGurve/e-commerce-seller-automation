@extends('layouts.app')
@section('title', 'Task Dependency Management')
@section('content')
<div class="container-fluid py-4">
    <h1 class="h3 mb-1">Task Dependency Management</h1><p class="text-muted">A dependent task is considered blocked while any prerequisite remains incomplete. This is a planning indicator; it does not change the task's stored status.</p>
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
    <div class="card mb-4"><div class="card-header fw-semibold">Add prerequisite</div><div class="card-body"><form method="POST" action="{{ route('automation.insights.dependencies.store') }}" class="row g-3">@csrf
        <div class="col-md-5"><label class="form-label">Prerequisite task</label><select name="predecessor_task_id" class="form-select" required><option value="">Select task</option>@foreach($tasks as $task)<option value="{{ $task->id }}">#{{ $task->id }} · {{ $task->title }}</option>@endforeach</select></div>
        <div class="col-md-5"><label class="form-label">Task waiting on it</label><select name="dependent_task_id" class="form-select" required><option value="">Select task</option>@foreach($tasks as $task)<option value="{{ $task->id }}">#{{ $task->id }} · {{ $task->title }}</option>@endforeach</select></div>
        <div class="col-md-2 d-flex align-items-end"><button class="btn btn-primary w-100">Add dependency</button></div></form></div></div>
    <div class="card"><div class="card-header fw-semibold">Existing dependencies</div><div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Prerequisite</th><th>Dependent task</th><th>Dependency status</th><th></th></tr></thead><tbody>
    @forelse($dependencies as $dependency)<tr><td>#{{ $dependency->predecessor_task_id }} · {{ $dependency->predecessor?->title ?? 'Deleted task' }}</td><td>#{{ $dependency->dependent_task_id }} · {{ $dependency->dependent?->title ?? 'Deleted task' }}</td><td>@if($dependency->predecessor && $dependency->predecessor->status === 'COMPLETED')<span class="badge bg-success">Prerequisite complete</span>@else<span class="badge bg-warning text-dark">Blocked · prerequisite open</span>@endif</td><td><form method="POST" action="{{ route('automation.insights.dependencies.destroy', $dependency) }}" onsubmit="return confirm('Remove this dependency?')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger">Remove</button></form></td></tr>
    @empty<tr><td colspan="4" class="text-center text-muted py-4">No dependencies configured.</td></tr>@endforelse
    </tbody></table></div><div class="card-footer">{{ $dependencies->links() }}</div></div>
</div>
@endsection
