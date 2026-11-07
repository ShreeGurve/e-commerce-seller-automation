@extends('layouts.app')
@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3"><div><h1 class="h3 mb-1">Reservation Exception Audit</h1><p class="text-muted mb-0">Flags negative stock buckets or reservations exceeding available stock.</p></div><a class="btn btn-outline-primary" href="{{ route('inventory.reservations.export') }}">Export CSV</a></div>
    <div class="row g-3 mb-3"><div class="col-md-4"><div class="card card-body"><small class="text-muted">Exceptions</small><strong class="h3">{{ $summary['exceptions'] }}</strong></div></div><div class="col-md-4"><div class="card card-body"><small class="text-muted">Reserved Units in Exceptions</small><strong class="h3">{{ $summary['reserved'] }}</strong></div></div><div class="col-md-4"><div class="card card-body"><small class="text-muted">Available Units in Exceptions</small><strong class="h3">{{ $summary['available'] }}</strong></div></div></div>
    <div class="card"><div class="table-responsive"><table class="table table-hover mb-0"><thead><tr><th>Product</th><th>Available</th><th>Reserved</th><th>Unreserved Balance</th><th>Exception</th></tr></thead><tbody>
    @forelse($rows as $r)<tr><td>{{ $r->product?->name ?? 'Deleted product' }}<div class="small text-muted">{{ $r->product?->sku }}</div></td><td>{{ $r->available_qty }}</td><td>{{ $r->reserved_qty }}</td><td>{{ (int)$r->available_qty-(int)$r->reserved_qty }}</td><td><span class="badge bg-danger">{{ (int)$r->reserved_qty>(int)$r->available_qty ? 'Reserved exceeds available' : 'Negative stock bucket' }}</span></td></tr>
    @empty<tr><td colspan="5" class="text-center text-muted py-4">No reservation exceptions found.</td></tr>@endforelse
    </tbody></table></div></div>
</div>
@endsection
