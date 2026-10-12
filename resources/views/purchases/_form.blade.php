@php
  $rows = old('items', $purchase->exists
      ? $purchase->items->map(fn($i)=>['product_id'=>$i->product_id,'quantity'=>$i->quantity,'unit_cost'=>$i->unit_cost,'gst_rate'=>$i->gst_rate])->all()
      : [['product_id'=>'','quantity'=>1,'unit_cost'=>'','gst_rate'=>'']]);
  $meta = $products->mapWithKeys(fn($p)=>[$p->id=>['gst'=>(float)$p->gst_rate,'cogs'=>(float)$p->currentCost('COGS')]]);
  $f = fn($k,$d=null) => old($k, $purchase->{$k} ?? $d);
@endphp
<div class="row g-3 mb-3">
  <div class="col-md-4"><label class="form-label">Supplier *</label><select name="supplier_id" class="form-select" required><option value="">Select…</option>
    @foreach($suppliers as $s)<option value="{{ $s->id }}" @selected($f('supplier_id')==$s->id)>{{ $s->name }}</option>@endforeach</select></div>
  <div class="col-md-2"><label class="form-label">Purchase date *</label><input type="date" name="purchase_date" value="{{ old('purchase_date', optional($purchase->purchase_date)->toDateString() ?? now()->toDateString()) }}" class="form-control" required></div>
  <div class="col-md-2"><label class="form-label">Status</label><select name="status" class="form-select">@foreach(['DRAFT'=>'Draft','ORDERED'=>'Ordered'] as $k=>$l)<option value="{{ $k }}" @selected($f('status','DRAFT')==$k)>{{ $l }}</option>@endforeach</select></div>
  <div class="col-md-2"><label class="form-label">Invoice no.</label><input name="invoice_number" value="{{ $f('invoice_number') }}" class="form-control"></div>
  <div class="col-md-2"><label class="form-label">Invoice date</label><input type="date" name="invoice_date" value="{{ old('invoice_date', optional($purchase->invoice_date)->toDateString()) }}" class="form-control"></div>
</div>

<h6 class="border-bottom pb-1">Items</h6>
<div class="table-responsive"><table class="table align-middle" id="itemsTable">
  <thead><tr><th style="min-width:260px">Product</th><th style="width:110px">Qty</th><th style="width:140px">Unit cost ₹</th><th style="width:100px">GST %</th><th class="text-end" style="width:130px">Line total</th><th style="width:44px"></th></tr></thead>
  <tbody></tbody></table></div>
<button type="button" class="btn btn-sm btn-outline-primary mb-4" id="addRow"><i class="bi bi-plus-lg"></i> Add product</button>

<div class="row g-3">
  <div class="col-md-7"><label class="form-label">Notes</label><textarea name="notes" rows="4" class="form-control">{{ $f('notes') }}</textarea></div>
  <div class="col-md-5"><div class="card"><div class="card-body">
    <div class="row g-2 mb-2">
      <div class="col-6"><label class="form-label small">Shipping ₹</label><input type="number" step="0.01" min="0" name="shipping_charges" id="ship" value="{{ $f('shipping_charges',0) }}" class="form-control form-control-sm"></div>
      <div class="col-6"><label class="form-label small">Other charges ₹</label><input type="number" step="0.01" min="0" name="other_charges" id="other" value="{{ $f('other_charges',0) }}" class="form-control form-control-sm"></div>
    </div>
    <div class="d-flex justify-content-between"><span class="text-muted">Subtotal</span><span id="tSub">₹0.00</span></div>
    <div class="d-flex justify-content-between"><span class="text-muted">GST</span><span id="tGst">₹0.00</span></div>
    <div class="d-flex justify-content-between fw-bold fs-5 border-top pt-2 mt-2"><span>Total</span><span id="tTot">₹0.00</span></div>
    <div class="small text-muted mt-1">Preview only. Server recalculates on save.</div>
  </div></div></div>
</div>

@push('scripts')
<script>
(function(){
  const META=@json($meta), ROWS=@json($rows);
  const OPTIONS=@json($products->map(fn($p)=>['id'=>$p->id,'label'=>$p->sku.' — '.$p->name])->values());
  const tbody=document.querySelector('#itemsTable tbody'); let idx=0;
  const money=n=>'₹'+(Math.round(n*100)/100).toLocaleString('en-IN',{minimumFractionDigits:2,maximumFractionDigits:2});
  function addRow(d){
    d=d||{}; const i=idx++; const tr=document.createElement('tr');
    tr.innerHTML=`<td><select class="form-select" name="items[${i}][product_id]" required><option value="">Select product…</option>`+
      OPTIONS.map(o=>`<option value="${o.id}" ${String(d.product_id)===String(o.id)?'selected':''}>${o.label.replace(/</g,'&lt;')}</option>`).join('')+`</select></td>
      <td><input type="number" min="1" step="1" class="form-control" name="items[${i}][quantity]" value="${d.quantity||1}" required></td>
      <td><input type="number" min="0" step="0.01" class="form-control" name="items[${i}][unit_cost]" value="${d.unit_cost??''}" required></td>
      <td><input type="number" min="0" max="100" step="0.01" class="form-control" name="items[${i}][gst_rate]" value="${d.gst_rate??''}"></td>
      <td class="text-end fw-semibold line">₹0.00</td>
      <td><button type="button" class="btn btn-sm btn-outline-danger rm" title="Remove"><i class="bi bi-x-lg"></i></button></td>`;
    tbody.appendChild(tr); calc();
  }
  function calc(){
    let sub=0,gst=0;
    tbody.querySelectorAll('tr').forEach(tr=>{
      const q=+tr.querySelector('[name$="[quantity]"]').value||0, u=+tr.querySelector('[name$="[unit_cost]"]').value||0, g=+tr.querySelector('[name$="[gst_rate]"]').value||0;
      const ls=q*u, lg=ls*g/100; sub+=ls; gst+=lg; tr.querySelector('.line').textContent=money(ls+lg);
    });
    const sh=+document.getElementById('ship').value||0, ot=+document.getElementById('other').value||0;
    document.getElementById('tSub').textContent=money(sub); document.getElementById('tGst').textContent=money(gst); document.getElementById('tTot').textContent=money(sub+gst+sh+ot);
  }
  tbody.addEventListener('input',calc);
  tbody.addEventListener('change',e=>{
    if(e.target.name&&e.target.name.endsWith('[product_id]')){
      const m=META[e.target.value], tr=e.target.closest('tr'); if(!m)return;
      const g=tr.querySelector('[name$="[gst_rate]"]'), u=tr.querySelector('[name$="[unit_cost]"]');
      if(!g.value)g.value=m.gst||''; if(!u.value&&m.cogs)u.value=m.cogs; calc();
    }
  });
  tbody.addEventListener('click',e=>{const b=e.target.closest('.rm'); if(b&&tbody.children.length>1){b.closest('tr').remove();calc();}});
  document.getElementById('addRow').onclick=()=>addRow();
  document.getElementById('ship').oninput=calc; document.getElementById('other').oninput=calc;
  ROWS.forEach(addRow);
})();
</script>
@endpush
