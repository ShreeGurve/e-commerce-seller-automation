@extends('layouts.app')
@section('title','New order')
@section('subtitle','Manual entry. Stock is deducted and today\'s costs are saved on the order.')
@section('content')
@php
  $rows = old('items', [['product_id'=>'','quantity'=>1,'unit_price'=>'','discount'=>'']]);
  $opts = $products->map(fn($p)=>['id'=>$p->id,'label'=>$p->sku.' — '.$p->name,'price'=>(float)($p->default_selling_price ?? $p->mrp ?? 0)])->values();
@endphp
<div class="card"><div class="card-body"><form method="POST" action="{{ route('orders.store') }}">@csrf
  <div class="row g-3 mb-3">
    <div class="col-md-3"><label class="form-label">Channel *</label><select name="marketplace_id" class="form-select" required>@foreach($marketplaces as $m)<option value="{{ $m->id }}" @selected(old('marketplace_id')==$m->id)>{{ $m->name }}</option>@endforeach</select></div>
    <div class="col-md-3"><label class="form-label">Marketplace order ID *</label><input name="external_order_id" value="{{ old('external_order_id') }}" class="form-control" required></div>
    <div class="col-md-3"><label class="form-label">Order date *</label><input type="datetime-local" name="order_date" value="{{ old('order_date', now()->format('Y-m-d\TH:i')) }}" max="{{ now()->format('Y-m-d\TH:i') }}" class="form-control" required></div>
    <div class="col-md-3"><label class="form-label">Status</label><select name="status" class="form-select"><option value="CONFIRMED">Confirmed</option><option value="IMPORTED" @selected(old('status')==='IMPORTED')>Imported (review later)</option></select></div>
    <div class="col-md-4"><label class="form-label">Fulfilment</label><select name="fulfillment_type" class="form-select">@foreach(\App\Models\Order::FULFILLMENT as $k=>$l)<option value="{{ $k }}" @selected(old('fulfillment_type')===$k)>{{ $l }}</option>@endforeach</select></div>
    <div class="col-md-3"><label class="form-label">Customer / order ref</label><input name="customer_ref" value="{{ old('customer_ref') }}" class="form-control"></div>
    <div class="col-md-3"><label class="form-label">Ship-to state</label><input name="ship_state" value="{{ old('ship_state') }}" class="form-control"></div>
    <div class="col-md-2"><label class="form-label">Shipping charged ₹</label><input type="number" step="0.01" min="0" name="shipping_charged" id="ship" value="{{ old('shipping_charged',0) }}" class="form-control"></div>
  </div>
  <h6 class="border-bottom pb-1">Items <span class="text-muted fw-normal small">(price = selling price per unit, GST inclusive)</span></h6>
  <div class="table-responsive"><table class="table align-middle" id="it"><thead><tr><th style="min-width:260px">Product</th><th style="width:100px">Qty</th><th style="width:140px">Unit price ₹</th><th style="width:130px">Discount ₹</th><th class="text-end" style="width:130px">Line total</th><th style="width:44px"></th></tr></thead><tbody></tbody></table></div>
  <button type="button" class="btn btn-sm btn-outline-primary mb-3" id="add"><i class="bi bi-plus-lg"></i> Add product</button>
  <div class="row"><div class="col-md-7"><label class="form-label">Notes</label><textarea name="notes" rows="3" class="form-control">{{ old('notes') }}</textarea></div>
    <div class="col-md-5"><div class="card"><div class="card-body"><div class="d-flex justify-content-between fw-bold fs-5"><span>Order total</span><span id="tot">₹0.00</span></div><div class="small text-muted">Items + shipping. Preview only.</div></div></div></div></div>
  <div class="mt-4"><button class="btn btn-primary">Create order</button> <a href="{{ route('orders.index') }}" class="btn btn-link">Cancel</a></div>
</form></div></div>
@push('scripts')
<script>
(function(){
  const OPTS=@json($opts), ROWS=@json($rows), tb=document.querySelector('#it tbody'); let n=0;
  const money=v=>'₹'+(Math.round(v*100)/100).toLocaleString('en-IN',{minimumFractionDigits:2,maximumFractionDigits:2});
  function add(d){d=d||{};const i=n++,tr=document.createElement('tr');
    tr.innerHTML=`<td><select class="form-select" name="items[${i}][product_id]" required><option value="">Select product…</option>${OPTS.map(o=>`<option value="${o.id}" ${String(d.product_id)===String(o.id)?'selected':''}>${o.label.replace(/</g,'&lt;')}</option>`).join('')}</select></td>
    <td><input type="number" min="1" step="1" class="form-control" name="items[${i}][quantity]" value="${d.quantity||1}" required></td>
    <td><input type="number" min="0" step="0.01" class="form-control" name="items[${i}][unit_price]" value="${d.unit_price??''}" required></td>
    <td><input type="number" min="0" step="0.01" class="form-control" name="items[${i}][discount]" value="${d.discount??''}"></td>
    <td class="text-end fw-semibold ln">₹0.00</td><td><button type="button" class="btn btn-sm btn-outline-danger rm"><i class="bi bi-x-lg"></i></button></td>`;
    tb.appendChild(tr);calc();}
  function calc(){let t=0;tb.querySelectorAll('tr').forEach(tr=>{const q=+tr.querySelector('[name$="[quantity]"]').value||0,u=+tr.querySelector('[name$="[unit_price]"]').value||0,d=+tr.querySelector('[name$="[discount]"]').value||0;const l=Math.max(q*u-d,0);t+=l;tr.querySelector('.ln').textContent=money(l);});
    document.getElementById('tot').textContent=money(t+(+document.getElementById('ship').value||0));}
  tb.addEventListener('input',calc);
  tb.addEventListener('change',e=>{if(e.target.name&&e.target.name.endsWith('[product_id]')){const o=OPTS.find(x=>String(x.id)===e.target.value),u=e.target.closest('tr').querySelector('[name$="[unit_price]"]');if(o&&!u.value&&o.price)u.value=o.price;calc();}});
  tb.addEventListener('click',e=>{const b=e.target.closest('.rm');if(b&&tb.children.length>1){b.closest('tr').remove();calc();}});
  document.getElementById('add').onclick=()=>add();document.getElementById('ship').oninput=calc;
  ROWS.forEach(add);
})();
</script>
@endpush
@endsection
