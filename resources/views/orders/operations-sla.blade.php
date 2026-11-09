@extends('layouts.app')
@section('content')
<div class="container-fluid">
    <div class="mb-3"><h1 class="h3 mb-1">Order Processing SLA Monitor</h1><p class="text-muted mb-0">Aging view for self-fulfilled orders still awaiting confirmation, packing or dispatch. Age is measured from order date.</p></div>
    <div class="row g-3 mb-3">
        @foreach([['Overdue',$summary['overdue']],['Imported',$summary['imported']],['Confirmed',$summary['confirmed']],['Packed',$summary['packed']]] as $m)<div class="col-md-3"><div class="card card-body"><small class="text-muted">{{ $m[0] }}</small><strong class="h3">{{ number_format($m[1]) }}</strong></div></div>@endforeach
    </div>
    <form class="mb-3"><label class="form-label">Minimum order age</label><select name="age_days" class="form-select" style="max-width:220px" onchange="this.form.submit()">@foreach([1,2,3,5,7] as $d)<option value="{{ $d }}" @selected($days===$d)>{{ $d }} day(s)</option>@endforeach</select></form>
    <div class="card"><div class="table-responsive"><table class="table table-hover mb-0"><thead><tr><th>Order</th><th>Marketplace</th><th>Order Date</th><th>Current Stage</th><th>Age (days)</th><th>Fulfillment</th></tr></thead><tbody>
    @forelse($orders as $o)<tr><td><a href="{{ route('orders.show',$o) }}">{{ $o->order_number }}</a><div class="small text-muted">{{ $o->external_order_id }}</div></td><td>{{ $o->marketplace?->name ?? '—' }}</td><td>{{ $o->order_date?->format('d M Y H:i') }}</td><td><span class="badge bg-warning text-dark">{{ $o->status }}</span></td><td>{{ $o->order_date?->diffInDays(now()) }}</td><td>{{ $o->fulfillment?->carrier ?? 'Not assigned' }}</td></tr>
    @empty<tr><td colspan="6" class="text-center text-muted py-4">No overdue self-fulfilled orders for this threshold.</td></tr>@endforelse</tbody></table></div></div><div class="mt-3">{{ $orders->links() }}</div>
</div>
@endsection
