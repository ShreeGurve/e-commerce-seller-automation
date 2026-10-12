@extends('layouts.app')
@section('title','Compare versions')
@section('subtitle',$listing->product->sku.' · '.$listing->marketplace->name.' · v'.$a->version_no.' → v'.$b->version_no)
@section('content')
@php $d=$b->score-$a->score; @endphp
<div class="row g-3 mb-3">
  <div class="col-md-4"><div class="card"><div class="card-body text-center"><div class="small text-muted">v{{ $a->version_no }} (older)</div><div class="fs-2 fw-bold">{{ number_format($a->score,0) }}</div></div></div></div>
  <div class="col-md-4"><div class="card"><div class="card-body text-center"><div class="small text-muted">Change</div><div class="fs-2 fw-bold text-{{ $d>0?'success':($d<0?'danger':'muted') }}">{{ $d>0?'+':'' }}{{ number_format($d,0) }}</div></div></div></div>
  <div class="col-md-4"><div class="card"><div class="card-body text-center"><div class="small text-muted">v{{ $b->version_no }} (newer)</div><div class="fs-2 fw-bold">{{ number_format($b->score,0) }}</div></div></div></div>
</div>
<div class="small text-muted mb-2"><span class="bg-danger-subtle text-danger px-1">removed</span> <span class="bg-success-subtle text-success px-1">added</span></div>
@foreach([['Title',$diff['title']],['Description',$diff['description']],['Backend search terms',$diff['backend']],['Special features',$diff['features']],['A+ content',$diff['aplus']]] as [$label,$p])
  @if(trim(strip_tags($p[0])).trim(strip_tags($p[1]))!=='')
  <div class="card mb-3"><div class="card-header">{{ $label }}</div><div class="card-body"><div class="row g-3"><div class="col-md-6" style="white-space:pre-wrap;word-break:break-word">{!! $p[0] ?: '<span class="text-muted">—</span>' !!}</div><div class="col-md-6" style="white-space:pre-wrap;word-break:break-word">{!! $p[1] ?: '<span class="text-muted">—</span>' !!}</div></div></div></div>@endif
@endforeach
@if($diff['bullets'])<div class="card mb-3"><div class="card-header">Bullet points</div><ul class="list-group list-group-flush">@foreach($diff['bullets'] as $i=>$p)<li class="list-group-item"><div class="row g-3"><div class="col-md-6">{!! $p[0] ?: '<span class="text-muted">—</span>' !!}</div><div class="col-md-6">{!! $p[1] ?: '<span class="text-muted">—</span>' !!}</div></div></li>@endforeach</ul></div>@endif
<div class="card mb-3"><div class="card-body small">Images: {{ $a->image_count }} → {{ $b->image_count }} · Video: {{ $a->has_video?'yes':'no' }} → {{ $b->has_video?'yes':'no' }}</div></div>
<a href="{{ route('listings.show',$listing) }}" class="btn btn-outline-secondary">Back to listing</a>
@endsection
