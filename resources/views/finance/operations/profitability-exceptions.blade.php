@extends('layouts.app')
@section('title', 'Profitability Exceptions')
@section('subtitle', 'Loss-making and low-margin order lines requiring review')
@section('content')
<div class="card mb-3"><div class="card-body">
<form method="GET" class="row g-2 align-items-end">
<div class="col-md-3"><label class="form-label">From</label><input class="form-control" type="date" name="from" value="{{ $from }}"></div>
<div class="col-md-3"><label class="form-label">To</label><input class="form-control" type="date" name="to" value="{{ $to }}"></div>
<div class="col-md-3"><label class="form-label">Low-margin threshold (%)</label><input class="form-control" type="number" min="0" max="50" step="0.5" name="margin" value="{{ $threshold }}"></div>
<div class="col-md-3"><button class="btn btn-primary">Apply filters</button></div>
</form></div></div>
<div class="row g-3 mb-3">
<div class="col-md-4"><div class="card h-100"><div class="card-body"><div class="text-muted small">Exception lines shown</div><div class="fs-3 fw-semibold">{{ number_format($summary->lines) }}</div></div></div></div>
<div class="col-md-4"><div class="card h-100"><div class="card-body"><div class="text-muted small">Loss-making lines</div><div class="fs-3 fw-semibold text-danger">{{ number_format($summary->losses) }}</div></div></div></div>
<div class="col-md-4"><div class="card h-100"><div class="card-body"><div class="text-muted small">Negative-profit exposure (INR)</div><div class="fs-3 fw-semibold">{{ number_format($summary->exposure, 2) }}</div></div></div></div>
</div>
<div class="alert alert-info">Read-only review. Values use the saved estimated profit, or actual profit where available. Maximum 500 exception lines are displayed. TEST and cancelled orders are excluded.</div>
<div class="card"><div class="table-responsive"><table class="table table-hover mb-0"><thead><tr><th>Date</th><th>Order</th><th>Marketplace</th><th>SKU / Product</th><th class="text-end">Qty</th><th class="text-end">Revenue</th><th class="text-end">COGS</th><th class="text-end">Fees</th><th class="text-end">Return loss</th><th class="text-end">Profit</th><th>Basis</th></tr></thead><tbody>
@forelse($rows as $r) @php($profit = (float)($r->actual_profit ?? $r->estimated_profit))
<tr><td>{{ $r->order_date }}</td><td><a href="{{ route('orders.show', $r->order_id) }}">{{ $r->order_number }}</a></td><td>{{ $r->marketplace ?? '—' }}</td><td>{{ $r->sku ?? '—' }}<div class="small text-muted">{{ $r->product_name ?? 'Deleted product' }}</div></td><td class="text-end">{{ $r->qty }}</td><td class="text-end">{{ number_format((float)$r->revenue,2) }}</td><td class="text-end">{{ number_format((float)$r->cogs,2) }}</td><td class="text-end">{{ number_format((float)$r->fees,2) }}</td><td class="text-end">{{ number_format((float)$r->return_loss,2) }}</td><td class="text-end fw-semibold {{ $profit < 0 ? 'text-danger' : '' }}">{{ number_format($profit,2) }}</td><td><span class="badge text-bg-{{ $r->actual_profit !== null ? 'success' : 'secondary' }}">{{ $r->actual_profit !== null ? 'Actual' : 'Estimated' }}</span></td></tr>
@empty<tr><td colspan="11" class="text-center text-muted py-4">No profitability exceptions for this period.</td></tr>@endforelse
</tbody></table></div></div>
@endsection
