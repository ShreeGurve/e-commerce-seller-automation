@extends('layouts.app')
@section('title', 'Employee Productivity Insights')
@section('content')
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-3"><div><h1 class="h3 mb-1">Employee Productivity Insights</h1><p class="text-muted mb-0">Task throughput and workload indicators for the last 30 days. These are operational measures, not employee performance ratings.</p></div><a href="{{ route('automation.insights.analytics') }}" class="btn btn-outline-primary">Workflow analytics</a></div>
    <div class="card"><div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th>Employee</th><th>Open</th><th>Overdue</th><th>Completed · 30d</th><th>Created · 30d</th><th>Completion ratio</th><th>Avg. completion time</th></tr></thead><tbody>
    @forelse($rows as $row)<tr><td class="fw-semibold">{{ $row['user']->name }}</td><td>{{ $row['open'] }}</td><td><span class="{{ $row['overdue'] ? 'text-danger fw-semibold' : '' }}">{{ $row['overdue'] }}</span></td><td>{{ $row['completed_30d'] }}</td><td>{{ $row['created_30d'] }}</td><td>{{ is_null($row['completion_rate']) ? '—' : $row['completion_rate'].'%' }}</td><td>{{ is_null($row['avg_hours']) ? '—' : number_format((float)$row['avg_hours'], 1).' hrs' }}</td></tr>
    @empty<tr><td colspan="7" class="text-center text-muted py-4">No employee task data found.</td></tr>@endforelse
    </tbody></table></div></div>
</div>
@endsection
