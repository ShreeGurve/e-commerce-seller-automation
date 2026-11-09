@extends('layouts.app')
@section('content')
<div class="container-fluid">
    <div class="mb-3"><h1 class="h3 mb-1">Return Intake Monitor</h1><p class="text-muted mb-0">Tracks expected versus received quantities for open returns. Does not inspect, restock or close returns.</p></div>
    <div class="row g-3 mb-3">@foreach([['Open Returns',$summary['open_returns']],['Expected Units',$summary['expected_units']],['Received Units',$summary['received_units']],['Units Not Yet Received',$summary['unreceived_units']]] as $m)<div class="col-md-3"><div class="card card-body"><small class="text-muted">{{ $m[0] }}</small><strong class="h3">{{ number_format($m[1]) }}</strong></div></div>@endforeach</div>
    <div class="card"><div class="table-responsive"><table class="table table-hover mb-0"><thead><tr><th>Return</th><th>Order</th><th>Marketplace</th><th>Return Date</th><th>Status</th><th>Expected</th><th>Received</th><th>Outstanding</th><th>Reason</th></tr></thead><tbody>
    @forelse($returns as $r)@php($expected=(int)$r->items->sum('quantity'))@php($received=(int)$r->items->sum('received_qty'))<tr><td>{{ $r->return_number }}</td><td><a href="{{ route('orders.show',$r->order) }}">{{ $r->order?->order_number }}</a></td><td>{{ $r->marketplace?->name }}</td><td>{{ $r->return_date?->format('d M Y') }}</td><td>{{ $r->status }}</td><td>{{ $expected }}</td><td>{{ $received }}</td><td>{{ max(0,$expected-$received) }}</td><td>{{ $r->reason_text ?? \App\Models\ProductReturn::TYPES[$r->return_type] ?? '—' }}</td></tr>
    @empty<tr><td colspan="9" class="text-center text-muted py-4">No open returns.</td></tr>@endforelse</tbody></table></div></div><div class="mt-3">{{ $returns->links() }}</div>
</div>
@endsection
