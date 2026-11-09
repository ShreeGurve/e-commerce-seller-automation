@extends('layouts.app')
@section('content')
<div class="container-fluid">
    <div class="mb-3"><h1 class="h3 mb-1">Fulfillment Exception Monitor</h1><p class="text-muted mb-0">Highlights packed or shipped self-fulfilled orders with missing operational details. This page does not dispatch or update orders.</p></div>
    <div class="row g-3 mb-3">@foreach([['Exceptions',$summary['exceptions']],['Packed',$summary['packed']],['Shipped',$summary['shipped']]] as $m)<div class="col-md-4"><div class="card card-body"><small class="text-muted">{{ $m[0] }}</small><strong class="h3">{{ number_format($m[1]) }}</strong></div></div>@endforeach</div>
    <div class="card"><div class="table-responsive"><table class="table table-hover mb-0"><thead><tr><th>Order</th><th>Status</th><th>Carrier</th><th>Tracking</th><th>Invoice</th><th>Packed At</th><th>Missing Details</th></tr></thead><tbody>
    @forelse($orders as $o)@php($f=$o->fulfillment)@php($missing=[])@if(!$f)@php($missing[]='Fulfillment record')@else @if($o->status==='PACKED' && !$f->carrier)@php($missing[]='Carrier')@endif @if(!$f->tracking_number)@php($missing[]='Tracking number')@endif @if($o->status==='PACKED' && !$f->invoice_number)@php($missing[]='Invoice number')@endif @if($o->status==='PACKED' && !$f->packed_at)@php($missing[]='Packed timestamp')@endif @if($o->status==='SHIPPED' && !$o->shipped_at)@php($missing[]='Shipped timestamp')@endif @endif
    <tr><td><a href="{{ route('orders.show',$o) }}">{{ $o->order_number }}</a></td><td>{{ $o->status }}</td><td>{{ $f?->carrier ?? '—' }}</td><td>{{ $f?->tracking_number ?? '—' }}</td><td>{{ $f?->invoice_number ?? '—' }}</td><td>{{ $f?->packed_at?->format('d M Y H:i') ?? '—' }}</td><td>@foreach($missing as $m)<span class="badge bg-danger me-1">{{ $m }}</span>@endforeach</td></tr>
    @empty<tr><td colspan="7" class="text-center text-muted py-4">No fulfillment exceptions found.</td></tr>@endforelse</tbody></table></div></div>
</div>
@endsection
