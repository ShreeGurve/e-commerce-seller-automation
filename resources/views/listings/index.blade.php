@extends('layouts.app')
@section('title','Listings')
@section('subtitle','Your listing copy, versioned and scored. Apply it on the marketplace yourself.')
@section('content')
<div class="card mb-3"><div class="card-body"><form class="row g-2" method="GET">
  <div class="col-md-3"><input name="q" value="{{ request('q') }}" class="form-control" placeholder="Search SKU or name"></div>
  <div class="col-md-2"><select name="marketplace_id" class="form-select"><option value="">All channels</option>@foreach($marketplaces as $m)<option value="{{ $m->id }}" @selected(request('marketplace_id')==$m->id)>{{ $m->name }}</option>@endforeach</select></div>
  <div class="col-md-2"><select name="status" class="form-select"><option value="">Any status</option>@foreach(\App\Models\Listing::STATUSES as $k=>$l)<option value="{{ $k }}" @selected(request('status')===$k)>{{ $l }}</option>@endforeach</select></div>
  <div class="col-md-2 d-flex align-items-center"><div class="form-check"><input class="form-check-input" type="checkbox" name="low" value="1" id="low" @checked(request('low'))><label class="form-check-label" for="low">Score under 70</label></div></div>
  <div class="col-md-3 d-flex gap-2"><button class="btn btn-outline-primary">Filter</button>
    @can('listings.manage')<a href="{{ route('listings.rules') }}" class="btn btn-outline-secondary" title="Score rules"><i class="bi bi-sliders"></i></a><a href="{{ route('listings.create') }}" class="btn btn-primary ms-auto"><i class="bi bi-plus-lg"></i> New listing</a>@endcan</div>
</form></div></div>
<div class="card"><div class="table-responsive"><table class="table table-hover align-middle mb-0">
  <thead><tr><th>Product</th><th>Channel</th><th>Status</th><th class="text-center">Version</th><th class="text-center">Live version</th><th class="text-center">Score</th><th>Updated</th></tr></thead><tbody>
  @forelse($listings as $l) @php $sc=(float)($l->current->score ?? 0); @endphp
    <tr><td><a class="fw-semibold text-decoration-none" href="{{ route('listings.show',$l) }}">{{ $l->product->sku }}</a><div class="small text-muted">{{ \Illuminate\Support\Str::limit($l->product->name,45) }}</div></td>
      <td>{{ $l->marketplace->name }}</td><td><span class="badge text-bg-{{ \App\Models\Listing::BADGES[$l->status] }}">{{ \App\Models\Listing::STATUSES[$l->status] }}</span></td>
      <td class="text-center">v{{ $l->current->version_no ?? '—' }}</td>
      <td class="text-center">@if($l->published)v{{ $l->published->version_no }}@if($l->current && $l->published->id!==$l->current->id)<span class="badge text-bg-warning ms-1" title="Newer version not applied yet">newer draft</span>@endif @else<span class="text-muted">—</span>@endif</td>
      <td class="text-center"><span class="badge text-bg-{{ $sc>=80?'success':($sc>=60?'warning':'danger') }} fs-6">{{ number_format($sc,0) }}</span></td><td class="small text-muted text-nowrap">{{ $l->updated_at->diffForHumans() }}</td></tr>
  @empty<tr><td colspan="7" class="text-center text-muted py-5">No listings yet. Create one to write, score and version your copy.</td></tr>@endforelse
  </tbody></table></div>@if($listings->hasPages())<div class="card-footer">{{ $listings->links() }}</div>@endif</div>
@endsection
