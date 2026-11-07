@extends('layouts.app')
@section('title', 'Inventory Movement Analytics')
@section('subtitle', 'Read-only insights from the inventory transaction ledger')
@section('content')
    @if($integrityCount > 0)
        <div class="alert alert-danger">
            <strong>{{ $integrityCount }} inventory record(s) have a ledger mismatch.</strong>
            Investigate the transaction history before making stock corrections.
        </div>
    @else
        <div class="alert alert-success">Inventory bucket quantities match the transaction ledger.</div>
    @endif

    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-md-2"><label class="form-label">From</label><input type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="form-control"></div>
                <div class="col-md-2"><label class="form-label">To</label><input type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="form-control"></div>
                <div class="col-md-3"><label class="form-label">Movement type</label><select name="type" class="form-select">
                    <option value="">All types</option>
                    @foreach($types as $key => $definition)
                        <option value="{{ $key }}" @selected(($filters['type'] ?? '') === $key)>{{ $definition['label'] }}</option>
                    @endforeach
                </select></div>
                <div class="col-md-3"><label class="form-label">Product / SKU</label><input name="q" value="{{ $filters['q'] ?? '' }}" class="form-control" maxlength="120" placeholder="Search product or SKU"></div>
                <div class="col-md-2 d-flex gap-2"><button class="btn btn-primary">Apply</button><a href="{{ route('inventory.movement-analytics') }}" class="btn btn-outline-secondary">Reset</a></div>
            </form>
        </div>
    </div>

    <div class="row g-3 mb-3">
        @foreach([
            ['Movements', number_format((int)$summary->movement_count), 't-blue', 'bi-arrow-left-right'],
            ['Units in', number_format((int)$summary->units_in), 't-green', 'bi-box-arrow-in-down'],
            ['Units out', number_format((int)$summary->units_out), 't-orange', 'bi-box-arrow-up'],
            ['Net damaged change', number_format((int)$summary->damaged_net), 't-red', 'bi-exclamation-triangle']
        ] as [$label, $value, $tile, $icon])
            <div class="col-6 col-xl-3"><div class="card h-100"><div class="card-body d-flex align-items-center gap-3">
                <div class="sg-tile {{ $tile }}"><i class="bi {{ $icon }}"></i></div>
                <div><div class="small text-muted">{{ $label }}</div><div class="fs-5 fw-bold">{{ $value }}</div></div>
            </div></div></div>
        @endforeach
    </div>

    <div class="row g-3 mb-3">
        <div class="col-xl-6"><div class="card h-100"><div class="card-header fw-semibold">Movement by type</div><div class="table-responsive">
            <table class="table table-sm table-hover mb-0"><thead><tr><th>Type</th><th class="text-end">Entries</th><th class="text-end">Net units</th><th class="text-end">Damaged net</th></tr></thead><tbody>
            @forelse($byType as $row)
                <tr><td>{{ $types[$row->type]['label'] ?? $row->type }}</td><td class="text-end">{{ number_format($row->movement_count) }}</td><td class="text-end">{{ number_format($row->net_units) }}</td><td class="text-end">{{ number_format($row->damaged_net) }}</td></tr>
            @empty<tr><td colspan="4" class="text-center text-muted py-3">No movements for these filters.</td></tr>@endforelse
            </tbody></table>
        </div></div></div>
        <div class="col-xl-6"><div class="card h-100"><div class="card-header fw-semibold">Daily movement trend (up to 90 days)</div><div class="table-responsive">
            <table class="table table-sm table-hover mb-0"><thead><tr><th>Date</th><th class="text-end">Entries</th><th class="text-end">Net units</th></tr></thead><tbody>
            @forelse($daily as $row)
                <tr><td>{{ \Illuminate\Support\Carbon::parse($row->movement_date)->format('d M Y') }}</td><td class="text-end">{{ number_format($row->movement_count) }}</td><td class="text-end">{{ number_format($row->net_units) }}</td></tr>
            @empty<tr><td colspan="3" class="text-center text-muted py-3">No daily movement data.</td></tr>@endforelse
            </tbody></table>
        </div></div></div>
    </div>

    <div class="card mb-3"><div class="card-header fw-semibold">Most active products</div><div class="table-responsive">
        <table class="table table-hover align-middle mb-0"><thead><tr><th>SKU</th><th>Product</th><th class="text-end">Movements</th><th class="text-end">Net units</th><th class="text-end">Damaged net</th></tr></thead><tbody>
        @forelse($products as $product)
            <tr><td>{{ $product->sku }}</td><td><a href="{{ route('inventory.show', $product->id) }}">{{ $product->name }}</a></td><td class="text-end">{{ number_format($product->movement_count) }}</td><td class="text-end">{{ number_format($product->net_units) }}</td><td class="text-end">{{ number_format($product->damaged_net) }}</td></tr>
        @empty<tr><td colspan="5" class="text-center text-muted py-3">No product movement data.</td></tr>@endforelse
        </tbody></table>
    </div></div>

    <div class="card"><div class="card-header fw-semibold">Recent ledger entries</div><div class="table-responsive">
        <table class="table table-hover align-middle mb-0"><thead><tr><th>Date</th><th>SKU / Product</th><th>Type</th><th class="text-end">Available Δ</th><th class="text-end">Damaged Δ</th><th class="text-end">Balance after</th><th>Note</th></tr></thead><tbody>
        @forelse($recent as $entry)
            <tr><td>{{ $entry->created_at?->format('d M Y H:i') }}</td><td>{{ $entry->sku }}<div class="small text-muted">{{ $entry->name }}</div></td><td>{{ $types[$entry->type]['label'] ?? $entry->type }}</td><td class="text-end">{{ number_format($entry->qty_change) }}</td><td class="text-end">{{ number_format($entry->damaged_change) }}</td><td class="text-end">{{ number_format($entry->balance_after) }}</td><td>{{ $entry->note }}</td></tr>
        @empty<tr><td colspan="7" class="text-center text-muted py-4">No inventory transactions found.</td></tr>@endforelse
        </tbody></table>
    </div>@if($recent->hasPages())<div class="card-footer">{{ $recent->links() }}</div>@endif</div>
@endsection
