@extends('layouts.app')
@section('title',$product->sku.' · '.$product->name)
@section('content')
@php $tab = request('tab','overview'); @endphp
<div class="d-flex justify-content-between mb-3">
  <div><span class="badge text-bg-secondary">{{ ucfirst($product->status) }}</span>
    <span class="text-muted ms-2">Unit cost ₹{{ number_format((float)$product->totalUnitCost(),2) }}</span></div>
  <div class="d-flex gap-2">
    @can('products.edit')<a href="{{ route('products.edit',$product) }}" class="btn btn-sm btn-outline-primary">Edit</a>@endcan
    @can('products.delete')<form method="POST" action="{{ route('products.destroy',$product) }}" onsubmit="return confirm('Archive this product?')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger">Archive</button></form>@endcan
  </div>
</div>
<ul class="nav nav-tabs mb-3">
  @foreach(['overview'=>'Overview','costs'=>'Costs','marketplace'=>'Marketplace','listings'=>'Listings','keywords'=>'Keywords','history'=>'History'] as $k=>$l)
    <li class="nav-item"><a class="nav-link {{ $tab==$k?'active':'' }}" href="{{ route('products.show',[$product,'tab'=>$k]) }}">{{ $l }}</a></li>
  @endforeach
</ul>

@if($tab=='overview')
<div class="card"><div class="card-body"><dl class="row mb-0">
  @foreach(['SKU'=>$product->sku,'Parent SKU'=>$product->parent_sku,'Brand'=>$product->brand->name??null,'Category'=>$product->category->name??null,
    'Type'=>$product->product_type,'HSN'=>$product->hsn,'GST %'=>$product->gst_rate,'MRP'=>$product->mrp,'Default price'=>$product->default_selling_price,
    'Min price'=>$product->min_selling_price,'Target price'=>$product->target_selling_price,'Weight (g)'=>$product->weight_g,
    'L × W × H (cm)'=>trim(($product->length_cm??'—').' × '.($product->width_cm??'—').' × '.($product->height_cm??'—')),'Description'=>$product->description,'Notes'=>$product->notes] as $l=>$v)
    <dt class="col-sm-3">{{ $l }}</dt><dd class="col-sm-9">{{ $v ?? '—' }}</dd>
  @endforeach
</dl></div></div>

@elseif($tab=='costs')
@can('products.edit')
<div class="card mb-3"><div class="card-body"><form method="POST" action="{{ route('products.costs.store',$product) }}" class="row g-2 align-items-end">@csrf
  <div class="col-md-3"><label class="form-label">Cost type</label><select name="cost_type" class="form-select">@foreach(\App\Models\ProductCostHistory::TYPES as $k=>$l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></div>
  <div class="col-md-2"><label class="form-label">New amount (₹)</label><input name="amount" type="number" step="0.01" min="0" class="form-control" required></div>
  <div class="col-md-2"><label class="form-label">Effective from</label><input name="effective_from" type="date" value="{{ now()->toDateString() }}" class="form-control" required></div>
  <div class="col-md-3"><label class="form-label">Note</label><input name="note" class="form-control" placeholder="e.g. supplier price change"></div>
  <div class="col-md-2"><button class="btn btn-primary w-100">Save cost</button></div>
</form></div></div>
@endcan
@forelse($costRows as $type=>$rows)
  <div class="card mb-3"><div class="card-header bg-white fw-semibold">{{ \App\Models\ProductCostHistory::TYPES[$type] ?? $type }}</div>
  <table class="table table-sm mb-0"><thead class="table-light"><tr><th>Amount</th><th>From</th><th>To</th><th>Note</th></tr></thead><tbody>
  @foreach($rows as $r)<tr class="{{ $r->effective_to? '':'table-success' }}"><td>₹{{ number_format($r->amount,2) }}</td><td>{{ $r->effective_from->format('d M Y') }}</td><td>{{ $r->effective_to?->format('d M Y') ?? 'current' }}</td><td>{{ $r->note }}</td></tr>@endforeach
  </tbody></table></div>
@empty<div class="text-muted">No costs recorded yet.</div>@endforelse

@elseif($tab=='marketplace')
@can('products.edit')
<div class="card mb-3"><div class="card-body"><form method="POST" action="{{ route('products.marketplaces.store',$product) }}" class="row g-2 align-items-end">@csrf
  <div class="col-md-2"><label class="form-label">Marketplace</label><select name="marketplace_id" class="form-select">@foreach($marketplaces as $m)<option value="{{ $m->id }}">{{ $m->name }}</option>@endforeach</select></div>
  <div class="col-md-3"><label class="form-label">Marketplace SKU</label><input name="marketplace_sku" class="form-control" required></div>
  <div class="col-md-2"><label class="form-label">ASIN / FSN</label><input name="external_product_id" class="form-control"></div>
  <div class="col-md-2"><label class="form-label">FNSKU</label><input name="fulfillment_sku" class="form-control"></div>
  <div class="col-md-2"><label class="form-label">Listing</label><select name="listing_status" class="form-select">@foreach(['active','inactive','suppressed','draft'] as $s)<option>{{ $s }}</option>@endforeach</select></div>
  <div class="col-md-1"><button class="btn btn-primary w-100">Add</button></div>
</form></div></div>
@endcan
<div class="card"><table class="table mb-0 align-middle"><thead class="table-light"><tr><th>Marketplace</th><th>SKU</th><th>ASIN/FSN</th><th>FNSKU</th><th>Listing</th><th></th></tr></thead><tbody>
@forelse($product->marketplaces as $m)
  <tr><td>{{ $m->marketplace->name }}</td><td>{{ $m->marketplace_sku }}</td><td>{{ $m->external_product_id ?? '—' }}</td><td>{{ $m->fulfillment_sku ?? '—' }}</td><td>{{ ucfirst($m->listing_status) }}</td>
  <td class="text-end">@can('products.edit')<form method="POST" action="{{ route('products.marketplaces.destroy',[$product,$m]) }}" onsubmit="return confirm('Remove mapping?')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger">Remove</button></form>@endcan</td></tr>
@empty<tr><td colspan="6" class="text-center text-muted py-4">Not mapped to any marketplace yet.</td></tr>@endforelse
</tbody></table></div>

@elseif($tab=='listings')
<div class="card"><div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Channel</th><th>Status</th><th class="text-center">Version</th><th class="text-center">Score</th><th></th></tr></thead><tbody>
@forelse($listings as $l)<tr><td>{{ $l->marketplace->name }}</td><td><span class="badge text-bg-{{ \App\Models\Listing::BADGES[$l->status] }}">{{ \App\Models\Listing::STATUSES[$l->status] }}</span></td>
  <td class="text-center">v{{ $l->current->version_no ?? '—' }}</td><td class="text-center fw-bold">{{ $l->current ? number_format($l->current->score,0) : '—' }}</td><td class="text-end"><a class="btn btn-sm btn-outline-primary" href="{{ route('listings.show',$l) }}">Open</a></td></tr>
@empty<tr><td colspan="5" class="text-center text-muted py-4">No listing copy saved yet.</td></tr>@endforelse</tbody></table></div>
@can('listings.manage')<div class="card-footer"><a class="btn btn-sm btn-primary" href="{{ route('listings.create',['product_id'=>$product->id]) }}">+ New listing</a></div>@endcan</div>
@elseif($tab=='keywords')
<div class="card"><div class="table-responsive"><table class="table table-sm align-middle mb-0"><thead><tr><th>Keyword</th><th>Channel</th><th>Type</th><th class="text-end">Volume</th><th class="text-end">Rank</th></tr></thead><tbody>
@forelse($keywords as $k)<tr><td>{{ $k->keyword }}</td><td>{{ $k->marketplace->name }}</td><td><span class="badge text-bg-light border">{{ \App\Models\Keyword::TYPES[$k->keyword_type] }}</span></td><td class="text-end">{{ $k->search_volume ?? '—' }}</td><td class="text-end">{{ $k->current_rank ?? '—' }}</td></tr>
@empty<tr><td colspan="5" class="text-center text-muted py-4">No keywords yet.</td></tr>@endforelse</tbody></table></div>
<div class="card-footer"><a class="btn btn-sm btn-outline-primary" href="{{ route('keywords.index',['product_id'=>$product->id]) }}">Manage keywords</a></div></div>

@else
<div class="card"><ul class="list-group list-group-flush">
@forelse($activity as $a)<li class="list-group-item small"><b>{{ $a->action }}</b> · {{ $a->user->name ?? 'system' }} · {{ $a->created_at->format('d M Y H:i') }}
  @if($a->new_values)<div class="text-muted">{{ json_encode($a->new_values) }}</div>@endif</li>
@empty<li class="list-group-item text-muted">No history yet.</li>@endforelse
</ul></div>
@endif
@endsection
