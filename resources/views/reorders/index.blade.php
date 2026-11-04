@extends('layouts.app')
@section('title', 'Reorder Planning')
@section('subtitle', 'Internal stock replenishment suggestions based on inventory thresholds and outstanding ordered quantities')
@section('content')
    <div class="row g-3 mb-3">
        <div class="col-md-6"><div class="card"><div class="card-body"><div class="small text-muted">Products at/below reorder point</div><div class="fs-4 fw-bold">{{ $lowCount }}</div></div></div></div>
        <div class="col-md-6"><div class="card"><div class="card-body"><div class="small text-muted">Suggested units to replenish</div><div class="fs-4 fw-bold">{{ number_format($totalSuggested) }}</div></div></div></div>
    </div>
    <div class="alert alert-info small">Suggestions use the product's reorder level (or minimum stock if reorder level is zero). Target stock is twice that threshold. Outstanding quantities on ORDERED purchase orders are deducted to prevent duplicate ordering. Draft POs are not treated as confirmed incoming stock.</div>
    @if ($rows->isEmpty())
        <div class="card"><div class="card-body text-center py-5"><i class="bi bi-check-circle fs-2 text-success"></i><h5 class="mt-2">No reorder required</h5><p class="text-muted mb-0">No active products are currently at or below their configured reorder threshold.</p></div></div>
    @else
        @can('purchases.create')
            <form method="POST" action="{{ route('reorders.purchase') }}" class="card mb-3">
                @csrf
                <div class="card-header fw-semibold">Create a draft purchase order from selected suggestions</div>
                <div class="card-body">
                    <div class="row g-2 mb-3">
                        <div class="col-md-5"><label class="form-label">Supplier *</label><select name="supplier_id" class="form-select" required><option value="">Choose supplier</option>@foreach ($suppliers as $supplier)<option value="{{ $supplier->id }}" @selected(old('supplier_id') == $supplier->id)>{{ $supplier->name }}</option>@endforeach</select></div>
                        <div class="col-md-3"><label class="form-label">PO date *</label><input type="date" name="purchase_date" class="form-control" value="{{ old('purchase_date', now()->toDateString()) }}" required></div>
                        <div class="col-md-4"><label class="form-label">Notes</label><input name="notes" class="form-control" value="{{ old('notes') }}" placeholder="Optional purchase planning note"></div>
                    </div>
                    <div class="table-responsive"><table class="table table-hover align-middle">
                        <thead><tr><th style="width:45px">Select</th><th>Product</th><th class="text-end">Available</th><th class="text-end">On order</th><th class="text-end">Reorder at</th><th style="width:125px">Order qty</th><th style="width:145px">Unit cost (₹)</th></tr></thead>
                        <tbody>
                        @foreach ($rows as $row)
                            <tr>
                                <td><input class="form-check-input" type="checkbox" name="items[{{ $row->product->id }}][include]" value="1" checked><input type="hidden" name="items[{{ $row->product->id }}][product_id]" value="{{ $row->product->id }}"></td>
                                <td><div class="fw-semibold">{{ $row->product->sku }}</div><div class="small text-muted">{{ $row->product->name }}</div></td>
                                <td class="text-end">{{ $row->available }}</td><td class="text-end">{{ $row->on_order }}</td><td class="text-end">{{ $row->reorder_at }}</td>
                                <td><input type="number" name="items[{{ $row->product->id }}][quantity]" class="form-control form-control-sm" min="1" max="1000000" value="{{ max(1, $row->suggested) }}"></td>
                                <td><input type="number" name="items[{{ $row->product->id }}][unit_cost]" class="form-control form-control-sm" min="0" step="0.01" value="{{ $row->unit_cost }}" required></td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table></div>
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2"><span class="small text-muted">Uncheck products you do not want to include. Unit cost is prefilled from current COGS where available and can be edited.</span><button class="btn btn-primary" onclick="return confirm('Create a draft purchase order? No supplier order will be sent.')"><i class="bi bi-file-earmark-plus"></i> Create draft PO</button></div>
                </div>
            </form>
        @endcan
        <div class="card"><div class="card-header fw-semibold">Reorder suggestions</div><div class="table-responsive"><table class="table table-hover align-middle mb-0">
            <thead><tr><th>SKU / Product</th><th class="text-end">Available</th><th class="text-end">On order</th><th class="text-end">Threshold</th><th class="text-end">Suggested qty</th><th>Status</th></tr></thead>
            <tbody>@foreach ($rows as $row)<tr><td><a href="{{ route('inventory.show', $row->product) }}" class="text-decoration-none fw-semibold">{{ $row->product->sku }}</a><div class="small text-muted">{{ $row->product->name }}</div></td><td class="text-end">{{ $row->available }}</td><td class="text-end">{{ $row->on_order }}</td><td class="text-end">{{ $row->reorder_at }}</td><td class="text-end fw-bold">{{ $row->suggested }}</td><td>@if ($row->suggested > 0)<span class="badge text-bg-warning">Reorder</span>@else<span class="badge text-bg-info">Covered by PO</span>@endif</td></tr>@endforeach</tbody>
        </table></div></div>
    @endif
@endsection
