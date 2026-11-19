@extends('layouts.app')

@section('content')
<div class="container-fluid py-3">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h4 class="mb-1">Marketplace Order Imports</h4>
            <p class="text-muted mb-0">Review imported marketplace orders before adding them to operations.</p>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif

    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead><tr><th>External Order</th><th>Marketplace</th><th>Order Date</th><th>Lines</th><th>Review Issues</th><th>Status</th><th class="text-end">Action</th></tr></thead>
                <tbody>
                @forelse($imports as $import)
                    @php($payload = $import->preview_payload ?? [])
                    <tr>
                        <td class="fw-semibold">{{ $import->external_order_id }}</td>
                        <td>{{ $import->connection?->marketplace?->name ?? 'Marketplace' }}<div class="small text-muted">{{ $import->connection?->account_label }}</div></td>
                        <td>{{ $payload['order_date'] ?? '—' }}</td>
                        <td>{{ count($payload['items'] ?? []) }}</td>
                        <td>
                            @if($import->validation_errors)
                                <span class="text-danger small">{{ count($import->validation_errors) }} issue(s)</span>
                                <ul class="small text-danger mb-0 ps-3">@foreach($import->validation_errors as $issue)<li>{{ $issue }}</li>@endforeach</ul>
                            @else
                                <span class="text-success small">Ready for review</span>
                            @endif
                        </td>
                        <td><span class="badge bg-secondary">{{ $import->status }}</span></td>
                        <td class="text-end">
                            @if($import->status === 'PENDING' && empty($import->validation_errors))
                                <form method="POST" action="{{ route('marketplaces.order-imports.approve', $import) }}" class="d-inline">
                                    @csrf
                                    <input type="hidden" name="deduct_stock" value="0">
                                    <button class="btn btn-sm btn-primary" onclick="return confirm('Import this order? Stock will not be deducted.')">Approve & Import</button>
                                </form>
                            @elseif($import->status === 'IMPORTED' && $import->order)
                                <a class="btn btn-sm btn-outline-secondary" href="{{ route('orders.show', $import->order) }}">View Order</a>
                            @endif
                            @if($import->status === 'PENDING')
                                <form method="POST" action="{{ route('marketplaces.order-imports.reject', $import) }}" class="d-inline">
                                    @csrf
                                    <input type="hidden" name="reason" value="Rejected during marketplace order review">
                                    <button class="btn btn-sm btn-outline-danger" onclick="return confirm('Reject this marketplace order import?')">Reject</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-muted py-5">No marketplace order imports are awaiting review.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer bg-white">{{ $imports->links() }}</div>
    </div>
</div>
@endsection
