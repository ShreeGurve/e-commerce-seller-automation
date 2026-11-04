@extends('layouts.app')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div><h3 class="mb-1">Purchase Cost Intelligence</h3><p class="text-muted mb-0">Track supplier price movement and prepare evidence-based negotiation targets.</p></div>
        <a class="btn btn-outline-primary" href="{{ route('purchase-cost-intelligence.export', request()->query()) }}">Export CSV</a>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md"><div class="card border-0 shadow-sm"><div class="card-body"><div class="text-muted small">Supplier / product lines</div><div class="fs-3 fw-semibold">{{ $summary['supplier_lines'] }}</div></div></div></div>
        <div class="col-md"><div class="card border-0 shadow-sm"><div class="card-body"><div class="text-muted small">Received units</div><div class="fs-3 fw-semibold">{{ number_format($summary['received_units']) }}</div></div></div></div>
        <div class="col-md"><div class="card border-0 shadow-sm"><div class="card-body"><div class="text-muted small">Average unit cost</div><div class="fs-3 fw-semibold">{{ number_format($summary['average_cost'], 2) }}</div></div></div></div>
        <div class="col-md"><div class="card border-0 shadow-sm"><div class="card-body"><div class="text-muted small">Recent cost increases</div><div class="fs-3 fw-semibold text-danger">{{ $summary['increased'] }}</div></div></div></div>
        <div class="col-md"><div class="card border-0 shadow-sm"><div class="card-body"><div class="text-muted small">Recent cost decreases</div><div class="fs-3 fw-semibold text-success">{{ $summary['decreased'] }}</div></div></div></div>
    </div>

    <div class="card border-0 shadow-sm mb-3"><div class="card-body">
        <form method="GET" action="{{ route('purchase-cost-intelligence.index') }}" class="row g-2 align-items-end">
            <div class="col-lg-3"><label class="form-label">Product</label><select name="product_id" class="form-select"><option value="">All products</option>@foreach($products as $p)<option value="{{ $p->id }}" @selected(request('product_id') == $p->id)>{{ $p->name }}{{ $p->sku ? ' · '.$p->sku : '' }}</option>@endforeach</select></div>
            <div class="col-lg-3"><label class="form-label">Supplier</label><select name="supplier_id" class="form-select"><option value="">All suppliers</option>@foreach($suppliers as $s)<option value="{{ $s->id }}" @selected(request('supplier_id') == $s->id)>{{ $s->name }}</option>@endforeach</select></div>
            <div class="col-lg-2"><label class="form-label">Received from</label><input type="date" name="from" value="{{ request('from') }}" class="form-control"></div>
            <div class="col-lg-2"><label class="form-label">Received to</label><input type="date" name="to" value="{{ request('to') }}" class="form-control"></div>
            <div class="col-lg-2 d-flex gap-2"><button class="btn btn-primary">Filter</button><a href="{{ route('purchase-cost-intelligence.index') }}" class="btn btn-light">Reset</a></div>
        </form>
    </div></div>

    <div class="card border-0 shadow-sm"><div class="card-body">
        <div class="table-responsive"><table class="table align-middle">
            <thead><tr><th>Product</th><th>Supplier</th><th>Avg. cost</th><th>Latest</th><th>Previous</th><th>Change</th><th>Negotiation target</th><th>Potential saving / unit</th><th>Received qty</th></tr></thead>
            <tbody>
            @forelse($rows as $row)
                <tr>
                    <td><div class="fw-semibold">{{ $row->product_name }}</div><small class="text-muted">{{ $row->sku }}</small></td>
                    <td>{{ $row->supplier_name }}</td>
                    <td>{{ number_format((float)$row->average_unit_cost, 2) }}</td>
                    <td>{{ number_format((float)$row->latest_cost, 2) }}</td>
                    <td>{{ $row->previous_cost === null ? '—' : number_format((float)$row->previous_cost, 2) }}</td>
                    <td>
                        @if($row->change_percent === null) <span class="text-muted">—</span>
                        @else <span class="{{ $row->change_percent > 0 ? 'text-danger' : ($row->change_percent < 0 ? 'text-success' : 'text-muted') }}">{{ $row->change_percent > 0 ? '+' : '' }}{{ number_format($row->change_percent, 2) }}%</span> @endif
                    </td>
                    <td class="fw-semibold">{{ number_format($row->negotiation_target, 2) }}</td>
                    <td class="text-success">{{ number_format($row->target_saving, 2) }}</td>
                    <td>{{ number_format((int)$row->received_units) }}</td>
                </tr>
            @empty
                <tr><td colspan="9" class="text-center text-muted py-5">No received purchase cost history found for these filters.</td></tr>
            @endforelse
            </tbody>
        </table></div>
        <p class="small text-muted mb-0">Negotiation target is an illustrative 3% reduction from the supplier's historical average unit cost. It is a planning suggestion, not a supplier quote or approved purchase price.</p>
    </div></div>
</div>
@endsection
