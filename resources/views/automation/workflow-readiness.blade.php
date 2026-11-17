@extends('layouts.app')
@section('title', 'Workflow Readiness')
@section('content')
<div class="container-fluid py-3">
    <div class="mb-4"><h3 class="mb-1">Workflow Readiness Monitor</h3><p class="text-muted mb-0">Review open work, overdue items and prerequisite blockers. This screen does not change task status.</p></div>
    <div class="row g-3 mb-4">
        @foreach ([['Open tasks',$openCount,'primary'],['Overdue',$overdue,'danger'],['Unassigned',$unassigned,'secondary'],['Blocked dependencies',$blocked,'warning'],['Released dependencies',$released,'success']] as [$label,$value,$tone])
        <div class="col-6 col-md"><div class="card border-0 shadow-sm h-100"><div class="card-body"><div class="small text-muted">{{ $label }}</div><div class="fs-3 fw-semibold text-{{ $tone }}">{{ number_format($value) }}</div></div></div></div>
        @endforeach
    </div>
    @if(!$dependencyAvailable)
        <div class="alert alert-info">Dependency tracking is not installed yet. Install the task dependency migration from Packages 75–77 to enable blocker analysis. Other readiness indicators are available.</div>
    @else
        <div class="card"><div class="card-header bg-white fw-semibold">Tasks currently blocked by prerequisites</div><div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th>Waiting task</th><th>Prerequisite task</th><th>Prerequisite status</th><th>Waiting status</th></tr></thead><tbody>
        @forelse($dependencyRows as $row)<tr><td>{{ $row->dependent_title }}</td><td>{{ $row->prerequisite_title }}</td><td><span class="badge bg-warning text-dark">{{ $row->prerequisite_status }}</span></td><td>{{ $row->dependent_status }}</td></tr>@empty<tr><td colspan="4" class="text-center text-muted py-4">No blocked dependencies found</td></tr>@endforelse
        </tbody></table></div></div>
        @if($blocked > 50)<p class="small text-muted mt-2">Showing the first 50 of {{ $blocked }} blocked dependency relationships.</p>@endif
    @endif
    <a href="{{ route('automation.control-center') }}" class="btn btn-link px-0 mt-3">← Back to control center</a>
</div>
@endsection
