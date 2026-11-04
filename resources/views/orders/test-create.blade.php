@extends('layouts.app')
@section('title', 'Create test order')
@section('subtitle', 'Internal simulation only — no marketplace API calls, no stock deduction, no profit or settlement impact.')
@section('content')
<div class="alert alert-warning"><strong>Test data only.</strong> This creates a TEST order for exercising packing, dispatch and pickup. It does not contact Amazon or Flipkart, deduct inventory, or enter financial reports.</div>
<div class="card"><div class="card-body">
<form method="POST" action="{{ route('test-orders.store') }}">@csrf
<div class="row g-3 mb-3">
<div class="col-md-4"><label class="form-label">Simulated channel *</label><select name="marketplace_id" class="form-select" required>@foreach($marketplaces as $m)<option value="{{ $m->id }}" @selected(old('marketplace_id')==$m->id)>{{ $m->name }}</option>@endforeach</select></div>
<div class="col-md-4"><label class="form-label">Test order date *</label><input type="datetime-local" name="order_date" class="form-control" value="{{ old('order_date', now()->format('Y-m-d\TH:i')) }}" max="{{ now()->format('Y-m-d\TH:i') }}" required></div>
<div class="col-md-4"><label class="form-label">Test customer reference</label><input name="customer_ref" class="form-control" value="{{ old('customer_ref', 'TEST CUSTOMER') }}" maxlength="120"></div>
<div class="col-md-4"><label class="form-label">Destination state</label><input name="ship_state" class="form-control" value="{{ old('ship_state') }}" maxlength="80"></div>
<div class="col-md-4"><label class="form-label">Shipping charged (₹)</label><input type="number" min="0" step="0.01" name="shipping_charged" class="form-control" value="{{ old('shipping_charged', '0.00') }}"></div>
</div>
<h5 class="mb-2">Test order items</h5><div class="table-responsive"><table class="table align-middle" id="test-items"><thead><tr><th style="min-width:260px">Product *</th><th>Qty *</th><th>Unit price (₹) *</th><th>Discount (₹)</th><th></th></tr></thead><tbody></tbody></table></div>
<button type="button" class="btn btn-outline-secondary btn-sm" id="add-test-item"><i class="bi bi-plus-lg"></i> Add product</button>
<div class="mt-3"><label class="form-label">Internal test notes</label><textarea name="notes" rows="2" class="form-control">{{ old('notes') }}</textarea></div>
<div class="d-flex gap-2 mt-4"><button class="btn btn-primary">Create test order</button><a href="{{ route('orders.index') }}" class="btn btn-light">Cancel</a></div>
</form></div></div>
@php($productOptions = $products->map(fn($p) => ['id'=>$p->id,'label'=>$p->sku.' — '.$p->name,'price'=>(float)($p->default_selling_price ?? $p->mrp ?? 0)])->values())
@push('scripts')<script>
(()=>{const opts=@json($productOptions), body=document.querySelector('#test-items tbody');let n=0;
function add(){const i=n++,tr=document.createElement('tr');tr.innerHTML=`<td><select required class="form-select" name="items[${i}][product_id]"><option value="">Select product…</option>${opts.map(p=>`<option value="${p.id}">${String(p.label).replace(/&/g,'&amp;').replace(/</g,'&lt;')}</option>`).join('')}</select></td><td><input required type="number" min="1" step="1" value="1" class="form-control" name="items[${i}][quantity]"></td><td><input required type="number" min="0" step="0.01" class="form-control" name="items[${i}][unit_price]"></td><td><input type="number" min="0" step="0.01" value="0" class="form-control" name="items[${i}][discount]"></td><td><button type="button" class="btn btn-sm btn-outline-danger remove">Remove</button></td>`;body.appendChild(tr);}
body.addEventListener('change',e=>{if(e.target.matches('select')){const p=opts.find(x=>String(x.id)===e.target.value);if(p){const input=e.target.closest('tr').querySelector('[name$="[unit_price]"]');if(!input.value)input.value=p.price.toFixed(2);}}});body.addEventListener('click',e=>{if(e.target.matches('.remove')&&body.children.length>1)e.target.closest('tr').remove();});document.querySelector('#add-test-item').addEventListener('click',add);add();})();
</script>@endpush
@endsection
