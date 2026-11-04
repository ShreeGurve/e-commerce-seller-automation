@extends('layouts.app')
@section('title', 'Supplier Analytics')
@section('subtitle', 'Supplier delivery performance, purchase trends and cost movement')
@section('content')
<div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
    <form class="row g-2" method="GET"><div class="col-auto"><label class="form-label small mb-1">From</label><input type="date" name="from" value="{{ $from }}" class="form-control"></div><div class="col-auto"><label class="form-label small mb-1">To</label><input type="date" name="to" value="{{ $to }}" class="form-control"></div><div class="col-auto align-self-end"><button class="btn btn-outline-primary">Apply</button></div></form>
    <a class="btn btn-outline-success align-self-end" href="{{ route('supplier-analytics.export', request()->query()) }}"><i class="bi bi-download"></i> Export CSV</a>
</div>
<div class="row g-3 mb-3">
    @foreach ([['Purchase value','₹'.number_format($totalSpend,2),'bi-currency-rupee'],['Purchase orders',number_format($totalOrders),'bi-receipt'],['Active suppliers with purchases',number_format($totalSuppliers),'bi-truck'],['Overdue ordered POs',number_format($overdueOrders),'bi-clock-history']] as $k)
    <div class="col-6 col-xl-3"><div class="card h-100"><div class="card-body"><div class="small text-muted">{{ $k[0] }}</div><div class="fs-4 fw-bold mt-1">{{ $k[1] }}</div></div></div></div>
    @endforeach
</div>
<div class="card mb-3"><div class="card-header d-flex justify-content-between"><span>Supplier scorecards</span><span class="small text-muted">On-time rate uses received POs with an expected delivery date</span></div><div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th>Supplier</th><th class="text-end">POs</th><th class="text-end">Purchase value</th><th class="text-end">Units received</th><th class="text-end">On-time</th><th class="text-end">Overdue</th><th class="text-end">Rating</th></tr></thead><tbody>
@forelse($scores as $r)<tr><td><a class="fw-semibold text-decoration-none" href="{{ route('supplier-analytics.show', $r->supplier) }}">{{ $r->supplier->name }}</a><div class="small text-muted">{{ $r->supplier->city ?: 'Supplier' }}</div></td><td class="text-end">{{ $r->order_count }}</td><td class="text-end">₹{{ number_format($r->spend,2) }}</td><td class="text-end">{{ number_format($r->received_units) }} / {{ number_format($r->ordered_units) }}</td><td class="text-end">{{ $r->on_time_rate === null ? '—' : $r->on_time_rate.'%' }} <span class="small text-muted">({{ $r->on_time_count }}/{{ $r->evaluated_count }})</span></td><td class="text-end">{{ $r->overdue_count }}</td><td class="text-end">{{ $r->rating === null ? 'Not rated' : $r->rating.' / 5' }}</td></tr>
@empty<tr><td colspan="7" class="text-center text-muted py-4">No purchase records for this period.</td></tr>@endforelse
</tbody></table></div></div>
<div class="card"><div class="card-header">Recent product purchase-cost changes</div><div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Product</th><th>Latest supplier</th><th class="text-end">Previous cost</th><th class="text-end">Latest cost</th><th class="text-end">Change</th><th>Date</th></tr></thead><tbody>
@forelse($costChanges as $c)<tr><td class="fw-semibold">{{ $c->product?->name ?? 'Deleted product' }}<div class="small text-muted">{{ $c->product?->sku }}</div></td><td>{{ $c->supplier?->name ?? '—' }}</td><td class="text-end">₹{{ number_format($c->old,2) }}</td><td class="text-end">₹{{ number_format($c->new,2) }}</td><td class="text-end"><span class="badge text-bg-{{ ($c->change ?? 0) > 0 ? 'warning' : 'success' }}">{{ $c->change === null ? 'N/A' : (($c->change > 0 ? '+' : '').$c->change.'%') }}</span></td><td>{{ optional($c->date)->format('d M Y') }}</td></tr>
@empty<tr><td colspan="6" class="text-center text-muted py-4">At least two purchase records for the same product are needed to show a cost comparison.</td></tr>@endforelse
</tbody></table></div></div>
<div class="small text-muted mt-2">Reports use internal purchase records only. On-time delivery is measured only where an expected delivery date was entered. Cost comparisons use the two latest purchase-line unit costs in the selected period.</div>
@endsection
