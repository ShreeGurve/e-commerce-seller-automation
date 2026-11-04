@extends('layouts.app')
@section('title', 'Fulfilment Queue')
@section('subtitle', 'Pack, dispatch and record carrier pickup for marketplace orders')
@section('content')
<div class="card mb-3"><div class="card-body"><form method="GET" class="row g-2 align-items-end">
<div class="col-md-5"><label class="form-label">Order / marketplace ID</label><input class="form-control" name="q" value="{{ request('q') }}" placeholder="Search order"></div>
<div class="col-md-3"><label class="form-label">Status</label><select class="form-select" name="status"><option value="">All active</option>@foreach(['CONFIRMED','PACKED','SHIPPED'] as $s)<option value="{{ $s }}" @selected(request('status')===$s)>{{ ucfirst(strtolower($s)) }}</option>@endforeach</select></div>
<div class="col-md-2"><button class="btn btn-primary w-100">Filter</button></div><div class="col-md-2"><a href="{{ route('fulfilment.index') }}" class="btn btn-outline-secondary w-100">Reset</a></div>
</form></div></div>
<div class="card"><div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th>Order</th><th>Marketplace</th><th>Order date</th><th>Fulfilment</th><th>Status</th><th>Carrier / tracking</th><th></th></tr></thead><tbody>
@forelse($orders as $order)<tr><td><a href="{{ route('orders.show',$order) }}" class="fw-semibold">{{ $order->order_number }}</a><div class="small text-muted">{{ $order->external_order_id }}</div></td><td>{{ $order->marketplace->name }}</td><td>{{ $order->order_date->format('d M Y') }}</td><td>{{ \App\Models\Order::FULFILLMENT[$order->fulfillment_type] ?? $order->fulfillment_type }}</td><td><span class="badge text-bg-{{ \App\Models\Order::BADGES[$order->status] ?? 'secondary' }}">{{ $order->status }}</span></td><td>{{ $order->fulfillment?->carrier ?: '—' }}<div class="small text-muted">{{ $order->fulfillment?->tracking_number ?: 'No tracking yet' }}</div></td><td><a class="btn btn-sm btn-outline-primary" href="{{ route('orders.show',$order) }}">Manage</a></td></tr>
@empty<tr><td colspan="7" class="text-center text-muted py-5">No orders waiting for fulfilment.</td></tr>@endforelse
</tbody></table></div><div class="card-footer">{{ $orders->links() }}</div></div>
<div class="alert alert-info mt-3 mb-0 small">This is an internal fulfilment workflow. It does not request labels, book pickups or update Amazon / Flipkart. Enter carrier and tracking details received from your marketplace or courier.</div>
@endsection
