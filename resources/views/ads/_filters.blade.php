@php
  $pq = fn($a)=>request()->fullUrlWithQuery($a);
  $presets=['7 days'=>[now()->subDays(6)->toDateString(),now()->toDateString()],'30 days'=>[now()->subDays(29)->toDateString(),now()->toDateString()],
    'This month'=>[now()->startOfMonth()->toDateString(),now()->toDateString()],'Last month'=>[now()->subMonthNoOverflow()->startOfMonth()->toDateString(),now()->subMonthNoOverflow()->endOfMonth()->toDateString()]];
@endphp
<div class="card mb-3"><div class="card-body">
  <div class="d-flex flex-wrap gap-1 mb-2">@foreach($presets as $l=>[$a,$b])<a href="{{ $pq(['from'=>$a,'to'=>$b]) }}" class="btn btn-sm btn-{{ $f['from']===$a&&$f['to']===$b?'primary':'outline-secondary' }}">{{ $l }}</a>@endforeach</div>
  <form class="row g-2" method="GET">
    <div class="col-6 col-md-2"><input type="date" name="from" value="{{ $f['from'] }}" class="form-control"></div>
    <div class="col-6 col-md-2"><input type="date" name="to" value="{{ $f['to'] }}" class="form-control"></div>
    <div class="col-md-3"><select name="marketplace_id" class="form-select"><option value="">All channels</option>@foreach($marketplaces as $m)<option value="{{ $m->id }}" @selected($f['marketplace_id']==$m->id)>{{ $m->name }}</option>@endforeach</select></div>
    @isset($campaigns)<div class="col-md-3"><select name="campaign_id" class="form-select"><option value="">All campaigns</option>@foreach($campaigns as $c)<option value="{{ $c->id }}" @selected($f['campaign_id']==$c->id)>{{ \Illuminate\Support\Str::limit($c->name,40) }}</option>@endforeach</select></div>
    <div class="col-md-3"><input name="q" value="{{ $f['q'] }}" class="form-control" placeholder="Search text"></div>@endisset
    <div class="col-md-2"><button class="btn btn-outline-primary">Apply</button></div>
  </form></div></div>
