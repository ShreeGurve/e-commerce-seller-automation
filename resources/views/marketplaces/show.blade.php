@extends('layouts.app')
@section('title',$mp->name)
@section('subtitle','Last 30 days · '.($adapter->supportsApi() ? 'API connected' : 'CSV report mode (API not connected)'))
@section('content')
<div class="d-flex flex-wrap gap-2 mb-3">
  <a href="{{ route('imports.create',['marketplace_id'=>$mp->id,'type'=>'ORDERS']) }}" class="btn btn-primary"><i class="bi bi-cloud-upload"></i> Import orders</a>
  <a href="{{ route('imports.create',['marketplace_id'=>$mp->id,'type'=>'RETURNS']) }}" class="btn btn-outline-primary">Import returns</a>
  <a href="{{ route('imports.create',['marketplace_id'=>$mp->id,'type'=>'MAPPINGS']) }}" class="btn btn-outline-primary">Import SKU mappings</a>
  @can('advertising.manage')<a href="{{ route('imports.create',['marketplace_id'=>$mp->id,'type'=>'ADS_PRODUCT']) }}" class="btn btn-outline-primary">Import ad report</a>@endcan
  @can('finance.manage')<a href="{{ route('imports.create',['marketplace_id'=>$mp->id,'type'=>'SETTLEMENTS']) }}" class="btn btn-outline-primary">Import settlements</a>@endcan
</div>
<div class="row g-3 mb-3">
  @foreach([['Orders',$orders30,'t-blue','bi-receipt'],['Sales (incl. GST)','₹'.number_format($sales30,2),'t-green','bi-cash-stack'],['Units sold',$units30,'t-blue','bi-box-seam'],['Return rate',$returnRate.'% ('.$returns30.')','t-orange','bi-arrow-return-left']] as [$l,$v,$c,$i])
  <div class="col-6 col-xl-3"><div class="card"><div class="card-body d-flex align-items-center gap-3"><div class="sg-tile {{ $c }}"><i class="bi {{ $i }}"></i></div><div><div class="small text-muted">{{ $l }}</div><div class="fw-bold fs-5">{{ $v }}</div></div></div></div></div>
  @endforeach
</div>
<div class="row g-3">
  <div class="col-lg-7"><div class="card h-100"><div class="card-header">Recent orders</div><ul class="list-group list-group-flush">
    @forelse($recentOrders as $o)<li class="list-group-item d-flex justify-content-between"><a class="text-decoration-none" href="{{ route('orders.show',$o) }}">{{ $o->order_number }} <span class="text-muted small">{{ $o->external_order_id }}</span></a>
      <span><span class="badge text-bg-{{ \App\Models\Order::BADGES[$o->status] }}">{{ ucfirst(strtolower($o->status)) }}</span> <b class="ms-2">₹{{ number_format($o->total_amount,2) }}</b></span></li>
    @empty<li class="list-group-item text-muted">No orders yet. Import a report to start.</li>@endforelse</ul></div></div>
  <div class="col-lg-5">
    <div class="card mb-3"><div class="card-header">SKU mapping</div><div class="card-body"><div class="d-flex justify-content-between"><span>Mapped SKUs</span><b>{{ $mapped }}</b></div>
      <div class="d-flex justify-content-between"><span>Active products not mapped here</span><b class="{{ $unmapped?'text-warning':'' }}">{{ $unmapped }}</b></div>
      <div class="small text-muted mt-2">Orders with an unknown SKU are rejected until the SKU is mapped (or matches your own SKU).</div></div></div>
    <div class="card"><div class="card-header">Recent imports</div><ul class="list-group list-group-flush small">
      @forelse($recentImports as $i)<li class="list-group-item d-flex justify-content-between"><a class="text-decoration-none" href="{{ route('imports.show',$i) }}">{{ $i->file_name }}</a><span class="text-muted">{{ $i->typeLabel() }} · {{ ucfirst(strtolower($i->status)) }}</span></li>
      @empty<li class="list-group-item text-muted">No imports yet.</li>@endforelse</ul></div>
  </div>
</div>
@endsection
