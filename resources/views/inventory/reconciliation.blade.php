@extends('layouts.app')
@section('title', 'Inventory Reconciliation')
@section('subtitle', 'Investigate differences between current stock buckets and the immutable inventory ledger')
@section('content')
    <div class="row g-3 mb-3">
        <div class="col-md-4"><div class="card h-100"><div class="card-body"><div class="small text-muted">Inventory records</div><div class="fs-3 fw-bold">{{ number_format($summary['inventory_rows']) }}</div></div></div></div>
        <div class="col-md-4"><div class="card h-100"><div class="card-body"><div class="small text-muted">Ledger mismatches</div><div class="fs-3 fw-bold {{ $summary['mismatches'] ? 'text-danger' : 'text-success' }}">{{ number_format($summary['mismatches']) }}</div></div></div></div>
        <div class="col-md-4"><div class="card h-100"><div class="card-body"><div class="small text-muted">Matching records</div><div class="fs-3 fw-bold">{{ number_format($summary['matched']) }}</div></div></div></div>
    </div>

    <div class="alert alert-info">
        This page is an investigation report only. It does not edit stock or ledger rows. Resolve discrepancies through an authorized, reviewed stock adjustment using the existing inventory workflow.
    </div>

    <div class="card mb-3"><div class="card-body">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-5"><label class="form-label">Product / SKU</label><input name="q" value="{{ $filters['q'] ?? '' }}" class="form-control" maxlength="120" placeholder="Search product or SKU"></div>
            <div class="col-md-4"><label class="form-label">Reconciliation status</label><select name="status" class="form-select"><option value="">All records</option><option value="mismatch" @selected(($filters['status'] ?? '') === 'mismatch')>Mismatches only</option><option value="matched" @selected(($filters['status'] ?? '') === 'matched')>Matched only</option></select></div>
            <div class="col-md-3 d-flex gap-2"><button class="btn btn-primary">Apply</button><a href="{{ route('inventory.reconciliation') }}" class="btn btn-outline-secondary">Reset</a></div>
        </form>
    </div></div>

    <div class="card mb-3"><div class="card-header fw-semibold">Inventory bucket records</div><div class="table-responsive">
        <table class="table table-hover align-middle mb-0"><thead><tr><th>SKU</th><th>Product</th><th class="text-end">Available</th><th class="text-end">Damaged</th><th>Status</th><th></th></tr></thead><tbody>
        @forelse($rows as $row)
            @php($mismatch = in_array((int)$row->product_id, $mismatchIds, true))
            <tr><td>{{ $row->product?->sku ?? '—' }}</td><td>{{ $row->product?->name ?? 'Deleted product' }}</td><td class="text-end">{{ number_format($row->available_qty) }}</td><td class="text-end">{{ number_format($row->damaged_qty) }}</td><td>@if($mismatch)<span class="badge bg-danger">Mismatch</span>@else<span class="badge bg-success">Matched</span>@endif</td><td>@if($row->product)<a class="btn btn-sm btn-outline-primary" href="{{ route('inventory.show', $row->product_id) }}">Inspect</a>@endif</td></tr>
        @empty<tr><td colspan="6" class="text-center text-muted py-4">No inventory records found.</td></tr>@endforelse
        </tbody></table>
    </div>@if($rows->hasPages())<div class="card-footer">{{ $rows->links() }}</div>@endif</div>

    <div class="card"><div class="card-header fw-semibold">Latest ledger activity</div><div class="table-responsive">
        <table class="table table-hover align-middle mb-0"><thead><tr><th>Date</th><th>SKU / Product</th><th>Type</th><th class="text-end">Available Δ</th><th class="text-end">Damaged Δ</th><th class="text-end">Balance after</th><th>Note</th></tr></thead><tbody>
        @forelse($ledger as $entry)
            <tr><td>{{ $entry->created_at?->format('d M Y H:i') }}</td><td>{{ $entry->product?->sku ?? '—' }}<div class="small text-muted">{{ $entry->product?->name ?? 'Deleted product' }}</div></td><td>{{ \App\Models\InventoryTransaction::TYPES[$entry->type]['label'] ?? $entry->type }}</td><td class="text-end">{{ number_format($entry->qty_change) }}</td><td class="text-end">{{ number_format($entry->damaged_change) }}</td><td class="text-end">{{ number_format($entry->balance_after) }}</td><td>{{ $entry->note }}</td></tr>
        @empty<tr><td colspan="7" class="text-center text-muted py-4">No ledger entries found.</td></tr>@endforelse
        </tbody></table>
    </div></div>
@endsection
