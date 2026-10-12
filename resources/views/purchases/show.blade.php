@extends('layouts.app')
@section('title',$purchase->po_number)
@section('subtitle',$purchase->supplier->name.' · '.$purchase->purchase_date->format('d M Y'))
@section('content')
@php $badge=['DRAFT'=>'secondary','ORDERED'=>'info','RECEIVED'=>'success','CANCELLED'=>'dark']; $pay=['UNPAID'=>'danger','PARTIAL'=>'warning','PAID'=>'success']; @endphp
<div class="d-flex flex-wrap gap-2 align-items-center mb-3">
  <span class="badge text-bg-{{ $badge[$purchase->status] }} fs-6">{{ ucfirst(strtolower($purchase->status)) }}</span>
  <span class="badge text-bg-{{ $pay[$purchase->payment_status] }} fs-6">{{ ucfirst(strtolower($purchase->payment_status)) }}</span>
  <div class="ms-auto d-flex flex-wrap gap-2">
    @can('purchases.edit')
      @if($purchase->isEditable())
        <a href="{{ route('purchases.edit',$purchase) }}" class="btn btn-sm btn-outline-primary">Edit</a>
        <form method="POST" action="{{ route('purchases.receive',$purchase) }}" onsubmit="return confirm('Receive all items and add them to stock? This cannot be undone.')">@csrf<button class="btn btn-sm btn-success"><i class="bi bi-box-arrow-in-down"></i> Receive stock</button></form>
        <form method="POST" action="{{ route('purchases.cancel',$purchase) }}" onsubmit="return confirm('Cancel this purchase?')">@csrf<button class="btn btn-sm btn-outline-danger">Cancel</button></form>
      @endif
    @endcan
  </div>
</div>
<div class="row g-3">
  <div class="col-lg-8">
    <div class="card"><div class="table-responsive"><table class="table align-middle mb-0">
      <thead><tr><th>Product</th><th class="text-end">Qty</th><th class="text-end">Unit cost</th><th class="text-end">GST</th><th class="text-end">Line total</th><th class="text-end">Received</th></tr></thead><tbody>
      @foreach($purchase->items as $i)
        <tr><td><a class="text-decoration-none fw-semibold" href="{{ route('products.show',$i->product_id) }}">{{ $i->product->sku }}</a><div class="small text-muted">{{ $i->product->name }}</div></td>
          <td class="text-end">{{ $i->quantity }}</td><td class="text-end">₹{{ number_format($i->unit_cost,2) }}</td>
          <td class="text-end">{{ rtrim(rtrim((string)$i->gst_rate,'0'),'.') }}%</td><td class="text-end">₹{{ number_format($i->line_total,2) }}</td><td class="text-end">{{ $i->received_qty }}</td></tr>
      @endforeach</tbody></table></div></div>
    @if($purchase->notes)<div class="card mt-3"><div class="card-body"><b>Notes</b><div class="text-muted">{{ $purchase->notes }}</div></div></div>@endif
  </div>
  <div class="col-lg-4">
    <div class="card mb-3"><div class="card-body">
      @foreach(['Subtotal'=>$purchase->subtotal,'GST'=>$purchase->gst_amount,'Shipping'=>$purchase->shipping_charges,'Other charges'=>$purchase->other_charges] as $l=>$v)
        <div class="d-flex justify-content-between"><span class="text-muted">{{ $l }}</span><span>₹{{ number_format($v,2) }}</span></div>@endforeach
      <div class="d-flex justify-content-between fw-bold fs-5 border-top pt-2 mt-2"><span>Total</span><span>₹{{ number_format($purchase->total_amount,2) }}</span></div>
      <div class="d-flex justify-content-between"><span class="text-muted">Paid</span><span>₹{{ number_format($purchase->amount_paid,2) }}</span></div>
      <div class="d-flex justify-content-between fw-semibold"><span>Balance due</span><span>₹{{ number_format((float)$purchase->balance(),2) }}</span></div>
      <hr><div class="small text-muted">Invoice: {{ $purchase->invoice_number ?? '—' }}{{ $purchase->invoice_date ? ' · '.$purchase->invoice_date->format('d M Y') : '' }}<br>
        Created by {{ $purchase->creator->name ?? '—' }}@if($purchase->received_at)<br>Received {{ $purchase->received_at->format('d M Y H:i') }}@endif</div>
    </div></div>
    @can('purchases.edit')@if($purchase->status!=='CANCELLED' && (float)$purchase->balance()>0)
    <div class="card"><div class="card-header">Record payment</div><div class="card-body">
      <form method="POST" action="{{ route('purchases.payment',$purchase) }}" class="d-flex gap-2">@csrf
        <input type="number" name="amount" step="0.01" min="0.01" max="{{ $purchase->balance() }}" class="form-control" placeholder="Amount ₹" required>
        <button class="btn btn-primary">Add</button></form></div></div>
    @endif @endcan
  </div>
</div>
@endsection
