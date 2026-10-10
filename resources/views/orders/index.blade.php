@extends('layouts.app')
@section('title','Orders')
@section('subtitle','All marketplace orders in one place')
@section('content')
<div class="d-flex flex-wrap gap-2 mb-3">
  <a href="{{ route('orders.index') }}" class="badge rounded-pill text-bg-{{ request('status')?'light border':'dark' }} fs-6 text-decoration-none">All</a>
  @foreach(\App\Models\Order::STATUSES as $s)
    <a href="{{ route('orders.index',['status'=>$s]) }}" class="badge rounded-pill text-bg-{{ request('status')===$s ? \App\Models\Order::BADGES[$s] : 'light border' }} fs-6 text-decoration-none">{{ ucfirst(strtolower($s)) }} {{ $counts[$s] ?? 0 }}</a>
  @endforeach
</div>
<div class="card mb-3"><div class="card-body"><form class="row g-2" method="GET">
  <input type="hidden" name="status" value="{{ request('status') }}">
  <div class="col-md-3"><input name="q" value="{{ request('q') }}" class="form-control" placeholder="Order no. / marketplace id / ref"></div>
  <div class="col-md-2"><select name="marketplace_id" class="form-select"><option value="">All channels</option>@foreach($marketplaces as $m)<option value="{{ $m->id }}" @selected(request('marketplace_id')==$m->id)>{{ $m->name }}</option>@endforeach</select></div>
  <div class="col-6 col-md-2"><input type="date" name="from" value="{{ request('from') }}" class="form-control"></div>
  <div class="col-6 col-md-2"><input type="date" name="to" value="{{ request('to') }}" class="form-control"></div>
  <div class="col-md-3 d-flex gap-2"><button class="btn btn-outline-primary">Filter</button>
    @can('orders.edit')<a href="{{ route('orders.create') }}" class="btn btn-primary ms-auto"><i class="bi bi-plus-lg"></i> New order</a>@endcan</div>
</form></div></div>
<div class="card"><div class="table-responsive"><table class="table table-hover align-middle mb-0">
  <thead><tr><th>Order</th><th>Date</th><th>Channel</th><th>Marketplace ID</th><th class="text-end">Items</th><th class="text-end">Total</th><th>Status</th></tr></thead><tbody>
  @forelse($orders as $o)
    <tr><td><a class="fw-semibold text-decoration-none" href="{{ route('orders.show',$o) }}">{{ $o->order_number }}</a></td>
      <td class="text-nowrap">{{ $o->order_date->format('d M Y H:i') }}</td><td>{{ $o->marketplace->name }}</td><td class="text-muted">{{ $o->external_order_id }}</td>
      <td class="text-end">{{ $o->items_count }}</td><td class="text-end">₹{{ number_format($o->total_amount,2) }}</td>
      <td><span class="badge text-bg-{{ \App\Models\Order::BADGES[$o->status] }}">{{ ucfirst(strtolower($o->status)) }}</span></td></tr>
  @empty<tr><td colspan="7" class="text-center text-muted py-5">No orders found.</td></tr>@endforelse
  </tbody></table></div>
  @if($orders->hasPages())<div class="card-footer">{{ $orders->links() }}</div>@endif
</div>
@endsection
