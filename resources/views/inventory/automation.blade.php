@extends('layouts.app')
@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3"><div><h1 class="h3 mb-1">Inventory Automation Monitor</h1><p class="text-muted mb-0">Rule-based low-stock signals using existing minimum and reorder levels. No automatic purchasing or stock changes.</p></div>
        <a href="{{ route('inventory.automation.export') }}" class="btn btn-outline-primary">Export Signals CSV</a>
    </div>
    <div class="row g-3 mb-3">
        <div class="col-md-4"><div class="card card-body"><small class="text-muted">Out of Stock</small><strong class="h3 text-danger">{{ $stats['out'] }}</strong></div></div>
        <div class="col-md-4"><div class="card card-body"><small class="text-muted">Low Stock</small><strong class="h3 text-warning">{{ $stats['low'] }}</strong></div></div>
        <div class="col-md-4"><div class="card card-body"><small class="text-muted">Suggested Replenishment Units</small><strong class="h3">{{ $stats['suggested_units'] }}</strong></div></div>
    </div>
    <div class="card"><div class="table-responsive"><table class="table table-hover mb-0">
        <thead><tr><th>Product</th><th>Available</th><th>Minimum</th><th>Reorder Level</th><th>Suggested Units</th><th>Signal</th></tr></thead>
        <tbody>@forelse($rows as $r)@php($target = max((int)$r->minimum_stock, (int)$r->reorder_level))<tr>
            <td><strong>{{ $r->product?->name ?? 'Deleted product' }}</strong><div class="small text-muted">{{ $r->product?->sku }}</div></td>
            <td>{{ $r->available_qty }}</td><td>{{ $r->minimum_stock }}</td><td>{{ $r->reorder_level }}</td><td>{{ max(0, $target - (int)$r->available_qty) }}</td>
            <td><span class="badge bg-{{ (int)$r->available_qty <= 0 ? 'danger' : 'warning text-dark' }}">{{ (int)$r->available_qty <= 0 ? 'OUT OF STOCK' : 'LOW STOCK' }}</span></td>
        </tr>@empty<tr><td colspan="6" class="text-center text-muted py-4">No low-stock signals. Current thresholds are healthy.</td></tr>@endforelse</tbody>
    </table></div></div>
</div>
@endsection
