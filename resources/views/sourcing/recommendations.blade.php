@extends('layouts.app')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
        <div>
            <h3 class="mb-1">Smart Supplier Sourcing</h3>
            <p class="text-muted mb-0">Compare suppliers using received purchase history, delivery performance, review ratings and historical cost.</p>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('supplier-sourcing.index') }}" class="row g-3 align-items-end">
                <div class="col-lg-8">
                    <label class="form-label">Select product</label>
                    <select name="product_id" class="form-select" required>
                        <option value="">Choose a product to compare suppliers</option>
                        @foreach($products as $product)
                            <option value="{{ $product->id }}" @selected($selectedProduct?->id === $product->id)>{{ $product->name }}{{ $product->sku ? ' · '.$product->sku : '' }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-lg-4"><button class="btn btn-primary w-100">Find supplier recommendations</button></div>
            </form>
        </div>
    </div>

    @if($selectedProduct)
        <div class="mb-3">
            <h5 class="mb-1">Supplier comparison</h5>
            <div class="text-muted small">{{ $selectedProduct->name }}{{ $selectedProduct->sku ? ' · '.$selectedProduct->sku : '' }}</div>
        </div>
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                @if($recommendations->isEmpty())
                    <div class="text-center py-5">
                        <h5>No received purchase history found</h5>
                        <p class="text-muted mb-0">Supplier recommendations will appear after this product has been received through purchase orders.</p>
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table align-middle">
                            <thead><tr><th>Supplier</th><th>Recommendation</th><th>Score</th><th>Avg. unit cost</th><th>Cost range</th><th>On-time delivery</th><th>Review / 5</th><th>Received units</th><th>Last receipt</th></tr></thead>
                            <tbody>
                            @foreach($recommendations as $supplier)
                                <tr>
                                    <td class="fw-semibold">{{ $supplier->supplier_name }}</td>
                                    <td><span class="badge bg-{{ $supplier->risk_label === 'Preferred' ? 'success' : ($supplier->risk_label === 'Consider' ? 'warning' : 'secondary') }}">{{ $supplier->risk_label }}</span></td>
                                    <td><strong>{{ $supplier->score }}/100</strong></td>
                                    <td>{{ number_format((float)$supplier->average_unit_cost, 2) }}</td>
                                    <td>{{ number_format((float)$supplier->lowest_unit_cost, 2) }} – {{ number_format((float)$supplier->highest_unit_cost, 2) }}</td>
                                    <td>{{ $supplier->on_time_rate === null ? 'No data' : number_format((float)$supplier->on_time_rate, 1).'%' }}</td>
                                    <td>{{ $supplier->rating_out_of_5 === null ? 'Not reviewed' : $supplier->rating_out_of_5 }}</td>
                                    <td>{{ (int)$supplier->received_units }}</td>
                                    <td>{{ $supplier->last_received_at ? \Illuminate\Support\Carbon::parse($supplier->last_received_at)->format('d M Y') : '—' }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="small text-muted">Recommendation score: delivery reliability 35%, supplier review 35%, historical price competitiveness 30%. Missing delivery or review history receives a neutral baseline. Historical cost is not a live quote.</div>
                @endif
            </div>
        </div>
    @else
        <div class="alert alert-light border">Choose a product to see suppliers with received purchase history.</div>
    @endif
</div>
@endsection
