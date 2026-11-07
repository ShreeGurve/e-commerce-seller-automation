@extends('layouts.app')
@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div><h1 class="h3 mb-1">Controlled Stock Corrections</h1><p class="text-muted mb-0">Count variances require review before stock is changed.</p></div>
        <a href="{{ route('inventory.index') }}" class="btn btn-outline-secondary">Back to Inventory</a>
    </div>
    @if(session('success')) <div class="alert alert-success">{{ session('success') }}</div> @endif
    @if($errors->any()) <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div> @endif
    <div class="card"><div class="table-responsive"><table class="table table-hover align-middle mb-0">
        <thead><tr><th>Product</th><th>System</th><th>Counted</th><th>Variance</th><th>Requested By</th><th>Status</th><th>Review</th></tr></thead>
        <tbody>
        @forelse($requests as $item)
            <tr>
                <td><strong>{{ $item->product?->name ?? 'Deleted product' }}</strong><div class="small text-muted">{{ $item->product?->sku }}</div></td>
                <td>{{ $item->system_qty }}</td><td>{{ $item->counted_qty }}</td>
                <td class="{{ $item->variance < 0 ? 'text-danger' : 'text-success' }}">{{ $item->variance > 0 ? '+' : '' }}{{ $item->variance }}</td>
                <td>{{ $item->requester?->name ?? '—' }}<div class="small text-muted">{{ $item->created_at?->format('d M Y H:i') }}</div></td>
                <td><span class="badge bg-{{ $item->status === 'applied' ? 'success' : ($item->status === 'pending' ? 'warning text-dark' : 'secondary') }}">{{ ucfirst($item->status) }}</span></td>
                <td style="min-width:250px">
                    @if($item->status === 'pending')
                        <form method="POST" action="{{ route('inventory.corrections.approve', $item) }}" class="mb-2">@csrf
                            <input name="review_note" class="form-control form-control-sm mb-1" placeholder="Optional approval note">
                            <button class="btn btn-sm btn-success" onclick="return confirm('Apply this stock correction to the inventory ledger?')">Approve & Apply</button>
                        </form>
                        <form method="POST" action="{{ route('inventory.corrections.reject', $item) }}">@csrf
                            <input name="review_note" class="form-control form-control-sm mb-1" required placeholder="Reason for rejection">
                            <button class="btn btn-sm btn-outline-danger">Reject</button>
                        </form>
                    @else
                        <div class="small">{{ $item->reviewer?->name ?? '—' }} {{ $item->review_note ? '· '.$item->review_note : '' }}</div>
                    @endif
                </td>
            </tr>
        @empty <tr><td colspan="7" class="text-center text-muted py-4">No correction requests found.</td></tr> @endforelse
        </tbody>
    </table></div></div>
    <div class="mt-3">{{ $requests->links() }}</div>
</div>
@endsection
