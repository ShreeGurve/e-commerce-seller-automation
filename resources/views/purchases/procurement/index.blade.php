@extends('layouts.app')
@section('content')
<div class="container-fluid">
 <h1>Procurement Automation</h1>
 <p class="text-muted">Internal requisitions, supplier quotation comparison and award approval. No supplier purchase order or external message is sent.</p>
 @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
 @if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

 <div class="card mb-4"><div class="card-body">
  <h5>Create Purchase Requisition</h5>
  <form method="POST" action="{{ route('procurement.requisitions.store') }}" id="reqForm">@csrf
   <div class="row g-2 mb-2">
    <div class="col-md-4"><input name="title" class="form-control" placeholder="Requisition title" required></div>
    <div class="col-md-3"><input name="required_by" type="date" class="form-control"></div>
    <div class="col-md-2"><select name="priority" class="form-select"><option>NORMAL</option><option>LOW</option><option>HIGH</option><option>URGENT</option></select></div>
    <div class="col-md-3"><input name="purpose" class="form-control" placeholder="Purpose / business reason"></div>
   </div>
   <div class="table-responsive"><table class="table table-sm" id="reqItems"><thead><tr><th>Item</th><th>SKU</th><th>Qty</th><th>Unit</th><th>Est. unit cost</th><th>Specifications</th><th></th></tr></thead><tbody>
    <tr><td><input name="items[0][item_name]" class="form-control" required></td><td><input name="items[0][sku]" class="form-control"></td><td><input name="items[0][quantity]" type="number" step="0.001" min="0.001" class="form-control" required></td><td><input name="items[0][unit]" value="pcs" class="form-control"></td><td><input name="items[0][estimated_unit_cost]" type="number" step="0.01" min="0" value="0" class="form-control"></td><td><input name="items[0][specifications]" class="form-control"></td><td></td></tr>
   </tbody></table></div>
   <button type="button" class="btn btn-outline-secondary btn-sm" id="addReqItem">Add item</button>
   <button class="btn btn-primary btn-sm">Submit requisition</button>
  </form>
 </div></div>

 @foreach($requisitions as $req)
 <div class="card mb-3"><div class="card-body">
  <div class="d-flex justify-content-between flex-wrap"><div><h5>{{ $req->request_number }} — {{ $req->title }}</h5><div>{{ $req->priority }} · Required by {{ $req->required_by?->format('d M Y') ?? 'Not set' }} · Requested {{ $req->created_at->format('d M Y') }}</div></div><span class="badge bg-secondary align-self-start">{{ $req->status }}</span></div>
  @if($req->purpose)<p class="mt-2 mb-1">{{ $req->purpose }}</p>@endif
  <div class="table-responsive"><table class="table table-sm"><thead><tr><th>Item</th><th>SKU</th><th>Qty</th><th>Est. unit cost</th></tr></thead><tbody>@foreach($req->items as $item)<tr><td>{{ $item->item_name }}</td><td>{{ $item->sku }}</td><td>{{ $item->quantity }} {{ $item->unit }}</td><td>₹{{ number_format((float)$item->estimated_unit_cost,2) }}</td></tr>@endforeach</tbody></table></div>
  @if($req->status==='SUBMITTED')
   <form method="POST" action="{{ route('procurement.requisitions.review',$req) }}" class="row g-2">@csrf @method('PATCH')
    <div class="col-md-7"><input name="approval_notes" class="form-control" placeholder="Review notes"></div>
    <div class="col-md-2"><button name="decision" value="APPROVE" class="btn btn-success w-100">Approve</button></div>
    <div class="col-md-2"><button name="decision" value="REJECT" class="btn btn-outline-danger w-100">Reject</button></div>
   </form>
  @endif
  @if($req->status==='APPROVED')
   <hr><h6>Record supplier quotation</h6>
   <form method="POST" action="{{ route('procurement.quotes.store',$req) }}" class="row g-2">@csrf
    <div class="col-md-3"><select name="supplier_id" class="form-select" required><option value="">Supplier</option>@foreach($suppliers as $s)<option value="{{ $s->id }}">{{ $s->name }}</option>@endforeach</select></div>
    <div class="col-md-2"><input name="quote_reference" class="form-control" placeholder="Quote reference"></div>
    <div class="col-md-2"><input name="quote_date" type="date" class="form-control" required></div>
    <div class="col-md-2"><input name="valid_until" type="date" class="form-control" placeholder="Valid until"></div>
    <div class="col-md-1"><input name="shipping_cost" type="number" step="0.01" min="0" class="form-control" placeholder="Ship"></div>
    <div class="col-md-1"><input name="tax_amount" type="number" step="0.01" min="0" class="form-control" placeholder="Tax"></div>
    <div class="col-md-1"><input name="other_cost" type="number" step="0.01" min="0" class="form-control" placeholder="Other"></div>
    @foreach($req->items as $item)
     <div class="col-md-4"><label class="form-label small">{{ $item->item_name }} · requested {{ $item->quantity }}</label><input type="hidden" name="items[{{ $loop->index }}][requisition_item_id]" value="{{ $item->id }}"></div>
     <div class="col-md-2"><input name="items[{{ $loop->index }}][unit_cost]" type="number" step="0.01" min="0" class="form-control" placeholder="Unit cost" required></div>
     <div class="col-md-2"><input name="items[{{ $loop->index }}][quantity]" type="number" step="0.001" min="0.001" value="{{ $item->quantity }}" class="form-control" required></div>
     <div class="col-md-2"><select name="items[{{ $loop->index }}][availability]" class="form-select"><option>AVAILABLE</option><option>PARTIAL</option><option>UNAVAILABLE</option></select></div>
    @endforeach
    <div class="col-md-8"><input name="terms" class="form-control" placeholder="Payment / delivery terms"></div><div class="col-md-2"><input name="lead_time_days" type="number" min="0" class="form-control" placeholder="Lead days"></div><div class="col-md-2"><button class="btn btn-primary">Save quotation</button></div>
   </form>
  @endif
  @if($req->quotes->count())
   <hr><h6>Quotation comparison</h6><div class="table-responsive"><table class="table table-bordered table-sm"><thead><tr><th>Supplier</th><th>Reference</th><th>Quote date</th><th>Lead time</th><th>Total</th><th>Status</th><th>Award</th></tr></thead><tbody>
    @foreach($req->quotes as $q)<tr><td>{{ $q->supplier?->name }}</td><td>{{ $q->quote_reference }}</td><td>{{ $q->quote_date?->format('d M Y') }}</td><td>{{ $q->lead_time_days ?? '—' }}</td><td>₹{{ number_format((float)$q->quoted_total,2) }}</td><td>{{ $q->status }}</td><td>
     @if($req->status==='APPROVED' && $q->status==='RECEIVED')
      <form method="POST" action="{{ route('procurement.quotes.award',[$req,$q]) }}" class="d-flex gap-1">@csrf @method('PATCH')<input name="award_reason" class="form-control form-control-sm" placeholder="Award rationale" required><button class="btn btn-sm btn-success">Award</button></form>
     @elseif($req->awarded_quote_id===$q->id)<strong>Selected</strong>@endif
    </td></tr>@endforeach
   </tbody></table></div>
  @endif
  @if($req->status==='AWARDED')<div class="alert alert-info mb-0">Award recorded for {{ $req->awardedQuote?->supplier?->name }}. Create a purchase order separately through the existing purchase workflow after your internal checks.</div>@endif
 </div></div>
 @endforeach
 {{ $requisitions->links() }}
</div>
<script>
document.addEventListener('DOMContentLoaded',function(){
 const body=document.querySelector('#reqItems tbody'), add=document.getElementById('addReqItem');
 if(!body||!add)return;
 add.addEventListener('click',function(){
  const i=body.querySelectorAll('tr').length, tr=document.createElement('tr');
  const fields=[['item_name','text','',true],['sku','text','',false],['quantity','number','',true],['unit','text','pcs',false],['estimated_unit_cost','number','0',false],['specifications','text','',false]];
  fields.forEach(([name,type,val,required])=>{const td=document.createElement('td'),el=document.createElement('input');el.name=`items[${i}][${name}]`;el.type=type;el.className='form-control';el.value=val;if(type==='number'){el.step=name==='quantity'?'0.001':'0.01';el.min=name==='quantity'?'0.001':'0';}if(required)el.required=true;td.appendChild(el);tr.appendChild(td);});
  const td=document.createElement('td'),btn=document.createElement('button');btn.type='button';btn.className='btn btn-sm btn-outline-danger';btn.textContent='×';btn.onclick=()=>tr.remove();td.appendChild(btn);tr.appendChild(td);body.appendChild(tr);
 });
});
</script>
@endsection
