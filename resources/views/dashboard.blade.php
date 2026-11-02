@extends('layouts.app')
@section('title','Dashboard')
@section('subtitle',"Here's what's going on at your business right now")
@section('content')
@php
  $inr=fn($v,$d=0)=>($v<0?'−':'').'₹'.number_format(abs((float)$v),$d); $pct=fn($v)=>$v===null?'—':round($v,1).'%';
  $arrow=fn($d)=>$d===null?'':'<span class="small '.($d>=0?'text-success':'text-danger').'">'.($d>=0?'▲':'▼').' '.abs($d).'%</span>';
  $q=fn($a)=>route('dashboard',array_filter(array_merge(request()->query(),$a),fn($v)=>$v!==null));
  $presets=['Today'=>[now()->toDateString(),now()->toDateString()],'Yesterday'=>[now()->subDay()->toDateString(),now()->subDay()->toDateString()],'7 days'=>[now()->subDays(6)->toDateString(),now()->toDateString()],
    '30 days'=>[now()->subDays(29)->toDateString(),now()->toDateString()],'This month'=>[now()->startOfMonth()->toDateString(),now()->toDateString()],
    'Last month'=>[now()->subMonthNoOverflow()->startOfMonth()->toDateString(),now()->subMonthNoOverflow()->endOfMonth()->toDateString()]];
@endphp
@if($error)<div class="alert alert-danger">{{ $error }}</div>@endif
<div class="card mb-3"><div class="card-body">
  <div class="d-flex flex-wrap gap-1 mb-2">@foreach($presets as $l=>[$a,$b])<a href="{{ $q(['from'=>$a,'to'=>$b]) }}" class="btn btn-sm btn-{{ $f['from']===$a&&$f['to']===$b?'primary':'outline-secondary' }}">{{ $l }}</a>@endforeach</div>
  <form class="row g-2" method="GET">
    <div class="col-6 col-md-2"><input type="date" name="from" value="{{ $f['from'] }}" class="form-control"></div><div class="col-6 col-md-2"><input type="date" name="to" value="{{ $f['to'] }}" class="form-control"></div>
    <div class="col-md-3"><select name="marketplace_id" class="form-select"><option value="">All channels</option>@foreach($marketplaces as $m)<option value="{{ $m->id }}" @selected($f['marketplace_id']===$m->id)>{{ $m->name }}</option>@endforeach</select></div>
    <div class="col-md-2"><button class="btn btn-outline-primary">Apply</button></div>
  </form></div></div>

@if($needsRebuild)<div class="alert alert-warning d-flex flex-wrap justify-content-between align-items-center gap-2"><span>You have orders from before the dashboard speed tables existed. Build them once to see your numbers.</span>
  @can('finance.manage')<form method="POST" action="{{ route('dashboard.rebuild') }}">@csrf<button class="btn btn-sm btn-warning">Build dashboard numbers</button></form>@else<span class="small">Ask someone with finance access, or run <code>php artisan summary:rebuild</code>.</span>@endcan</div>@endif

@if($d) @php $k=$d['kpi']; @endphp
@if($setup['products']===0)<div class="alert alert-info">Start here: add <a class="alert-link" href="{{ route('categories.index') }}">categories</a>, then your <a class="alert-link" href="{{ route('products.create') }}">first product</a>, then import orders from the Amazon or Flipkart page.</div>@endif

<div class="row g-3 mb-3">
  <div class="col-6 col-xl-2"><div class="card h-100"><div class="card-body"><div class="small text-muted">1 · How much did I sell?</div><div class="fw-bold fs-4">{{ $inr($k['sales']) }}</div><div class="small text-muted">incl. GST {!! $arrow($k['delta']['sales']) !!}</div></div></div></div>
  <div class="col-6 col-xl-2"><div class="card h-100"><div class="card-body"><div class="small text-muted">Orders · units</div><div class="fw-bold fs-4">{{ number_format($k['orders']) }} <span class="fs-6 text-muted">· {{ number_format($k['units']) }}</span></div><div class="small">{!! $arrow($k['delta']['orders']) !!}</div></div></div></div>
  <div class="col-6 col-xl-2"><div class="card h-100"><div class="card-body"><div class="small text-muted">Order profit</div><div class="fw-bold fs-4 {{ $k['orderProfit']<0?'text-danger':'text-success' }}">{{ $inr($k['orderProfit']) }}</div><div class="small text-muted">margin {{ $pct($k['margin']) }} {!! $arrow($k['delta']['profit']) !!}</div></div></div></div>
  <div class="col-6 col-xl-2"><div class="card h-100 border-{{ $k['net']<0?'danger':'success' }}"><div class="card-body"><div class="small text-muted">2 · What did I actually earn?</div><div class="fw-bold fs-4 {{ $k['net']<0?'text-danger':'text-success' }}">{{ $inr($k['net']) }}</div><div class="small text-muted">after expenses &amp; ad leaks</div></div></div></div>
  <div class="col-6 col-xl-2"><div class="card h-100"><div class="card-body"><div class="small text-muted">Ad spend</div><div class="fw-bold fs-4">{{ $inr($k['adSpend']) }}</div><div class="small text-muted">ACOS {{ $pct($k['acos']) }} · ROAS {{ $k['roas']===null?'—':round($k['roas'],2).'x' }}</div></div></div></div>
  <div class="col-6 col-xl-2"><div class="card h-100"><div class="card-body"><div class="small text-muted">Return rate · cancelled</div><div class="fw-bold fs-4">{{ $pct($k['returnRate']) }} <span class="fs-6 text-muted">· {{ $pct($k['cancelRate']) }}</span></div><div class="small text-muted">{{ $k['returns'] }} return(s)</div></div></div></div>
</div>

<div class="row g-3 mb-3">
  <div class="col-xl-7"><div class="card h-100"><div class="card-header d-flex justify-content-between"><span>5 · What should I do today?</span><span class="badge text-bg-{{ count($actions)?'danger':'success' }}">{{ count($actions) }}</span></div>
    <ul class="list-group list-group-flush">@forelse($actions as $x)
      <li class="list-group-item d-flex gap-3 align-items-start"><i class="bi {{ $x['icon'] }} fs-4 text-{{ $x['sev']>=3?'danger':($x['sev']==2?'warning':'secondary') }}"></i>
        <div class="flex-grow-1"><div class="fw-semibold">{{ $x['title'] }}</div><div class="small text-muted">{{ $x['detail'] }}</div></div><a href="{{ $x['url'] }}" class="btn btn-sm btn-outline-primary align-self-center">Open</a></li>
    @empty<li class="list-group-item text-center text-muted py-4"><i class="bi bi-check2-circle fs-3 text-success"></i><div>Nothing needs your attention right now.</div></li>@endforelse</ul></div></div>
  <div class="col-xl-5"><div class="card h-100"><div class="card-header">Where the money went</div><div class="card-body p-0"><table class="table table-sm mb-0">
    @foreach([['Revenue (ex-GST)',$k['revenue'],1],['Product &amp; packaging cost',-$k['cogs'],0],['Marketplace fees',-$k['fees'],0],['Refunds',-$k['refunds'],0],['Recovered (claims / reimbursements)',$k['recovery'],0],['Ads on sales',-$k['adsAllocated'],0]] as [$l,$v,$b])
      <tr class="{{ $b?'fw-semibold':'' }}"><td>{!! $l !!}</td><td class="text-end {{ $v<0?'text-muted':'' }}">{{ $inr($v) }}</td></tr>@endforeach
    <tr class="fw-bold border-top"><td>= Order profit</td><td class="text-end {{ $k['orderProfit']<0?'text-danger':'text-success' }}">{{ $inr($k['orderProfit']) }}</td></tr>
    <tr><td>Expenses</td><td class="text-end text-muted">{{ $inr(-$k['expenses']) }}</td></tr><tr><td>Ad spend not tied to a sale</td><td class="text-end text-muted">{{ $inr(-$k['unallocated']) }}</td></tr>
    <tr class="fw-bold border-top"><td>= Net profit</td><td class="text-end {{ $k['net']<0?'text-danger':'text-success' }}">{{ $inr($k['net']) }}</td></tr></table>
    @if($k['settledPct']!==null)<div class="p-2 small text-muted border-top">{{ round($k['settledPct']) }}% of profit lines use real payout data; the rest are estimates.</div>@endif</div></div></div>
</div>

<div class="row g-3 mb-3">
  <div class="col-lg-6"><div class="card"><div class="card-header">Sales &amp; orders</div><div class="card-body"><div style="height:240px"><canvas id="cSales"></canvas></div></div></div></div>
  <div class="col-lg-6"><div class="card"><div class="card-header">Profit</div><div class="card-body"><div style="height:240px"><canvas id="cProfit"></canvas></div></div></div></div>
  <div class="col-lg-4"><div class="card"><div class="card-header">Advertising: spend vs sales</div><div class="card-body"><div style="height:200px"><canvas id="cAds"></canvas></div></div></div></div>
  <div class="col-lg-4"><div class="card"><div class="card-header">Refunds</div><div class="card-body"><div style="height:200px"><canvas id="cRef"></canvas></div></div></div></div>
  <div class="col-lg-4"><div class="card"><div class="card-header">Channel comparison: profit</div><div class="card-body"><div style="height:200px"><canvas id="cCh"></canvas></div></div></div></div>
</div>

<div class="row g-3 mb-3">
  <div class="col-lg-6"><div class="card h-100"><div class="card-header">3 · Products that earn the most</div><table class="table table-sm align-middle mb-0"><tbody>
    @forelse($d['products']['top'] as $p)<tr><td><a class="fw-semibold text-decoration-none" href="{{ route('products.show',$p->product_id) }}">{{ $p->sku }}</a><div class="small text-muted">{{ \Illuminate\Support\Str::limit($p->name,34) }}</div></td><td class="text-end small text-muted">{{ number_format($p->units) }} units</td><td class="text-end fw-bold {{ $p->profit<0?'text-danger':'text-success' }}">{{ $inr($p->profit) }}</td></tr>
    @empty<tr><td class="text-muted py-3">No sales in this period.</td></tr>@endforelse</tbody></table></div></div>
  <div class="col-lg-6"><div class="card h-100"><div class="card-header">4 · Where am I losing money? <span class="text-muted fw-normal small">(lowest profit)</span></div><table class="table table-sm align-middle mb-0"><tbody>
    @forelse($d['products']['worst'] as $p)<tr><td><a class="fw-semibold text-decoration-none" href="{{ route('products.show',$p->product_id) }}">{{ $p->sku }}</a><div class="small text-muted">{{ \Illuminate\Support\Str::limit($p->name,34) }}</div></td><td class="text-end small text-muted">{{ number_format($p->units) }} units</td><td class="text-end fw-bold {{ $p->profit<0?'text-danger':'' }}">{{ $inr($p->profit) }}</td></tr>
    @empty<tr><td class="text-muted py-3">No sales in this period.</td></tr>@endforelse</tbody></table>
    @can('finance.view')<div class="card-footer small"><a href="{{ route('profit.index',['from'=>$f['from'],'to'=>$f['to'],'sort'=>'profit_asc']) }}" class="text-decoration-none">Full product profitability</a></div>@endcan</div></div>
</div>

<div class="card mb-3"><div class="card-header">Channels</div><div class="table-responsive"><table class="table align-middle mb-0">
  <thead><tr><th>Channel</th><th class="text-end">Orders</th><th class="text-end">Sales</th><th class="text-end">Profit</th><th class="text-end">Margin</th><th class="text-end">Ad spend</th><th class="text-end">ACOS</th><th class="text-end">Returns</th><th class="text-end">Refunded</th></tr></thead><tbody>
  @foreach($d['channels'] as $c)<tr><td class="fw-semibold">{{ $c['name'] }}</td><td class="text-end">{{ number_format($c['orders']) }}</td><td class="text-end">{{ $inr($c['sales']) }}</td><td class="text-end fw-bold {{ $c['profit']<0?'text-danger':'text-success' }}">{{ $inr($c['profit']) }}</td>
    <td class="text-end">{{ $pct($c['margin']) }}</td><td class="text-end">{{ $inr($c['adSpend']) }}</td><td class="text-end">{{ $pct($c['acos']) }}</td><td class="text-end">{{ $c['returns'] }}</td><td class="text-end">{{ $inr($c['refunded']) }}</td></tr>@endforeach</tbody></table></div></div>

<div class="card mb-3"><div class="card-header" data-bs-toggle="collapse" data-bs-target="#allk" role="button">All numbers <span class="text-muted fw-normal small">(click)</span></div><div id="allk" class="collapse"><div class="card-body"><div class="row small">
  @foreach([['Total sales (incl. GST)',$inr($k['sales'])],['Orders',number_format($k['orders'])],['Units sold',number_format($k['units'])],['Gross revenue (ex-GST)',$inr($k['revenue'])],['Marketplace fees',$inr($k['fees'])],['Advertising spend',$inr($k['adSpend'])],
    ['Product &amp; packaging cost',$inr($k['cogs'])],['Refunds',$inr($k['refunds'])],['Recovered',$inr($k['recovery'])],['Order profit',$inr($k['orderProfit'])],['Net profit',$inr($k['net'])],['Profit margin',$pct($k['margin'])],
    ['ROAS',$k['roas']===null?'—':round($k['roas'],2).'x'],['ACOS',$pct($k['acos'])],['TACOS',$pct($k['tacos'])],['Return rate',$pct($k['returnRate'])],['Cancellation rate',$pct($k['cancelRate'])],['Clicks · impressions',number_format($k['clicks']).' · '.number_format($k['impressions'])]] as [$l,$v])
    <div class="col-6 col-md-4 col-xl-3 d-flex justify-content-between border-bottom py-1"><span class="text-muted">{!! $l !!}</span><b>{{ $v }}</b></div>@endforeach</div></div></div></div>
@endif

<div class="row g-3">
  @if($claimAlerts->count())<div class="col-lg-6"><div class="card border-danger h-100"><div class="card-header text-danger"><i class="bi bi-exclamation-triangle"></i> Claims that need action</div><ul class="list-group list-group-flush small">
    @foreach($claimAlerts as $c)<li class="list-group-item d-flex flex-wrap justify-content-between gap-2"><a class="text-decoration-none fw-semibold" href="{{ route('claims.show',$c) }}">{{ $c->claim_number }}</a><span class="text-muted">{{ \App\Models\Claim::LABELS[$c->status] }} · ₹{{ number_format($c->claim_amount,0) }}</span>@include('claims._deadline',['claim'=>$c])</li>@endforeach</ul></div></div>@endif
  <div class="col-lg-{{ $claimAlerts->count()?6:12 }}"><div class="card h-100"><div class="card-header">Recent activity</div><ul class="list-group list-group-flush small">
    @forelse($activity as $a)<li class="list-group-item d-flex justify-content-between gap-2"><span><b>{{ $a->action }}</b> <span class="text-muted">by {{ $a->user->name ?? 'system' }}</span></span><span class="text-muted text-nowrap">{{ $a->created_at->diffForHumans() }}</span></li>
    @empty<li class="list-group-item text-muted">No activity yet.</li>@endforelse</ul></div></div>
</div>

@if($d)
@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script>
const S=@json($d['series']), CH=@json($d['channels']);
const dark=document.documentElement.getAttribute('data-bs-theme')==='dark', tick=dark?'#8f9bb8':'#6e7891', grid=dark?'#27304a':'#e3e6ed';
const base={maintainAspectRatio:false,interaction:{mode:'index',intersect:false},plugins:{legend:{labels:{color:tick,boxWidth:12}}},scales:{x:{ticks:{color:tick,maxTicksLimit:8},grid:{display:false}},y:{ticks:{color:tick},grid:{color:grid},beginAtZero:true}}};
const line=(id,sets,extra={})=>new Chart(document.getElementById(id),{type:'line',data:{labels:S.labels,datasets:sets},options:Object.assign({},base,extra)});
const ds=(l,d,c,fill=true)=>({label:l,data:d,borderColor:c,backgroundColor:c+'22',tension:.25,fill,pointRadius:S.labels.length>45?0:3});
new Chart(document.getElementById('cSales'),{data:{labels:S.labels,datasets:[{type:'bar',label:'Sales ₹',data:S.sales,backgroundColor:'#3874ff88',yAxisID:'y'},{type:'line',label:'Orders',data:S.orders,borderColor:'#f5803e',tension:.25,yAxisID:'y1',pointRadius:S.labels.length>45?0:3}]},
  options:Object.assign({},base,{scales:{x:base.scales.x,y:base.scales.y,y1:{position:'right',ticks:{color:tick},grid:{display:false},beginAtZero:true}}})});
line('cProfit',[ds('Profit ₹',S.profit,'#25b003')]); line('cAds',[ds('Ad spend',S.adSpend,'#ed2b2a'),ds('Sales from ads',S.adSales,'#3874ff')]); line('cRef',[ds('Refunds ₹',S.refunds,'#f5803e')]);
new Chart(document.getElementById('cCh'),{type:'bar',data:{labels:CH.map(c=>c.name),datasets:[{label:'Profit ₹',data:CH.map(c=>c.profit),backgroundColor:CH.map(c=>c.profit<0?'#ed2b2a99':'#25b00399')}]},options:Object.assign({},base,{plugins:{legend:{display:false}}})});
</script>
@endpush
@endif
@endsection
