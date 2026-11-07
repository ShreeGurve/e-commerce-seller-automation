@extends('layouts.app')
@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3"><div><h1 class="h3 mb-1">Inventory Inactivity Review</h1><p class="text-muted mb-0">Positive stock with no recorded movement within the selected period. This measures movement inactivity, not FIFO stock age.</p></div></div>
    <form class="mb-3"><label class="form-label">No-movement period</label><select name="days" class="form-select" style="max-width:220px" onchange="this.form.submit()">@foreach([30,60,90,180] as $d)<option value="{{ $d }}" @selected($days===$d)>{{ $d }} days</option>@endforeach</select></form>
    <div class="row g-3 mb-3"><div class="col-md-4"><div class="card card-body"><small class="text-muted">Products</small><strong class="h3">{{ $summary['products'] }}</strong></div></div><div class="col-md-4"><div class="card card-body"><small class="text-muted">Units in Inactive Products</small><strong class="h3">{{ number_format($summary['units']) }}</strong></div></div><div class="col-md-4"><div class="card card-body"><small class="text-muted">No Ledger History</small><strong class="h3">{{ $summary['no_history'] }}</strong></div></div></div>
    <div class="card"><div class="table-responsive"><table class="table table-hover mb-0"><thead><tr><th>Product</th><th>Available</th><th>Last Movement</th><th>Days Since Movement</th><th>Minimum Stock</th></tr></thead><tbody>
    @forelse($rows as $r)<tr><td>{{ $r->product?->name ?? 'Deleted product' }}<div class="small text-muted">{{ $r->product?->sku }}</div></td><td>{{ $r->available_qty }}</td><td>{{ $r->last_movement_at ? \Illuminate\Support\Carbon::parse($r->last_movement_at)->format('d M Y H:i') : 'No ledger history' }}</td><td>{{ $r->last_movement_at ? \Illuminate\Support\Carbon::parse($r->last_movement_at)->diffInDays(now()) : '—' }}</td><td>{{ $r->minimum_stock }}</td></tr>
    @empty<tr><td colspan="5" class="text-center text-muted py-4">No inactive inventory found for this period.</td></tr>@endforelse
    </tbody></table></div></div>
</div>
@endsection
