@extends('layouts.app')
@section('title', 'Delivery Aging & Delay Tracker')
@section('subtitle', 'Monitor elapsed time from shipment to delivery for self-fulfilled orders')
@section('content')
<div class="card mb-3"><div class="card-body"><form class="d-flex flex-wrap align-items-end gap-2"><div><label class="form-label">Flag as overdue after</label><select class="form-select" name="days">@foreach([2,3,5,7,10,14] as $day)<option value="{{ $day }}" @selected($threshold === $day)>{{ $day }} days</option>@endforeach</select></div><button class="btn btn-primary">Apply</button></form></div></div>
<div class="alert alert-info">Overdue means an order remains in SHIPPED status for at least {{ $threshold }} days. Delivered orders show their actual elapsed delivery duration.</div>
<div class="card"><div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th>Order</th><th>Channel</th><th>Shipped at</th><th>Status</th><th>Elapsed</th><th>Delivery duration</th><th>Signal</th></tr></thead><tbody>
@forelse($orders as $order)<tr><td><a href="{{ route('orders.show', $order) }}">{{ $order->order_number }}</a></td><td>{{ $order->marketplace?->name }}</td><td>{{ $order->shipped_at?->format('d M Y H:i') }}</td><td><span class="badge text-bg-{{ \App\Models\Order::BADGES[$order->status] ?? 'secondary' }}">{{ $order->status }}</span></td><td>{{ $order->delivery_age_days }} days</td><td>{{ $order->delivery_duration_days !== null ? $order->delivery_duration_days.' days' : 'Pending' }}</td><td>@if($order->delivery_overdue)<span class="badge text-bg-danger">Overdue</span>@elseif($order->delivered_at)<span class="badge text-bg-success">Delivered</span>@else<span class="badge text-bg-info">Within threshold</span>@endif</td></tr>
@empty<tr><td colspan="7" class="text-center text-muted py-4">No shipped self-fulfilled orders found.</td></tr>@endforelse</tbody></table></div><div class="card-footer">{{ $orders->links() }}</div></div>
@endsection
