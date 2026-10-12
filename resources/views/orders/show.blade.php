@extends('layouts.app')
@section('title',$order->order_number)
@section('subtitle',$order->marketplace->name.' · '.$order->external_order_id.' · '.$order->order_date->format('d M Y H:i'))
@section('content')
@php
  $B=\App\Models\Order::BADGES; $idx=array_search($order->status,\App\Models\Order::PIPELINE);
  $refunded=(float)$order->refunds->sum('amount'); $canReturn=in_array($order->status,['SHIPPED','DELIVERED','RETURNED']);
  $manual=array_values(array_diff($order->nextStatuses(),['RETURNED','REFUNDED']));
@endphp
<div class="card mb-3"><div class="card-body">
  <div class="d-flex flex-wrap align-items-center gap-1 mb-3">
    @if($order->status==='CANCELLED')<span class="badge text-bg-danger fs-6">Cancelled</span>
    @else @foreach(\App\Models\Order::PIPELINE as $i=>$s)
      <span class="badge rounded-pill text-bg-{{ $idx!==false && $i<=$idx ? $B[$s] : 'light border' }}">{{ ucfirst(strtolower($s)) }}</span>@if(!$loop->last)<i class="bi bi-chevron-right text-muted small"></i>@endif
    @endforeach @endif
  </div>
  <div class="d-flex flex-wrap gap-2">
    @can('orders.edit') @foreach($manual as $s)
      <form method="POST" action="{{ route('orders.status',$order) }}" @if($s==='CANCELLED') onsubmit="return confirm('Cancel order and restock items?')" @endif>@csrf<input type="hidden" name="to" value="{{ $s }}">
        <button class="btn btn-sm {{ $s==='CANCELLED'?'btn-outline-danger':'btn-primary' }}">{{ $s==='CANCELLED'?'Cancel order':'Mark '.strtolower($s) }}</button></form>
    @endforeach @endcan
    @can('returns.manage')@if($canReturn)<a href="{{ route('orders.returns.create',$order) }}" class="btn btn-sm btn-outline-warning"><i class="bi bi-arrow-return-left"></i> Create return</a>@endif @endcan
  </div>
</div></div>
<div class="row g-3">
  <div class="col-lg-8">
    <div class="card mb-3"><div class="table-responsive"><table class="table align-middle mb-0">
      <thead><tr><th>Product</th><th class="text-end">Qty</th><th class="text-end">Unit price</th><th class="text-end">Line total</th><th class="text-end">Cost/unit*</th><th class="text-end">Gross margin**</th></tr></thead><tbody>
      @foreach($order->items as $i) @php $m=(float)$i->line_total-(float)$i->tax_amount-$i->unitCost()*$i->quantity; @endphp
        <tr><td><a class="fw-semibold text-decoration-none" href="{{ route('products.show',$i->product_id) }}">{{ $i->product->sku }}</a><div class="small text-muted">{{ $i->product->name }}</div></td>
          <td class="text-end">{{ $i->quantity }}</td><td class="text-end">₹{{ number_format($i->unit_price,2) }}@if((float)$i->discount>0)<div class="small text-danger">−₹{{ number_format($i->discount,2) }}</div>@endif</td>
          <td class="text-end">₹{{ number_format($i->line_total,2) }}<div class="small text-muted">GST ₹{{ number_format($i->tax_amount,2) }}</div></td>
          <td class="text-end">₹{{ number_format($i->unitCost(),2) }}</td>
          <td class="text-end fw-semibold {{ $m<0?'text-danger':'text-success' }}">₹{{ number_format($m,2) }}</td></tr>
      @endforeach</tbody></table></div>
      <div class="card-footer small text-muted">* Cost in force on the order date (COGS + packaging + other), saved on the order. ** Before marketplace fees, ads and returns. The full profit engine comes in Phase 4.</div></div>

    <div class="card mb-3"><div class="card-header d-flex justify-content-between"><span>Returns</span></div>
      <ul class="list-group list-group-flush">@forelse($order->returns as $r)
        <li class="list-group-item d-flex justify-content-between"><a class="text-decoration-none fw-semibold" href="{{ route('returns.show',$r) }}">{{ $r->return_number }}</a>
          <span>{{ \App\Models\ProductReturn::TYPES[$r->return_type] }} · <span class="badge text-bg-secondary">{{ ucfirst(strtolower($r->status)) }}</span></span></li>
      @empty<li class="list-group-item text-muted">No returns.</li>@endforelse</ul></div>

    <div class="card mb-3"><div class="card-header d-flex justify-content-between"><span>Claims</span>@can('claims.create')<a href="{{ route('claims.create',['order_id'=>$order->id]) }}" class="small text-decoration-none">+ New claim</a>@endcan</div>
      <ul class="list-group list-group-flush">@forelse($order->claims as $cl)
        <li class="list-group-item d-flex justify-content-between"><a class="text-decoration-none fw-semibold" href="{{ route('claims.show',$cl) }}">{{ $cl->claim_number }}</a>
          <span>{{ \App\Models\Claim::TYPES[$cl->claim_type] }} · <span class="badge text-bg-{{ \App\Models\Claim::BADGES[$cl->status] }}">{{ \App\Models\Claim::LABELS[$cl->status] }}</span> · ₹{{ number_format($cl->claim_amount,2) }}</span></li>
      @empty<li class="list-group-item text-muted">No claims.</li>@endforelse</ul></div>

    <div class="card"><div class="card-header">Refunds</div>
      <ul class="list-group list-group-flush">@forelse($order->refunds as $f)
        <li class="list-group-item d-flex justify-content-between"><span>{{ $f->refund_date->format('d M Y') }} <span class="text-muted">{{ $f->reason }}</span></span><b>₹{{ number_format($f->amount,2) }}</b></li>
      @empty<li class="list-group-item text-muted">No refunds.</li>@endforelse</ul>
      @can('orders.edit')@if(in_array($order->status,['SHIPPED','DELIVERED','RETURNED','REFUNDED','CLOSED']) && $refunded < (float)$order->total_amount)
      <div class="card-body border-top"><form method="POST" action="{{ route('orders.refunds.store',$order) }}" class="row g-2">@csrf
        <div class="col-6 col-md-3"><input type="number" step="0.01" min="0.01" max="{{ (float)$order->total_amount-$refunded }}" name="amount" class="form-control" placeholder="Amount ₹" required></div>
        <div class="col-6 col-md-3"><input type="date" name="refund_date" value="{{ now()->toDateString() }}" max="{{ now()->toDateString() }}" class="form-control" required></div>
        <div class="col-md-3"><select name="return_id" class="form-select"><option value="">No return linked</option>@foreach($order->returns as $r)<option value="{{ $r->id }}">{{ $r->return_number }}</option>@endforeach</select></div>
        <div class="col-md-3"><input name="reason" class="form-control" placeholder="Reason"></div>
        <div class="col-md-9"><input name="external_refund_id" class="form-control" placeholder="Marketplace refund id (optional)"></div>
        <div class="col-md-3"><button class="btn btn-primary w-100">Record refund</button></div></form></div>@endif @endcan
    </div>
  </div>
  <div class="col-lg-4">
    <div class="card mb-3"><div class="card-body">
      <div class="d-flex justify-content-between"><span class="text-muted">Items (after discount)</span><span>₹{{ number_format($order->subtotal,2) }}</span></div>
      <div class="d-flex justify-content-between"><span class="text-muted">Shipping charged</span><span>₹{{ number_format($order->shipping_charged,2) }}</span></div>
      <div class="d-flex justify-content-between fw-bold fs-5 border-top pt-2 mt-2"><span>Total</span><span>₹{{ number_format($order->total_amount,2) }}</span></div>
      <div class="d-flex justify-content-between"><span class="text-muted">GST included</span><span>₹{{ number_format($order->tax_amount,2) }}</span></div>
      <div class="d-flex justify-content-between text-warning"><span>Refunded</span><span>₹{{ number_format($refunded,2) }}</span></div>
      <hr><div class="small text-muted">{{ \App\Models\Order::FULFILLMENT[$order->fulfillment_type] }}<br>Ref: {{ $order->customer_ref ?? '—' }} · State: {{ $order->ship_state ?? '—' }}<br>Source: {{ $order->source }} · by {{ $order->creator->name ?? '—' }}</div>
      @if($order->notes)<hr><div class="small">{{ $order->notes }}</div>@endif
    </div></div>
    @if($order->profits->count()) @php $pr=$order->profits; $pp=(float)$pr->sum(fn($x)=>(float)($x->actual_profit ?? $x->estimated_profit)); @endphp
    <div class="card mb-3"><div class="card-header d-flex justify-content-between"><span>Profit</span><span class="badge text-bg-{{ $pr->every(fn($x)=>$x->actual_profit!==null)?'success':'secondary' }}">{{ $pr->every(fn($x)=>$x->actual_profit!==null)?'Actual':'Estimated' }}</span></div><div class="card-body small">
      @foreach(['Revenue (ex-GST)'=>[$pr->sum('revenue'),1],'COGS & packaging'=>[$pr->sum('cogs'),-1],'Marketplace fees'=>[$pr->sum('fees'),-1],'Refunds (ex-GST)'=>[$pr->sum('return_loss'),-1]] as $l=>[$v,$sg])
        <div class="d-flex justify-content-between"><span class="text-muted">{{ $sg<0?'−':'' }} {{ $l }}</span><span>₹{{ number_format((float)$v,2) }}</span></div>@endforeach
      <div class="d-flex justify-content-between fw-bold fs-5 border-top pt-2 mt-2 {{ $pp<0?'text-danger':'text-success' }}"><span>Profit</span><span>₹{{ number_format($pp,2) }}</span></div></div></div>
    @endif
    @if($order->settlementItems->count() || $order->recon_status!=='PENDING')
    <div class="card mb-3"><div class="card-header d-flex justify-content-between"><span>Payout lines</span>
      <span class="badge text-bg-{{ ['MATCHED'=>'success','MISMATCH'=>'danger'][$order->recon_status] ?? 'secondary' }}">{{ ucfirst(strtolower($order->recon_status)) }}</span></div>
      <ul class="list-group list-group-flush small">@forelse($order->settlementItems as $si)
        <li class="list-group-item d-flex justify-content-between"><span>{{ \App\Models\SettlementItem::KINDS[$si->kind] ?? $si->kind }} <span class="text-muted">{{ $si->posted_date?->format('d M') }}</span></span>
          <b class="{{ (float)$si->amount<0?'text-danger':'' }}">₹{{ number_format($si->amount,2) }}</b></li>
      @empty<li class="list-group-item text-muted">No payout yet.</li>@endforelse
      @if($order->settlementItems->count())<li class="list-group-item d-flex justify-content-between fw-bold"><span>Net received</span><span>₹{{ number_format((float)$order->settlementItems->sum('amount'),2) }}</span></li>@endif</ul></div>
    @endif
    <div class="card"><div class="card-header">Status history</div><ul class="list-group list-group-flush small">
      @foreach($order->history as $h)<li class="list-group-item"><b>{{ ucfirst(strtolower($h->to_status)) }}</b> <span class="text-muted">{{ $h->created_at->format('d M H:i') }} · {{ $h->user->name ?? 'system' }}</span>@if($h->note)<div class="text-muted">{{ $h->note }}</div>@endif</li>@endforeach</ul></div>
  </div>
</div>
@endsection
