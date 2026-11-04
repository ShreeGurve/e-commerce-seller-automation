@extends('layouts.app')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
        <div>
            <h3 class="mb-1">Supplier Risk Intelligence</h3>
            <p class="text-muted mb-0">A consolidated view of supplier delivery, reviews, compliance and open issues.</p>
        </div>
        <a href="{{ route('supplier-risk.export', request()->query()) }}" class="btn btn-outline-primary">Export CSV</a>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-4"><div class="card border-0 shadow-sm"><div class="card-body"><div class="text-muted small">Low risk</div><div class="fs-3 fw-semibold text-success">{{ $counts['low'] }}</div></div></div></div>
        <div class="col-md-4"><div class="card border-0 shadow-sm"><div class="card-body"><div class="text-muted small">Medium risk</div><div class="fs-3 fw-semibold text-warning">{{ $counts['medium'] }}</div></div></div></div>
        <div class="col-md-4"><div class="card border-0 shadow-sm"><div class="card-body"><div class="text-muted small">High risk</div><div class="fs-3 fw-semibold text-danger">{{ $counts['high'] }}</div></div></div></div>
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <form method="GET" action="{{ route('supplier-risk.index') }}" class="row g-2 align-items-end">
                <div class="col-md-6">
                    <label class="form-label">Search supplier</label>
                    <input type="text" name="search" class="form-control" value="{{ request('search') }}" placeholder="Name, contact or email">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Risk level</label>
                    <select name="risk" class="form-select">
                        <option value="">All risk levels</option>
                        @foreach(['LOW' => 'Low', 'MEDIUM' => 'Medium', 'HIGH' => 'High'] as $value => $label)
                            <option value="{{ $value }}" @selected(request('risk') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <button class="btn btn-primary">Apply</button>
                    <a href="{{ route('supplier-risk.index') }}" class="btn btn-light">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead><tr>
                        <th>Supplier</th><th>Risk score</th><th>Risk</th><th>Delivery</th><th>Review</th><th>Documents</th><th>Open issues</th><th>Received POs</th>
                    </tr></thead>
                    <tbody>
                    @forelse($suppliers as $supplier)
                        @php
                            $riskClass = $supplier->risk_level === 'LOW' ? 'success' : ($supplier->risk_level === 'MEDIUM' ? 'warning' : 'danger');
                        @endphp
                        <tr>
                            <td><div class="fw-semibold">{{ $supplier->name }}</div><small class="text-muted">{{ $supplier->contact_person ?: $supplier->email }}</small></td>
                            <td><span class="fw-semibold">{{ $supplier->risk_score }}/100</span></td>
                            <td><span class="badge bg-{{ $riskClass }}">{{ $supplier->risk_level }}</span></td>
                            <td>{{ $supplier->delivery_rate === null ? 'Not rated' : $supplier->delivery_rate.'%' }}</td>
                            <td>{{ $supplier->review_score === null ? 'Not rated' : $supplier->review_score.'%' }}</td>
                            <td><span class="text-success">{{ $supplier->valid_documents }} valid</span><br><small class="text-danger">{{ $supplier->expired_documents }} expired</small></td>
                            <td>{{ $supplier->open_issues }} <small class="text-danger">({{ $supplier->overdue_issues }} overdue)</small></td>
                            <td>{{ $supplier->received_purchase_count }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-center text-muted py-4">No suppliers match these filters.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <div class="text-muted small">Risk score is an internal decision-support estimate, not a credit rating or external verification.</div>
        </div>
    </div>
</div>
@endsection
