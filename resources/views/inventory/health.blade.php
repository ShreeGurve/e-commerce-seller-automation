@extends('layouts.app')
@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3"><div><h1 class="h3 mb-1">Inventory Health Overview</h1><p class="text-muted mb-0">Combined exception and replenishment checks from current inventory balances.</p></div><a href="{{ route('inventory.health.export') }}" class="btn btn-outline-primary">Export CSV</a></div>
    <div class="row g-3 mb-3">
        @foreach([['Products Checked',$summary['checked']],['Flagged Products',$summary['exceptions']],['Negative Buckets',$summary['negative']],['Reservation Exceptions',$summary['reservation']],['Low Stock',$summary['low']]] as $metric)
            <div class="col-md"><div class="card card-body"><small class="text-muted">{{ $metric[0] }}</small><strong class="h3">{{ number_format($metric[1]) }}</strong></div></div>
        @endforeach
    </div>
    <div class="card"><div class="table-responsive"><table class="table table-hover mb-0"><thead><tr><th>Product</th><th>Available</th><th>Damaged</th><th>Reserved</th><th>Min</th><th>Reorder</th><th>Flags</th></tr></thead><tbody>
    @forelse($rows as $r)@php($flags=[])@if((int)$r->available_qty<0)@php($flags[]='Negative available')@endif @if((int)$r->damaged_qty<0)@php($flags[]='Negative damaged')@endif @if((int)$r->reserved_qty<0)@php($flags[]='Negative reserved')@endif @if((int)$r->reserved_qty>(int)$r->available_qty)@php($flags[]='Reserved > available')@endif @if((int)$r->minimum_stock>0 && (int)$r->available_qty<=(int)$r->minimum_stock)@php($flags[]='Below minimum')@endif @if((int)$r->reorder_level>0 && (int)$r->available_qty<=(int)$r->reorder_level)@php($flags[]='At reorder level')@endif
    <tr><td>{{ $r->product?->name ?? 'Deleted product' }}<div class="small text-muted">{{ $r->product?->sku }}</div></td><td>{{ $r->available_qty }}</td><td>{{ $r->damaged_qty }}</td><td>{{ $r->reserved_qty }}</td><td>{{ $r->minimum_stock }}</td><td>{{ $r->reorder_level }}</td><td>@foreach($flags as $f)<span class="badge bg-warning text-dark me-1">{{ $f }}</span>@endforeach</td></tr>
    @empty<tr><td colspan="7" class="text-center text-muted py-4">No inventory health flags found.</td></tr>@endforelse
    </tbody></table></div></div>
</div>
@endsection
