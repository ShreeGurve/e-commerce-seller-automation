@extends('layouts.app')
@section('title',$listing->product->sku.' · '.$listing->marketplace->name)
@section('subtitle',\Illuminate\Support\Str::limit($listing->product->name,70))
@section('content')
@php $v=$listing->current; $sc=(float)($v->score ?? 0); $col=$sc>=80?'success':($sc>=60?'warning':'danger'); $can=auth()->user()->can('listings.manage'); @endphp
<div class="d-flex flex-wrap gap-2 align-items-center mb-3">
  <span class="badge text-bg-{{ \App\Models\Listing::BADGES[$listing->status] }} fs-6">{{ \App\Models\Listing::STATUSES[$listing->status] }}</span>
  <span class="text-muted">version {{ $v->version_no ?? '—' }} · live: {{ $listing->published ? 'v'.$listing->published->version_no : 'not recorded' }}</span>
  @if($can)<div class="ms-auto d-flex flex-wrap gap-2">
    <form method="POST" action="{{ route('listings.status',$listing) }}" class="d-flex gap-1">@csrf<select name="status" class="form-select form-select-sm" onchange="this.form.submit()">@foreach(\App\Models\Listing::STATUSES as $k=>$l)<option value="{{ $k }}" @selected($listing->status===$k)>{{ $l }}</option>@endforeach</select></form>
    <form method="POST" action="{{ route('listings.rescore',$listing) }}">@csrf<button class="btn btn-sm btn-outline-secondary" title="Re-check with current keywords and score rules"><i class="bi bi-arrow-repeat"></i> Recalculate score</button></form>
    <a href="{{ route('listings.edit',$listing) }}" class="btn btn-sm btn-primary">Edit (new version)</a></div>@endif
</div>
@if($v)
<div class="row g-3">
  <div class="col-lg-4"><div class="card mb-3"><div class="card-body text-center"><div class="small text-muted">Listing score</div><div class="display-4 fw-bold text-{{ $col }}">{{ number_format($sc,0) }}<span class="fs-5 text-muted">/100</span></div></div></div>
    <div class="card"><div class="card-header">Breakdown &amp; tips</div><div class="card-body small">
      @foreach($v->score_breakdown ?? [] as $b) @php $r=$b['max']>0?$b['points']/$b['max']:0; @endphp
        <div class="mb-3"><div class="d-flex justify-content-between"><span class="fw-semibold">{{ $b['label'] }}</span><span>{{ $b['points'] }} / {{ $b['max'] }}</span></div>
          <div class="progress" style="height:6px"><div class="progress-bar bg-{{ $r>=0.8?'success':($r>=0.5?'warning':'danger') }}" style="width:{{ round($r*100) }}%"></div></div>
          @foreach($b['tips'] as $t)<div class="text-muted mt-1"><i class="bi bi-lightbulb"></i> {{ $t }}</div>@endforeach</div>@endforeach
      <a href="{{ route('listings.rules') }}" class="text-decoration-none">Change scoring rules</a></div></div></div>
  <div class="col-lg-8">
    <div class="card mb-3"><div class="card-body">
      <h5 class="fw-bold">{{ $v->title }}</h5><div class="small text-muted mb-3">{{ mb_strlen($v->title) }} characters</div>
      <ul class="mb-3">@foreach($v->bullets ?? [] as $b)<li class="mb-1">{{ $b }}</li>@endforeach</ul>
      @if($v->description)<div class="mb-3" style="white-space:pre-wrap">{{ $v->description }}</div>@endif
      <dl class="row small mb-0"><dt class="col-sm-3">Backend terms</dt><dd class="col-sm-9">{{ $v->backend_keywords ?: '—' }} @if($v->backend_keywords)<span class="text-muted">({{ strlen($v->backend_keywords) }} bytes)</span>@endif</dd>
        <dt class="col-sm-3">Special features</dt><dd class="col-sm-9" style="white-space:pre-line">{{ $v->special_features ?: '—' }}</dd>
        <dt class="col-sm-3">A+ content</dt><dd class="col-sm-9">{{ $v->aplus_content ? \Illuminate\Support\Str::limit($v->aplus_content,200) : '—' }}</dd>
        <dt class="col-sm-3">Images / video</dt><dd class="col-sm-9">{{ $v->image_count }} image(s){{ $v->has_video ? ' + video' : '' }}</dd>
        <dt class="col-sm-3">Brand · category</dt><dd class="col-sm-9">{{ $listing->brand ?: '—' }} · {{ $listing->category_path ?: '—' }}</dd></dl></div></div>
    <div class="card mb-3"><div class="card-header d-flex justify-content-between"><span>Target keywords</span><a class="small text-decoration-none" href="{{ route('keywords.index',['product_id'=>$listing->product_id,'marketplace_id'=>$listing->marketplace_id]) }}">Manage</a></div>
      <div class="card-body">@forelse($keywords as $x)<span class="badge rounded-pill text-bg-{{ $x['found']?'success':'light border text-danger' }} me-1 mb-1" title="{{ \App\Models\Keyword::TYPES[$x['k']->keyword_type] }}">{!! $x['found']?'✓':'✗' !!} {{ $x['k']->keyword }}</span>
        @empty<span class="text-muted">No keywords for this product on this channel yet.</span>@endforelse<div class="small text-muted mt-2">✓ appears in the current version (title, bullets, description, backend terms).</div></div></div>
    <div class="card"><div class="card-header">Versions</div>
      <form method="GET" action="{{ route('listings.compare',$listing) }}"><div class="table-responsive"><table class="table table-sm align-middle mb-0"><thead><tr><th class="text-center">A</th><th class="text-center">B</th><th>Version</th><th>Note</th><th class="text-center">Score</th><th>By</th><th></th></tr></thead><tbody>
      @foreach($versions as $i=>$x)<tr><td class="text-center"><input type="radio" name="a" value="{{ $x->id }}" {{ $i===1?'checked':'' }} required></td><td class="text-center"><input type="radio" name="b" value="{{ $x->id }}" {{ $i===0?'checked':'' }} required></td>
        <td class="text-nowrap fw-semibold">v{{ $x->version_no }}@if($listing->current_version_id===$x->id)<span class="badge text-bg-primary ms-1">latest</span>@endif @if($listing->published_version_id===$x->id)<span class="badge text-bg-success ms-1">live</span>@endif<div class="small text-muted fw-normal">{{ $x->created_at->format('d M Y H:i') }}</div></td>
        <td class="small">{{ $x->change_note }}</td><td class="text-center">{{ number_format($x->score,0) }}</td><td class="small">{{ $x->author->name ?? '—' }}</td>
        <td class="text-end text-nowrap">@if($can)@if($listing->published_version_id!==$x->id)<button type="submit" form="pub{{ $x->id }}" class="btn btn-sm btn-outline-success" title="I applied this version on the marketplace">Mark live</button>@endif
          @if($listing->current_version_id!==$x->id)<button type="submit" form="res{{ $x->id }}" class="btn btn-sm btn-outline-secondary">Restore</button>@endif @endif</td></tr>@endforeach</tbody></table></div>
        <div class="card-footer"><button class="btn btn-sm btn-primary">Compare A and B</button></div></form>
      @foreach($versions as $x)<form id="pub{{ $x->id }}" method="POST" action="{{ route('listings.publish',[$listing,$x]) }}">@csrf</form><form id="res{{ $x->id }}" method="POST" action="{{ route('listings.restore',[$listing,$x]) }}" onsubmit="return confirm('Create a new version from v{{ $x->version_no }}?')">@csrf</form>@endforeach
    </div>
  </div>
</div>
@endif
@endsection
