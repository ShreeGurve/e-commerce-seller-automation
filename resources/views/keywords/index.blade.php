@extends('layouts.app')
@section('title','Keywords')
@section('subtitle','Primary, secondary, long-tail, backend, AEO, SEO and GEO keywords with volume and rank')
@section('content')
@can('listings.manage')
<div class="card mb-3"><div class="card-header d-flex justify-content-between"><span>Add keywords</span><a href="{{ route('imports.create',['type'=>'KEYWORDS']) }}" class="small text-decoration-none"><i class="bi bi-cloud-upload"></i> Import from a file</a></div><div class="card-body"><form method="POST" action="{{ route('keywords.store') }}" class="row g-2">@csrf
  <div class="col-md-4"><select name="product_id" class="form-select" required><option value="">Product…</option>@foreach($products as $p)<option value="{{ $p->id }}" @selected(request('product_id')==$p->id)>{{ $p->sku }} — {{ \Illuminate\Support\Str::limit($p->name,35) }}</option>@endforeach</select></div>
  <div class="col-md-2"><select name="marketplace_id" class="form-select" required>@foreach($marketplaces as $m)<option value="{{ $m->id }}" @selected(request('marketplace_id')==$m->id)>{{ $m->name }}</option>@endforeach</select></div>
  <div class="col-md-2"><select name="keyword_type" class="form-select">@foreach(\App\Models\Keyword::TYPES as $k=>$l)<option value="{{ $k }}" @selected($k==='SECONDARY')>{{ $l }}</option>@endforeach</select></div>
  <div class="col-6 col-md-1"><input type="number" min="0" name="search_volume" class="form-control" placeholder="Volume"></div>
  <div class="col-6 col-md-1"><select name="competition" class="form-select"><option value="">Comp.</option>@foreach(\App\Models\Keyword::COMPETITION as $k=>$l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></div>
  <div class="col-md-2"><input name="group_name" class="form-control" list="grp" placeholder="Group (optional)"><datalist id="grp">@foreach($groups as $g)<option value="{{ $g->name }}">@endforeach</datalist></div>
  <div class="col-12"><textarea name="keywords" rows="3" class="form-control" placeholder="One keyword per line. Existing keywords are skipped." required></textarea></div>
  <div class="col-12"><button class="btn btn-primary">Add keywords</button></div></form></div></div>
@endcan
<div class="d-flex flex-wrap gap-2 mb-3">@foreach(\App\Models\Keyword::TYPES as $k=>$l)<a href="{{ route('keywords.index',array_merge(request()->query(),['type'=>request('type')===$k?null:$k])) }}" class="badge rounded-pill text-bg-{{ request('type')===$k?'primary':'light border' }} fs-6 text-decoration-none">{{ $l }} {{ $counts[$k] ?? 0 }}</a>@endforeach</div>
<div class="card mb-3"><div class="card-body"><form class="row g-2" method="GET"><input type="hidden" name="type" value="{{ request('type') }}">
  <div class="col-md-3"><input name="q" value="{{ request('q') }}" class="form-control" placeholder="Search keyword"></div>
  <div class="col-md-3"><select name="product_id" class="form-select"><option value="">All products</option>@foreach($products as $p)<option value="{{ $p->id }}" @selected(request('product_id')==$p->id)>{{ $p->sku }}</option>@endforeach</select></div>
  <div class="col-md-2"><select name="marketplace_id" class="form-select"><option value="">All channels</option>@foreach($marketplaces as $m)<option value="{{ $m->id }}" @selected(request('marketplace_id')==$m->id)>{{ $m->name }}</option>@endforeach</select></div>
  <div class="col-md-2"><select name="group_id" class="form-select"><option value="">All groups</option>@foreach($groups as $g)<option value="{{ $g->id }}" @selected(request('group_id')==$g->id)>{{ $g->name }}</option>@endforeach</select></div>
  <div class="col-md-2"><select name="sort" class="form-select">@foreach(['new'=>'Newest','volume'=>'Highest volume','rank'=>'Best rank','keyword'=>'A to Z'] as $k=>$l)<option value="{{ $k }}" @selected(request('sort','new')===$k)>{{ $l }}</option>@endforeach</select></div>
  <div class="col-12"><button class="btn btn-outline-primary">Filter</button></div></form></div></div>
<div class="card"><div class="table-responsive"><table class="table table-hover align-middle mb-0">
  <thead><tr><th>Keyword</th><th>Product</th><th>Channel</th><th>Type</th><th class="text-end">Volume</th><th>Comp.</th><th class="text-end">Relevance</th><th class="text-end">Rank</th><th>Group</th><th></th></tr></thead><tbody>
  @forelse($rows as $k)<tr><td class="fw-semibold">{{ $k->keyword }}</td><td>{{ $k->product->sku }}</td><td>{{ $k->marketplace->name }}</td><td><span class="badge text-bg-light border">{{ \App\Models\Keyword::TYPES[$k->keyword_type] }}</span></td>
    <td class="text-end">{{ $k->search_volume!==null?number_format($k->search_volume):'—' }}</td><td>{{ \App\Models\Keyword::COMPETITION[$k->competition] ?? '—' }}</td><td class="text-end">{{ $k->relevance ?? '—' }}</td>
    <td class="text-end">{{ $k->current_rank ?? '—' }}@if($k->rank_checked_on)<div class="small text-muted">{{ $k->rank_checked_on->format('d M') }}</div>@endif</td><td class="small">{{ $k->group->name ?? '—' }}</td>
    <td class="text-end text-nowrap">@can('listings.manage')<a href="{{ route('keywords.edit',$k) }}" class="btn btn-sm btn-outline-primary">Edit</a>@endcan</td></tr>
  @empty<tr><td colspan="10" class="text-center text-muted py-5">No keywords yet.</td></tr>@endforelse</tbody></table></div>@if($rows->hasPages())<div class="card-footer">{{ $rows->links() }}</div>@endif</div>
@endsection
