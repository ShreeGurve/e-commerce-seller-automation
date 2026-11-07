@extends('layouts.app')
@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3"><div><h1 class="h3 mb-1">Inventory Valuation Estimate</h1><p class="text-muted mb-0">Indicative value using current product cost fields; not an accounting FIFO/weighted-average valuation.</p></div><a href="{{ route('inventory.valuation.export') }}" class="btn btn-outline-primary">Export CSV</a></div>
    <div class="row g-3 mb-3"><div class="col-md-3"><div class="card card-body"><small class="text-muted">Products</small><strong class="h3">{{ $summary['products'] }}</strong></div></div><div class="col-md-3"><div class="card card-body"><small class="text-muted">Available Units</small><strong class="h3">{{ number_format($summary['units']) }}</strong></div></div><div class="col-md-3"><div class="card card-body"><small class="text-muted">Estimated Value</small><strong class="h3">{{ number_format($summary['value'],2) }}</strong></div></div><div class="col-md-3"><div class="card card-body"><small class="text-muted">Missing Cost</small><strong class="h3">{{ $summary['missing_cost'] }}</strong></div></div></div>
    <div class="alert alert-info">This estimate uses the sum of the product's current cost-history components. Confirm the applicable cost types and currency before using these figures for financial reporting.</div>
    <div class="card"><div class="table-responsive"><table class="table table-hover mb-0"><thead><tr><th>Product</th><th>Available</th><th>Unit Cost</th><th>Estimated Value</th><th>Cost Status</th></tr></thead><tbody>
    @forelse($rows as $r)<tr><td>{{ $r->product?->name ?? 'Deleted product' }}<div class="small text-muted">{{ $r->product?->sku }}</div></td><td>{{ max(0,(int)$r->available_qty) }}</td><td>{{ number_format($r->valuation_unit_cost,2) }}</td><td>{{ number_format($r->valuation_value,2) }}</td><td>{{ $r->cost_missing ? 'Missing cost' : 'Available' }}</td></tr>@empty<tr><td colspan="5" class="text-center text-muted py-4">No inventory rows.</td></tr>@endforelse
    </tbody></table></div></div>
</div>
@endsection
