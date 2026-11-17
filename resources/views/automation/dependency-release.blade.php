@extends('layouts.app')
@section('content')
<div class="container-fluid py-3">
 <div class="mb-3"><h3 class="mb-1">Dependency Release Queue</h3><p class="text-muted mb-0">See which dependent tasks are ready to proceed and which remain blocked.</p></div>
 <div class="card"><div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th>Dependent task</th><th>Assignee</th><th>Prerequisites</th><th>Waiting</th><th>Readiness</th></tr></thead><tbody>
 @forelse($rows as $row)<tr><td class="fw-semibold">{{ $row->task->title }}</td><td>{{ $row->task->assignee->name ?? 'Unassigned' }}</td><td>{{ $row->required }}</td><td>{{ $row->waiting }}</td><td><span class="badge {{ $row->ready ? 'bg-success' : 'bg-warning text-dark' }}">{{ $row->ready ? 'Ready to proceed' : 'Blocked' }}</span></td></tr>
 @empty<tr><td colspan="5" class="text-center text-muted py-4">No active tasks with dependency links found.</td></tr>@endforelse
 </tbody></table></div></div>
 <small class="text-muted d-block mt-2">A task is marked ready when every linked prerequisite has status COMPLETED. This page does not change task status or enforce completion rules.</small>
</div>
@endsection
