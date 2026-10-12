@extends('layouts.app')
@section('title','Listing score rules')
@section('subtitle','Choose how many points each part is worth and what "good" means. The score is always shown out of 100.')
@section('content')
@php $can=auth()->user()->can('listings.manage'); @endphp
<form method="POST" action="{{ route('listings.rules.update') }}">@csrf @method('PUT')
<div class="card"><div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Criterion</th><th class="text-center">On</th><th style="width:120px">Max points</th><th>Rules</th></tr></thead><tbody>
  @foreach(\App\Models\ListingScoreRule::DEFAULTS as $c=>[$label,$defMax,$defParams]) @php $r=$rows[$c] ?? null; $p=($r->params ?? [])+$defParams; @endphp
  <tr><td class="fw-semibold">{{ $label }}</td><td class="text-center"><input class="form-check-input" type="checkbox" name="active[{{ $c }}]" value="1" @checked($r ? $r->is_active : true) {{ $can?'':'disabled' }}></td>
    <td><input type="number" step="0.5" min="0" max="100" name="max[{{ $c }}]" value="{{ $r->max_points ?? $defMax }}" class="form-control form-control-sm" {{ $can?'':'disabled' }}></td>
    <td><div class="row g-2">@forelse($defParams as $k=>$def)<div class="col-sm-4"><label class="form-label small mb-0">{{ \App\Models\ListingScoreRule::PARAM_LABELS[$k] }}</label><input type="number" min="0" name="params[{{ $c }}][{{ $k }}]" value="{{ $p[$k] }}" class="form-control form-control-sm" {{ $can?'':'disabled' }}></div>
      @empty<div class="col-12 small text-muted">Uses your saved keywords (primary keywords count double). AEO / SEO / GEO keywords are tracked but not scored.</div>@endforelse</div></td></tr>@endforeach
</tbody></table></div>@if($can)<div class="card-footer"><button class="btn btn-primary">Save rules</button> <span class="small text-muted ms-2">Total of active max points is scaled to 100, so weights do not have to add up.</span></div>@endif</div></form>
<a href="{{ route('listings.index') }}" class="btn btn-link mt-2">&larr; Listings</a>
@endsection
