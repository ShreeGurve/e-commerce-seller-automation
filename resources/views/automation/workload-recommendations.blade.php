@extends('layouts.app')
@section('content')
<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-center mb-3"><div><h3>Smart Workload Recommendations</h3><p class="text-muted mb-0">A planning signal based on open, overdue and high-priority tasks. No tasks are reassigned automatically.</p></div><span class="badge bg-warning text-dark">{{ $unassigned }} unassigned</span></div>
    <div class="card"><div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th>Employee</th><th>Open</th><th>Overdue</th><th>High / Urgent</th><th>Workload signal</th><th>Recommendation</th></tr></thead><tbody>
    @forelse($rows as $row)<tr><td>{{ $row->employee->name ?? $row->employee->email }}</td><td>{{ $row->open }}</td><td>{{ $row->overdue }}</td><td>{{ $row->urgent }}</td><td><strong>{{ $row->score }}</strong></td><td>@if($row->score >= 12)<span class="text-danger">Review capacity before assigning more work</span>@elseif($row->score >= 6)<span class="text-warning">Consider balancing upcoming assignments</span>@else<span class="text-success">Capacity signal is relatively light</span>@endif</td></tr>@empty<tr><td colspan="6" class="text-center text-muted py-4">No active employees found.</td></tr>@endforelse
    </tbody></table></div></div>
    <small class="text-muted d-block mt-2">The score is a relative triage indicator, not an employee performance rating.</small>
</div>
@endsection
