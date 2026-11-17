@extends('layouts.app')
@section('content')
<div class="container-fluid py-3">
 <div class="mb-3"><h3 class="mb-1">Workload Rebalance Simulator</h3><p class="text-muted mb-0">Advisory suggestions based on estimated task hours and weekly capacity. No assignments are changed.</p></div>
 <div class="card mb-4"><div class="card-header fw-semibold">Suggested task moves</div><div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th>Task</th><th>Estimated effort</th><th>Current assignee</th><th>Suggested assignee</th></tr></thead><tbody>
 @forelse($recommendations as $item)<tr><td>{{ $item->task->title }}</td><td>{{ number_format($item->effort,1) }} hrs</td><td>{{ $item->from->name }}</td><td class="fw-semibold">{{ $item->to->name }}</td></tr>
 @empty<tr><td colspan="4" class="text-center text-muted py-4">No safe rebalance suggestions found. Capacity may be balanced, or task effort estimates may be missing.</td></tr>@endforelse
 </tbody></table></div></div>
 <div class="card"><div class="card-header fw-semibold">Projected capacity after suggested moves</div><div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th>Employee</th><th>Estimated hours</th><th>Weekly capacity</th><th>Utilization</th></tr></thead><tbody>
 @forelse($summary as $row)<tr><td>{{ $row->employee->name }}</td><td>{{ number_format($row->hours,1) }} hrs</td><td>{{ number_format($row->capacity,1) }} hrs</td><td><div class="d-flex align-items-center gap-2"><div class="progress flex-grow-1" style="height:8px"><div class="progress-bar {{ $row->utilization > 100 ? 'bg-danger' : ($row->utilization >= 85 ? 'bg-warning' : 'bg-success') }}" style="width:{{ min(100,$row->utilization) }}%"></div></div><span>{{ number_format($row->utilization,1) }}%</span></div></td></tr>
 @empty<tr><td colspan="4" class="text-center text-muted py-4">No active employees found.</td></tr>@endforelse
 </tbody></table></div></div>
 <small class="text-muted d-block mt-2">Simulation considers only open assigned tasks with effort_hours and existing weekly capacity (default 40 hours). It does not account for calendars, skills, leave, or task deadlines and never saves reassignment changes.</small>
</div>
@endsection
