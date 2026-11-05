@extends('layouts.app')
@section('content')
<div class="container-fluid">
 <h1>Inventory & Warehouse Intelligence</h1>
 <p class="text-muted">Manage internal warehouse locations, manual stock balances, transfers and cycle counts. These records do not sync to marketplace inventory.</p>
 @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
 @if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif
 <div class="row g-3">
  <div class="col-xl-4"><div class="card h-100"><div class="card-body"><h5>Add Warehouse / Location</h5>
   <form method="POST" action="{{ route('warehouse.locations.store') }}">@csrf
    <input class="form-control mb-2" name="code" placeholder="Location code" required>
    <input class="form-control mb-2" name="name" placeholder="Location name" required>
    <select class="form-select mb-2" name="location_type">@foreach(['WAREHOUSE','STORE','3PL','TRANSIT','OTHER'] as $type)<option>{{ $type }}</option>@endforeach</select>
    <input class="form-control mb-2" name="address" placeholder="Address">
    <input class="form-control mb-2" name="contact_person" placeholder="Contact person">
    <input class="form-control mb-2" name="contact_phone" placeholder="Contact phone">
    <button class="btn btn-primary">Create location</button>
   </form>
  </div></div></div>
  <div class="col-xl-8"><div class="card h-100"><div class="card-body"><h5>Warehouse Stock Balances</h5>
   @forelse($locations as $loc)
    <div class="border rounded p-3 mb-3"><div class="d-flex justify-content-between"><strong>{{ $loc->name }} ({{ $loc->code }})</strong><span>{{ $loc->balances_count }} SKUs · {{ $loc->is_active?'Active':'Inactive' }}</span></div>
     <form method="POST" action="{{ route('warehouse.balances.store',$loc) }}" class="row g-2 mt-2">@csrf
      <div class="col-md-3"><input class="form-control form-control-sm" name="sku" placeholder="SKU" required></div>
      <div class="col-md-3"><input class="form-control form-control-sm" name="item_name" placeholder="Item name" required></div>
      <div class="col-md-2"><input class="form-control form-control-sm" name="quantity" type="number" step="0.001" min="0" placeholder="Qty" required></div>
      <div class="col-md-2"><input class="form-control form-control-sm" name="unit" value="pcs" required></div>
      <div class="col-md-2"><button class="btn btn-sm btn-outline-primary">Save stock</button></div>
     </form>
     @if($loc->balances->count())<div class="table-responsive mt-2"><table class="table table-sm mb-0"><thead><tr><th>SKU</th><th>Item</th><th>Qty</th><th>Unit</th></tr></thead><tbody>@foreach($loc->balances as $b)<tr><td>{{ $b->sku }}</td><td>{{ $b->item_name }}</td><td>{{ number_format((float)$b->quantity,3) }}</td><td>{{ $b->unit }}</td></tr>@endforeach</tbody></table></div>@endif
    </div>
   @empty <p class="text-muted">Create a location to begin.</p> @endforelse
  </div></div></div>
 </div>

 <div class="card my-4"><div class="card-body"><h5>Create Internal Stock Transfer</h5>
  <form method="POST" action="{{ route('warehouse.transfers.store') }}" id="transferForm">@csrf
   <div class="row g-2 mb-2"><div class="col-md-3"><select name="source_location_id" class="form-select" required><option value="">Source location</option>@foreach($locations->where('is_active',true) as $l)<option value="{{ $l->id }}">{{ $l->name }}</option>@endforeach</select></div>
   <div class="col-md-3"><select name="destination_location_id" class="form-select" required><option value="">Destination location</option>@foreach($locations->where('is_active',true) as $l)<option value="{{ $l->id }}">{{ $l->name }}</option>@endforeach</select></div>
   <div class="col-md-2"><input name="transfer_date" type="date" class="form-control" required></div><div class="col-md-4"><input name="notes" class="form-control" placeholder="Notes"></div></div>
   <div id="transferLines"><div class="row g-2 mb-2"><div class="col-md-3"><input name="items[0][sku]" class="form-control" placeholder="SKU" required></div><div class="col-md-3"><input name="items[0][item_name]" class="form-control" placeholder="Item name" required></div><div class="col-md-2"><input name="items[0][quantity]" type="number" min="0.001" step="0.001" class="form-control" placeholder="Qty" required></div><div class="col-md-2"><input name="items[0][unit]" value="pcs" class="form-control" required></div></div></div>
   <button type="button" class="btn btn-sm btn-outline-secondary" id="addTransferLine">Add item</button> <button class="btn btn-primary btn-sm">Save transfer draft</button>
  </form>
 </div></div>

 <div class="card mb-4"><div class="card-body"><h5>Transfers</h5><div class="table-responsive"><table class="table table-sm align-middle"><thead><tr><th>Transfer</th><th>From</th><th>To</th><th>Date</th><th>Items</th><th>Status / Action</th></tr></thead><tbody>
 @forelse($transfers as $t)<tr><td>{{ $t->transfer_number }}</td><td>{{ $t->source?->name }}</td><td>{{ $t->destination?->name }}</td><td>{{ $t->transfer_date?->format('d M Y') }}</td><td>@foreach($t->items as $i){{ $i->sku }}: {{ $i->quantity }} {{ $i->unit }}@if(!$loop->last); @endif @endforeach</td><td>{{ $t->status }}
 @if($t->status==='DRAFT')<form method="POST" action="{{ route('warehouse.transfers.dispatch',$t) }}" class="mt-1">@csrf @method('PATCH')<button class="btn btn-sm btn-warning">Dispatch</button></form>
 @elseif($t->status==='IN_TRANSIT')<form method="POST" action="{{ route('warehouse.transfers.receive',$t) }}" class="mt-1">@csrf @method('PATCH')<button class="btn btn-sm btn-success">Receive</button></form>@endif</td></tr>
 @empty<tr><td colspan="6" class="text-center text-muted">No transfers yet.</td></tr>@endforelse
 </tbody></table></div></div></div>

 <div class="card mb-4"><div class="card-body"><h5>Cycle Count / Physical Stocktake</h5>
  <form method="POST" action="{{ route('warehouse.stock-counts.store') }}" id="countForm">@csrf
   <div class="row g-2 mb-2"><div class="col-md-4"><select name="warehouse_location_id" class="form-select" required><option value="">Warehouse</option>@foreach($locations as $l)<option value="{{ $l->id }}">{{ $l->name }}</option>@endforeach</select></div><div class="col-md-3"><input name="count_date" type="date" class="form-control" required></div><div class="col-md-5"><input name="notes" class="form-control" placeholder="Count notes"></div></div>
   <div id="countLines"><div class="row g-2 mb-2"><div class="col-md-3"><input name="items[0][sku]" class="form-control" placeholder="SKU" required></div><div class="col-md-3"><input name="items[0][item_name]" class="form-control" placeholder="Item name" required></div><div class="col-md-2"><input name="items[0][counted_quantity]" type="number" min="0" step="0.001" class="form-control" placeholder="Physical qty" required></div><div class="col-md-2"><input name="items[0][unit]" value="pcs" class="form-control" required></div><div class="col-md-2"><input name="items[0][notes]" class="form-control" placeholder="Notes"></div></div></div>
   <button type="button" class="btn btn-sm btn-outline-secondary" id="addCountLine">Add count line</button> <button class="btn btn-primary btn-sm">Save count draft</button>
  </form>
 </div></div>
 <div class="card"><div class="card-body"><h5>Stock Count Reviews</h5><div class="table-responsive"><table class="table table-sm"><thead><tr><th>Count</th><th>Location</th><th>Date</th><th>Lines / Variance</th><th>Status</th><th></th></tr></thead><tbody>
 @forelse($counts as $c)<tr><td>{{ $c->count_number }}</td><td>{{ $c->location?->name }}</td><td>{{ $c->count_date?->format('d M Y') }}</td><td>@foreach($c->items as $i){{ $i->sku }}: system {{ number_format((float)$i->system_quantity,3) }} / counted {{ number_format((float)$i->counted_quantity,3) }} / variance {{ number_format((float)$i->variance_quantity,3) }}@if(!$loop->last); @endif @endforeach</td><td>{{ $c->status }}</td><td>@if($c->status==='DRAFT')<form method="POST" action="{{ route('warehouse.stock-counts.finalize',$c) }}">@csrf @method('PATCH')<button class="btn btn-sm btn-success" onclick="return confirm('Finalize this count and apply counted quantities to internal stock balances?')">Finalize</button></form>@endif</td></tr>
 @empty<tr><td colspan="6" class="text-center text-muted">No stock counts yet.</td></tr>@endforelse
 </tbody></table></div></div></div>
</div>
<script>
document.addEventListener('DOMContentLoaded',()=>{
 function addRow(containerId,buttonId,fields){const box=document.getElementById(containerId),btn=document.getElementById(buttonId);if(!box||!btn)return;btn.addEventListener('click',()=>{const i=box.children.length,row=document.createElement('div');row.className='row g-2 mb-2';fields.forEach(f=>{const col=document.createElement('div');col.className=f.col;const el=document.createElement('input');el.className='form-control';el.name=`items[${i}][${f.name}]`;el.placeholder=f.label;el.type=f.type||'text';el.value=f.value||'';el.required=true;if(el.type==='number'){el.step='0.001';el.min=f.min||'0';}col.appendChild(el);row.appendChild(col);});box.appendChild(row);});}
 addRow('transferLines','addTransferLine',[{name:'sku',label:'SKU',col:'col-md-3'},{name:'item_name',label:'Item name',col:'col-md-3'},{name:'quantity',label:'Qty',type:'number',min:'0.001',col:'col-md-2'},{name:'unit',label:'Unit',value:'pcs',col:'col-md-2'}]);
 addRow('countLines','addCountLine',[{name:'sku',label:'SKU',col:'col-md-3'},{name:'item_name',label:'Item name',col:'col-md-3'},{name:'counted_quantity',label:'Physical qty',type:'number',col:'col-md-2'},{name:'unit',label:'Unit',value:'pcs',col:'col-md-2'},{name:'notes',label:'Notes',col:'col-md-2'}]);
});
</script>
@endsection
