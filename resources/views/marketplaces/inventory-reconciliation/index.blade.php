@extends('layouts.app')

@section('content')
<div class="container-fluid py-3">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1">Marketplace inventory reconciliation</h1>
            <p class="text-muted mb-0">Compare the latest marketplace inventory snapshots with internal available stock. This screen never changes stock automatically.</p>
        </div>
    </div>

    @if(session('success')) <div class="alert alert-success">{{ session('success') }}</div> @endif
    @if($errors->any()) <div class="alert alert-danger">{{ $errors->first() }}</div> @endif

    <div class="card mb-4">
        <div class="card-header fw-semibold">Run reconciliation</div>
        <div class="card-body">
            @forelse($connections as $connection)
                <div class="d-flex flex-wrap align-items-center justify-content-between border-bottom py-2 gap-2">
                    <div>
                        <div class="fw-semibold">{{ $connection->marketplace?->name ?? 'Marketplace' }} — {{ $connection->account_label ?: 'Seller account' }}</div>
                        <small class="text-muted">{{ ucfirst($connection->environment ?? 'production') }} · {{ ucfirst($connection->status) }}</small>
                    </div>
                    <form method="POST" action="{{ route('marketplaces.inventory-reconciliation.run', $connection) }}">
                        @csrf
                        <button class="btn btn-outline-primary btn-sm" {{ in_array(strtolower((string)$connection->status), ['connected','active','verified'], true) ? '' : 'disabled' }}>Compare latest snapshot</button>
                    </form>
                </div>
            @empty
                <p class="text-muted mb-0">No marketplace connections are configured.</p>
            @endforelse
        </div>
    </div>

    @if($selectedRun)
        <div class="card mb-4">
            <div class="card-header d-flex flex-wrap justify-content-between gap-2">
                <strong>{{ $selectedRun->connection?->marketplace?->name }} reconciliation</strong>
                <small class="text-muted">{{ $selectedRun->completed_at?->format('d M Y, H:i') }}</small>
            </div>
            <div class="card-body">
                <div class="row g-3 text-center">
                    <div class="col-6 col-lg"><div class="border rounded p-2"><div class="h4 mb-0">{{ $selectedRun->items_checked }}</div><small class="text-muted">Checked</small></div></div>
                    <div class="col-6 col-lg"><div class="border rounded p-2"><div class="h4 mb-0 text-success">{{ $selectedRun->matched_count }}</div><small class="text-muted">Matched</small></div></div>
                    <div class="col-6 col-lg"><div class="border rounded p-2"><div class="h4 mb-0 text-warning">{{ $selectedRun->mismatch_count }}</div><small class="text-muted">Mismatches</small></div></div>
                    <div class="col-6 col-lg"><div class="border rounded p-2"><div class="h4 mb-0">{{ $selectedRun->unmapped_count }}</div><small class="text-muted">Unmapped</small></div></div>
                    <div class="col-6 col-lg"><div class="border rounded p-2"><div class="h4 mb-0">{{ $selectedRun->managed_channel_count }}</div><small class="text-muted">Marketplace managed</small></div></div>
                </div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead><tr><th>Marketplace SKU</th><th>Internal product</th><th>Channel</th><th class="text-end">Marketplace</th><th class="text-end">Internal</th><th class="text-end">Variance</th><th>Status</th><th>Action</th></tr></thead>
                    <tbody>
                    @forelse($items as $item)
                        <tr>
                            <td class="fw-semibold">{{ $item->marketplace_sku }}</td>
                            <td>{{ $item->product?->name ?? 'No SKU mapping' }} @if($item->product)<small class="d-block text-muted">{{ $item->product->sku }}</small>@endif</td>
                            <td>{{ $item->fulfillment_channel }}</td>
                            <td class="text-end">{{ $item->marketplace_qty }}</td>
                            <td class="text-end">{{ $item->internal_qty === null ? '—' : $item->internal_qty }}</td>
                            <td class="text-end">{{ $item->variance === null ? '—' : ($item->variance > 0 ? '+' : '').$item->variance }}</td>
                            <td><span class="badge text-bg-{{ $item->status === 'MATCHED' ? 'success' : (in_array($item->status, ['MISMATCH','UNMAPPED']) ? 'warning' : 'secondary') }}">{{ str_replace('_', ' ', $item->status) }}</span></td>
                            <td>
                                @if($item->status === 'CORRECTION_REQUESTED')
                                    <small class="text-muted">Request #{{ $item->inventory_correction_request_id }} pending review</small>
                                @elseif($item->correction_eligible && $item->status === 'MISMATCH')
                                    <form method="POST" action="{{ route('marketplaces.inventory-reconciliation.correction', $item) }}" onsubmit="return confirm('Create a pending stock correction request? No stock will change until separately approved.');">
                                        @csrf <button class="btn btn-sm btn-outline-warning">Request correction</button>
                                    </form>
                                @elseif($item->status === 'MANAGED_CHANNEL')
                                    <small class="text-muted">Do not apply marketplace-managed quantity to internal stock</small>
                                @else
                                    <small class="text-muted">—</small>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-center text-muted py-4">No inventory snapshots found for this connection.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <div class="card-footer">{{ $items->links() }}</div>
        </div>
    @endif

    <div class="card">
        <div class="card-header fw-semibold">Recent reconciliation runs</div>
        <div class="list-group list-group-flush">
            @forelse($runs as $run)
                <a class="list-group-item list-group-item-action d-flex justify-content-between gap-2" href="{{ route('marketplaces.inventory-reconciliation', ['run' => $run->id]) }}">
                    <span>{{ $run->connection?->marketplace?->name ?? 'Marketplace' }} · {{ $run->created_at?->format('d M Y, H:i') }}</span>
                    <span class="text-muted">{{ $run->items_checked }} checked · {{ $run->mismatch_count }} mismatches</span>
                </a>
            @empty <div class="list-group-item text-muted">No reconciliation runs yet.</div> @endforelse
        </div>
        <div class="card-footer">{{ $runs->links() }}</div>
    </div>
</div>
@endsection
