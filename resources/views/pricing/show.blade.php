@extends('layouts.app')
@section('title',$p->sku.' · '.$mp->name)
@section('subtitle',\Illuminate\Support\Str::limit($p->name,70))
@section('content')
@php $inr=fn($v)=>$v===null?'—':'₹'.number_format($v/100,0); $inr2=fn($v)=>$v===null?'—':'₹'.number_format($v/100,2); $x=$a['at']; $can=auth()->user()->can('products.edit');
  $badge=['LOSS'=>['danger','Losing money at this price'],'NO_BREAK_EVEN'=>['dark','Fees are too high: no price breaks even'],'BELOW_MIN'=>['warning','Below your minimum price'],'BELOW_TARGET'=>['info','Earning less than the target margin'],'NO_PRICE'=>['secondary','No price set'],'OK'=>['success','Healthy']]; [$bc,$bl]=$badge[$a['status']]; @endphp
@if($a['no_fees'])<div class="alert alert-warning">No fee rules match this product on {{ $mp->name }}, so fees are treated as ₹0. <a class="alert-link" href="{{ route('fees.index') }}">Add fee rules</a>.</div>@endif
<div class="d-flex flex-wrap gap-2 align-items-center mb-3"><span class="badge text-bg-{{ $bc }} fs-6">{{ $bl }}</span><span class="text-muted small">{{ $a['source'] }}</span>
  <a href="{{ route('pricing.index',['marketplace_id'=>$mp->id]) }}" class="ms-auto btn btn-sm btn-outline-secondary">&larr; All prices</a></div>
<div class="row g-3 mb-3">
  @foreach([['Price now',$inr2($a['current']),null],['Break-even',$inr($a['break_even']),'₹0 profit'],['Minimum price',$inr($a['min_price']),'margin '.$a['min_margin'].'%'],['Target price',$inr($a['target_price']),'margin '.$a['target_margin'].'%'],
    ['Margin now',$x&&$x['margin']!==null?round($x['margin'],1).'%':'—',$x?'profit '.$inr2($x['profit']).' / unit':null],['Max discount',$a['max_discount']!==null?$a['max_discount'].'%':'—','before hitting the minimum']] as [$l,$v,$s])
  <div class="col-6 col-xl-2"><div class="card h-100"><div class="card-body"><div class="small text-muted">{{ $l }}</div><div class="fw-bold fs-4 {{ $l==='Margin now'&&$x&&$x['margin']<0?'text-danger':'' }}">{{ $v }}</div>@if($s)<div class="small text-muted">{{ $s }}</div>@endif</div></div></div>@endforeach
</div>
<div class="row g-3">
  <div class="col-lg-6">
    <div class="card mb-3"><div class="card-header">Where one unit's money goes</div><div class="card-body">
      <form method="GET" class="d-flex gap-2 mb-3"><input type="number" step="1" min="1" name="price" value="{{ $whatIf }}" class="form-control" placeholder="Try another price ₹ (what-if)"><button class="btn btn-outline-primary">Test</button>@if($whatIf)<a href="{{ route('pricing.show',[$p,$mp]) }}" class="btn btn-link">Reset</a>@endif</form>
      @if($x)<table class="table table-sm mb-0">
        <tr><td>Selling price (incl. GST)</td><td class="text-end">{{ $inr2($a['current']) }}</td></tr>
        <tr class="text-muted"><td>− GST ({{ rtrim(rtrim((string)$p->gst_rate,'0'),'.') }}%)</td><td class="text-end">{{ $inr2($a['current']-$x['revenue']) }}</td></tr>
        <tr><td>Revenue (ex-GST)</td><td class="text-end">{{ $inr2($x['revenue']) }}</td></tr>
        <tr><td>− Product cost (COGS + packaging + other)</td><td class="text-end">{{ $inr2($a['unit_cost']) }}</td></tr>
        <tr><td>− Marketplace fees (incl. GST on fees)</td><td class="text-end">{{ $inr2($x['fees']) }}</td></tr>
        <tr><td>− Ads per unit <span class="text-muted small">({{ $a['history_units'] ? 'avg of '.$a['history_units'].' units, '.$a['history_days'].' days' : 'no orders yet: ₹0' }})</span></td><td class="text-end">{{ $inr2($a['ads_per_unit']) }}</td></tr>
        <tr><td>− Returns per unit</td><td class="text-end">{{ $inr2($a['return_per_unit']) }}</td></tr>
        <tr class="fw-bold {{ $x['profit']<0?'text-danger':'text-success' }}"><td>= Profit per unit</td><td class="text-end">{{ $inr2($x['profit']) }} ({{ $x['margin']!==null?round($x['margin'],1).'%':'—' }})</td></tr></table>
      @else<div class="text-muted">Record a price below (or set the product's selling price) to see the breakdown.</div>@endif</div></div>
    <div class="card"><div class="card-header">Competitors <span class="text-muted fw-normal small">· only prices captured in the last {{ (int)\App\Services\SettingsService::get('pricing_competitor_days',30) }} days count</span></div>
      @if($a['competitor_low']!==null)<div class="card-body border-bottom small">Lowest competitor <b>₹{{ number_format($a['competitor_low'],0) }}</b>@if($a['vs_competitor']!==null) · you are <b class="{{ $a['vs_competitor']>0?'text-danger':'text-success' }}">{{ $a['vs_competitor']>0?'+':'' }}{{ $a['vs_competitor'] }}%</b> vs them @endif</div>@endif
      <div class="table-responsive"><table class="table table-sm align-middle mb-0"><tbody>
      @forelse($comps as $c)<tr><td>{{ $c->competitor_name }}@if($c->url)<a href="{{ $c->url }}" target="_blank" rel="noopener noreferrer" class="ms-1 small"><i class="bi bi-box-arrow-up-right"></i></a>@endif<div class="small text-muted">{{ $c->source }}</div></td><td class="text-muted small">{{ $c->captured_on->format('d M Y') }}</td>
        <td class="text-end fw-semibold">₹{{ number_format($c->competitor_price,0) }}</td><td class="text-end small text-muted">{{ $c->our_price?'ours ₹'.number_format($c->our_price,0):'' }}</td>
        <td class="text-end">@if($can)<form method="POST" action="{{ route('pricing.competitor.delete',$c) }}" onsubmit="return confirm('Remove?')">@csrf @method('DELETE')<button class="btn btn-link btn-sm text-danger p-0">×</button></form>@endif</td></tr>
      @empty<tr><td class="text-muted py-3">No competitor prices yet.</td></tr>@endforelse</tbody></table></div>
      @if($can)<div class="card-body border-top"><form method="POST" action="{{ route('pricing.competitor',[$p,$mp]) }}" class="row g-2">@csrf
        <div class="col-md-4"><input name="competitor_name" class="form-control form-control-sm" placeholder="Competitor" required></div><div class="col-6 col-md-3"><input type="number" step="1" min="1" name="competitor_price" class="form-control form-control-sm" placeholder="Price ₹" required></div>
        <div class="col-6 col-md-3"><input type="date" name="captured_on" value="{{ now()->toDateString() }}" max="{{ now()->toDateString() }}" class="form-control form-control-sm" required></div><div class="col-md-2"><button class="btn btn-sm btn-outline-primary w-100">Add</button></div>
        <div class="col-md-5"><input name="source" class="form-control form-control-sm" placeholder="Source (manual check, tool...)"></div><div class="col-md-7"><input type="url" name="url" class="form-control form-control-sm" placeholder="Link (optional)"></div></form>
        <div class="small text-muted mt-2">Type prices you checked yourself or import them (Imports → Competitor prices). This system does not scrape marketplaces.</div></div>@endif</div>
  </div>
  <div class="col-lg-6">
    <div class="card mb-3"><div class="card-header">Price history</div><div class="card-body">
      @if($can)<form method="POST" action="{{ route('pricing.price',[$p,$mp]) }}" class="row g-2 mb-3">@csrf
        <div class="col-6 col-md-3"><input type="number" step="0.01" min="0.01" name="price" class="form-control form-control-sm" placeholder="New price ₹" required></div><div class="col-6 col-md-3"><input type="date" name="effective_on" value="{{ now()->toDateString() }}" max="{{ now()->toDateString() }}" class="form-control form-control-sm"></div>
        <div class="col-md-4"><input name="reason" class="form-control form-control-sm" placeholder="Why? (optional)"></div><div class="col-md-2"><button class="btn btn-sm btn-primary w-100">Record</button></div></form>
        <div class="small text-muted mb-3">Records the price you set on {{ $mp->name }} so profit analysis stays honest. It does not change anything on the marketplace.</div>@endif
      <div style="height:200px" class="mb-3"><canvas id="pc"></canvas></div>
      <table class="table table-sm mb-0"><tbody>@forelse($history as $h)<tr><td class="text-nowrap">{{ $h->effective_on->format('d M Y') }}</td><td class="fw-semibold">₹{{ number_format($h->price,2) }}</td>
        <td class="text-muted small">{{ $h->previous_price!==null?'was ₹'.number_format($h->previous_price,2):'first record' }}</td><td class="small">{{ $h->reason }}</td><td class="small text-muted text-end">{{ $h->user->name ?? '' }}</td></tr>
      @empty<tr><td class="text-muted">Nothing recorded yet. The product's master selling price is used until you record one.</td></tr>@endforelse</tbody></table></div></div>
  </div>
</div>
@push('scripts')<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script><script>
const H=@json($history->sortBy('effective_on')->values()->map(fn($h)=>['d'=>$h->effective_on->toDateString(),'v'=>(float)$h->price])),
      C=@json($comps->sortBy('captured_on')->groupBy(fn($c)=>$c->captured_on->toDateString())->map(fn($g)=>(float)$g->min('competitor_price')));
const days=[...new Set([...H.map(x=>x.d),...Object.keys(C)])].sort(), ours=days.map(d=>{const r=H.filter(x=>x.d<=d).pop();return r?r.v:null;}), comp=days.map(d=>C[d]??null);
new Chart(document.getElementById('pc'),{type:'line',data:{labels:days,datasets:[{label:'Our price',data:ours,borderColor:'#3874ff',stepped:true,spanGaps:true},{label:'Lowest competitor',data:comp,borderColor:'#ed2b2a',spanGaps:true,pointRadius:4}]},options:{maintainAspectRatio:false}});
</script>@endpush
@endsection
