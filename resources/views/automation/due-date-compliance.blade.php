@extends('layouts.app')
@section('content')
<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-center mb-3"><div><h4 class="mb-1">Due-date Compliance</h4><div class="text-muted">Completed tasks with a due date · last 30 days from {{ $since->format('d M Y') }}</div></div></div>
    <div class="row g-3 mb-4">
        <div class="col-md-3"><div class="card h-100"><div class="card-body"><div class="text-muted small">Reviewed tasks</div><div class="fs-3 fw-semibold">{{ $total }}</div></div></div></div>
        <div class="col-md-3"><div class="card h-100"><div class="card-body"><div class="text-muted small">Completed on time</div><div class="fs-3 fw-semibold text-success">{{ $onTime }}</div></div></div></div>
        <div class="col-md-3"><div class="card h-100"><div class="card-body"><div class="text-muted small">Completed late</div><div class="fs-3 fw-semibold text-danger">{{ $late }}</div></div></div></div>
        <div class="col-md-3"><div class="card h-100"><div class="card-body"><div class="text-muted small">On-time rate</div><div class="fs-3 fw-semibold">{{ $rate === null ? '—' : $rate.'%' }}</div></div></div></div>
    </div>
    <div class="card"><div class="card-header fw-semibold">Compliance by priority</div><div class="table-responsive"><table class="table table-hover mb-0"><thead><tr><th>Priority</th><th>Total</th><th>On time</th><th>Late</th><th>On-time rate</th></tr></thead><tbody>
    @forelse($byPriority as $row)<tr><td>{{ $row->priority }}</td><td>{{ $row->total }}</td><td class="text-success">{{ $row->on_time }}</td><td class="text-danger">{{ $row->late }}</td><td>{{ $row->total ? number_format($row->on_time / $row->total * 100, 1).'%' : '—' }}</td></tr>@empty<tr><td colspan="5" class="text-center text-muted py-4">No completed tasks with due dates in this period.</td></tr>@endforelse
    </tbody></table></div></div>
    <p class="text-muted small mt-2">This is a historical due-date comparison, not a configurable contractual SLA.</p>
</div>
@endsection
