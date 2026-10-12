@extends('layouts.app')
@section('title','New listing')
@section('subtitle','Write the copy here; it is scored as you save. Saving never changes anything on Amazon or Flipkart.')
@section('content')
<div class="card"><div class="card-body"><form method="POST" action="{{ route('listings.store') }}">@csrf
  <div class="row g-3 mb-3">
    <div class="col-md-6"><label class="form-label fw-semibold">Product *</label><select name="product_id" class="form-select" required><option value="">Select…</option>@foreach($products as $p)<option value="{{ $p->id }}" @selected(old('product_id',$product->id ?? null)==$p->id)>{{ $p->sku }} — {{ \Illuminate\Support\Str::limit($p->name,50) }}</option>@endforeach</select></div>
    <div class="col-md-3"><label class="form-label fw-semibold">Channel *</label><select name="marketplace_id" class="form-select" required>@foreach($marketplaces as $m)<option value="{{ $m->id }}" @selected(old('marketplace_id')==$m->id)>{{ $m->name }}</option>@endforeach</select></div>
  </div>
  @php $listing=null; @endphp @include('listings._form')
  <div class="mt-4"><button class="btn btn-primary">Save &amp; score</button> <a href="{{ route('listings.index') }}" class="btn btn-link">Cancel</a></div></form></div></div>
@endsection
