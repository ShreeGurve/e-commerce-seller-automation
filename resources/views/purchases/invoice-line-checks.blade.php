@extends('layouts.app')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
        <div>
            <h1 class="h3 mb-1">Invoice Line Reconciliation</h1>
            <p class="text-muted mb-0">Compare supplier-invoiced quantities and unit costs with received quantities and purchase-order costs.</p>
        </div>
        <a href="{{ route('purchase-invoice-matching.index') }}" class="btn btn-outline-secondary">Invoice Matching</a>
    </div>

    <div class="row g-3 mb-4">
        @foreach([
            ['Invoice matches', $summary['matches']],
            ['Checked lines', $summary['checked_lines']],
            ['Lines requiring review', $summary['review_lines']],
            ['Net quantity variance', $summary['quantity_variance']],
        ] as [$label, $value])
            <div class="col-6 col-xl-3">
                <div class="card border-0 shadow-sm h-100"><div class="card-body">
                    <div class="small text-muted">{{ $label }}</div>
                    <div class="fs-3 fw-semibold">{{ number_format($value) }}</div>
                </div></div>
            </div>
        @endforeach
    </div>

    <form method="GET" class="card card-body mb-3">
        <div class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label">Line status</label>
                <select name="status" class="form-select">
                    <option value="">All statuses</option>
                    <option value="MATCHED" @selected(request('status') === 'MATCHED')>Matched</option>
                    <option value="REVIEW_REQUIRED" @selected(request('status') === 'REVIEW_REQUIRED')>Review required</option>
                </select>
            </div>
            <div class="col-md-auto"><button class="btn btn-primary">Filter</button></div>
        </div>
    </form>

    @forelse($matches as $match)
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white d-flex flex-wrap justify-content-between gap-2">
                <div>
                    <strong>{{ $match->purchase->po_number }}</strong>
                    <span class="text-muted ms-2">{{ $match->purchase->supplier->name ?? 'Supplier' }}</span>
                    <div class="small text-muted">Invoice {{ $match->supplier_invoice_number }} · {{ optional($match->supplier_invoice_date)->format('d M Y') }}</div>
                </div>
                <span class="badge text-bg-light">{{ $match->match_status }}</span>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('purchase-invoice-line-checks.store', $match) }}">
                    @csrf
                    <div class="table-responsive">
                        <table class="table table-sm align-middle">
                            <thead><tr>
                                <th>Product</th><th class="text-end">PO qty</th><th class="text-end">Received</th>
                                <th style="min-width:130px">Invoice qty</th><th class="text-end">PO unit cost</th>
                                <th style="min-width:140px">Invoice unit cost</th><th style="min-width:180px">Review note</th>
                                <th>Previous check</th>
                            </tr></thead>
                            <tbody>
                            @foreach($match->purchase->items as $item)
                                @php($check = $match->lineChecks->firstWhere('purchase_item_id', $item->id))
                                <tr>
                                    <td>
                                        <div class="fw-semibold">{{ $item->product->name ?? 'Product #'.$item->product_id }}</div>
                                        <small class="text-muted">{{ $item->product->sku ?? '' }}</small>
                                        <input type="hidden" name="lines[{{ $loop->index }}][purchase_item_id]" value="{{ $item->id }}">
                                    </td>
                                    <td class="text-end">{{ number_format($item->quantity) }}</td>
                                    <td class="text-end">{{ number_format($item->received_qty) }}</td>
                                    <td><input class="form-control form-control-sm" type="number" min="0" name="lines[{{ $loop->index }}][invoice_quantity]" value="{{ old('lines.'.$loop->index.'.invoice_quantity', $check->invoice_quantity ?? $item->received_qty) }}" required></td>
                                    <td class="text-end">{{ number_format((float)$item->unit_cost, 2) }}</td>
                                    <td><input class="form-control form-control-sm" type="number" min="0" step="0.01" name="lines[{{ $loop->index }}][invoice_unit_cost]" value="{{ old('lines.'.$loop->index.'.invoice_unit_cost', $check->invoice_unit_cost ?? $item->unit_cost) }}" required></td>
                                    <td><input class="form-control form-control-sm" name="lines[{{ $loop->index }}][review_note]" value="{{ old('lines.'.$loop->index.'.review_note', $check->review_note ?? '') }}" placeholder="Optional note"></td>
                                    <td>
                                        @if($check)
                                            <span class="badge {{ $check->line_status === 'MATCHED' ? 'text-bg-success' : 'text-bg-warning' }}">{{ $check->line_status }}</span>
                                            <small class="d-block text-muted">Qty Δ {{ $check->quantity_variance }} · Cost Δ {{ number_format((float)$check->cost_variance, 2) }}</small>
                                        @else
                                            <span class="text-muted small">Not checked</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                    <button class="btn btn-primary btn-sm">Save line checks</button>
                </form>
            </div>
        </div>
    @empty
        <div class="card card-body text-center text-muted">No supplier invoice matches found. Record an invoice match first.</div>
    @endforelse
    {{ $matches->links() }}
</div>
@endsection
