@extends('layouts.app')
@section('content')
<div class="container-fluid py-3">
 <div class="mb-3"><h3 class="mb-1">Dependency Impact Analysis</h3><p class="text-muted mb-0">Find prerequisite tasks that hold up the largest amount of downstream work.</p></div>
 <div class="row g-3 mb-3"><div class="col-sm-6 col-xl-3"><div class="card"><div class="card-body"><div class="text-muted small">Dependency links</div><div class="fs-3 fw-semibold">{{ $links }}</div></div></div></div><div class="col-sm-6 col-xl-3"><div class="card"><div class="card-body"><div class="text-muted small">Prerequisites with open dependents</div><div class="fs-3 fw-semibold">{{ $critical }}</div></div></div></div></div>
 <div class="card"><div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th>Prerequisite task</th><th>Status</th><th class="text-end">Total dependents</th><th class="text-end">Open dependents</th><th class="text-end">Potentially blocked</th></tr></thead><tbody>
 @forelse($rows as $row)<tr><td class="fw-semibold">{{ $row->task->title }}</td><td>{{ $row->task->status }}</td><td class="text-end">{{ $row->dependent_count }}</td><td class="text-end">{{ $row->open_count }}</td><td class="text-end"><span class="badge {{ $row->blocked_count ? 'bg-warning text-dark' : 'bg-secondary' }}">{{ $row->blocked_count }}</span></td></tr>
 @empty<tr><td colspan="5" class="text-center text-muted py-4">No dependency links found for this business.</td></tr>@endforelse
 </tbody></table></div></div>
 <small class="text-muted d-block mt-2">A dependent is counted as potentially blocked while its prerequisite is not completed. This report does not change task status or enforce dependency rules.</small>
</div>
@endsection
