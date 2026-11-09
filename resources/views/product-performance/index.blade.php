@extends('layouts.app')
@section('title', 'Product Performance Scorecard')
@section('subtitle', 'Compare product-level sales, costs, profitability and settlement coverage')
@section('content')
<div class="card mb-3"><div class="card-body"><form method="GET" class="row g-2 align-items-end">
<div class="col-md-2"><label class="form-label">From</label><input class="form-control" type="date" name="from" value="{{ $from }}" required></div>
<div class="col-md-2"><label class="form-label">To</label><input class="form-control" type="date" name="to" value="{{ $to }}" required></div>
<div class="col-md-3"><label class="form-label">Marketplace</label><select class="form-select" name="marketplace_id"><option value="">All marketplaces</option>@foreach($marketplaces as $m)<option value="{{ $m->id }}" @selected((string)$marketplaceId===(string)$m->id)>{{ $m->name }}</option>@endforeach</select></div>
<div class="col-md-2"><label class="form-label">Sort by</label><select class="form-select" name="sort">@foreach(['profit'=>'Profit','revenue'=>'Revenue','units'=>'Units','margin'=>'Margin'] as $k=>$v)<option value="{{ $k }}" @selected($sort===$k)>{{ $v }}</option>@endforeach</select></div>
<div class="col-md-1"><label class="form-label">Order</label><select class="form-select" name="direction"><option value="desc" @selected($direction==='desc')>Desc</option><option value="asc" @selected($direction==='asc')>Asc</option></select></div>
<div class="col-md-2 d-flex gap-2"><button class="btn btn-primary">Apply</button><a class="btn btn-outline-success" href="{{ route('product-performance.export', request()->query()) }}">CSV</a></div>
</form></div></div>
<div class="row g-3 mb-3">
@foreach([['Products',$summary['products']],['Units sold',number_format($summary['units'])],['Revenue','₹'.number_format($summary['revenue'],2)],['Profit','₹'.number_format($summary['profit'],2)],['Loss-making products',$summary['loss_products']]] as [$label,$value])<div class="col-6 col-lg"><div class="card h-100"><div class="card-body"><div class="small text-muted">{{ $label }}</div><div class="fs-4 fw-semibold">{{ $value }}</div></div></div></div>@endforeach
</div>
<div class="card"><div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th>Product</th><th class="text-end">Units</th><th class="text-end">Revenue</th><th class="text-end">COGS</th><th class="text-end">Fees</th><th class="text-end">Profit</th><th class="text-end">Margin</th><th class="text-end">Settled lines</th></tr></thead><tbody>
@forelse($rows as $row)<tr><td><div class="fw-semibold">{{ $row->name }}</div><small class="text-muted">{{ $row->sku }}</small></td><td class="text-end">{{ number_format($row->units) }}</td><td class="text-end">₹{{ number_format($row->revenue,2) }}</td><td class="text-end">₹{{ number_format($row->cogs,2) }}</td><td class="text-end">₹{{ number_format($row->fees,2) }}</td><td class="text-end {{ $row->profit<0?'text-danger':'text-success' }}">₹{{ number_format($row->profit,2) }}</td><td class="text-end">{{ $row->margin===null?'—':number_format($row->margin,1).'%' }}</td><td class="text-end">{{ number_format($row->settled_pct,0) }}%</td></tr>@empty<tr><td colspan="8" class="text-center text-muted py-4">No product profit records found for this period.</td></tr>@endforelse
</tbody></table></div></div>
<div class="alert alert-info mt-3 mb-0">Profit uses actual profit where settlement data exists and estimated profit otherwise. TEST-source orders are excluded. This is a management scorecard, not a statutory inventory valuation.</div>
@endsection
