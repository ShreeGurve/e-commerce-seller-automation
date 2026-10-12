@extends('layouts.app')
@section('title','Inventory')
@section('subtitle','Live stock from the transaction ledger')
@section('content')
@if($mismatches)<div class="alert alert-danger"><b>{{ $mismatches }}</b> product(s) have stock that does not match the ledger. Do not edit stock manually in the database; report this.</div>@endif
<div class="row g-3 mb-3">
  @foreach([['Units available',number_format($totalUnits),'t-blue','bi-boxes'],['Stock value (at COGS)','₹'.number_format((float)$stockValue,2),'t-green','bi-cash-stack'],['Low stock',$lowCount,'t-orange','bi-exclamation-triangle'],['Out of stock',$outCount,'t-red','bi-x-octagon']] as [$l,$v,$c,$i])
  <div class="col-6 col-xl-3"><div class="card"><div class="card-body d-flex align-items-center gap-3"><div class="sg-tile {{ $c }}"><i class="bi {{ $i }}"></i></div><div><div class="small text-muted">{{ $l }}</div><div class="fw-bold fs-5">{{ $v }}</div></div></div></div></div>
  @endforeach
</div>
<div class="card mb-3"><div class="card-body"><form class="row g-2" method="GET">
  <div class="col-md-5"><input name="q" value="{{ request('q') }}" class="form-control" placeholder="Search SKU or name"></div>
  <div class="col-md-3"><select name="stock" class="form-select"><option value="">All stock</option><option value="low" @selected(request('stock')=='low')>Low stock</option><option value="out" @selected(request('stock')=='out')>Out of stock</option></select></div>
  <div class="col-md-4"><button class="btn btn-outline-primary">Filter</button></div>
</form></div></div>
<div class="card"><div class="table-responsive"><table class="table table-hover align-middle mb-0">
  <thead><tr><th>SKU</th><th>Product</th><th class="text-end">Available</th><th class="text-end">Damaged</th><th class="text-end">Min</th><th class="text-end">Reorder</th><th>Status</th></tr></thead><tbody>
  @forelse($products as $p) @php $inv=$p->inventory; $a=$inv->available_qty ?? 0; @endphp
    <tr><td><a class="fw-semibold text-decoration-none" href="{{ route('inventory.show',$p) }}">{{ $p->sku }}</a></td><td>{{ $p->name }}</td>
      <td class="text-end fw-bold">{{ $a }}</td><td class="text-end">{{ $inv->damaged_qty ?? 0 }}</td>
      <td class="text-end">{{ $inv->minimum_stock ?? 0 }}</td><td class="text-end">{{ $inv->reorder_level ?? 0 }}</td>
      <td>@if($a<=0)<span class="badge text-bg-danger">Out</span>@elseif($inv && $inv->isLow())<span class="badge text-bg-warning">Low</span>@else<span class="badge text-bg-success">OK</span>@endif</td></tr>
  @empty<tr><td colspan="7" class="text-center text-muted py-5">No products.</td></tr>@endforelse
  </tbody></table></div>
  @if($products->hasPages())<div class="card-footer">{{ $products->links() }}</div>@endif
</div>
@endsection
