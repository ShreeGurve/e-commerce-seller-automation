@extends('layouts.app')
@section('title','Pricing')
@section('subtitle','The lowest price that still pays, from your real costs, fees, ad spend and returns. Advice only.')
@section('content')
@php $inr=fn($p)=>$p===null?'—':'₹'.number_format($p/100,0); $inr2=fn($p)=>$p===null?'—':'₹'.number_format($p/100,2);
  $badge=['LOSS'=>['danger','Losing money'],'NO_BREAK_EVEN'=>['dark','Fees too high'],'BELOW_MIN'=>['warning','Below minimum'],'BELOW_TARGET'=>['info','Below target'],'NO_PRICE'=>['secondary','No price set'],'OK'=>['success','OK']]; @endphp
@if($noFees)<div class="alert alert-warning">No fee rules match {{ $mp->name }} yet, so fees are treated as ₹0 and every price below looks too good. <a class="alert-link" href="{{ route('fees.index') }}">Add your fee rules</a>.</div>@endif
<div class="card mb-3"><div class="card-body"><form class="row g-2" method="GET">
  <div class="col-md-3"><select name="marketplace_id" class="form-select" onchange="this.form.submit()">@foreach($marketplaces as $m)<option value="{{ $m->id }}" @selected($mp->id===$m->id)>{{ $m->name }}</option>@endforeach</select></div>
  <div class="col-md-3"><input name="q" value="{{ request('q') }}" class="form-control" placeholder="Search SKU or name"></div>
  <div class="col-md-6 d-flex flex-wrap gap-1 align-items-center"><button class="btn btn-outline-primary me-2">Search</button>
    <a href="{{ route('pricing.index',['marketplace_id'=>$mp->id]) }}" class="badge rounded-pill text-bg-{{ request('status')?'light border':'dark' }} fs-6 text-decoration-none">All</a>
    @foreach($badge as $k=>[$c,$l])@if(($counts[$k] ?? 0)>0)<a href="{{ route('pricing.index',['marketplace_id'=>$mp->id,'status'=>$k,'q'=>request('q')]) }}" class="badge rounded-pill text-bg-{{ request('status')===$k?$c:'light border' }} fs-6 text-decoration-none">{{ $l }} {{ $counts[$k] }}</a>@endif @endforeach</div>
</form></div></div>
<div class="card"><div class="table-responsive"><table class="table table-hover align-middle mb-0 small">
  <thead><tr><th>Product</th><th class="text-end">Price now</th><th class="text-end">Unit cost</th><th class="text-end">Fees</th><th class="text-end">Ads / unit</th><th class="text-end">Returns / unit</th>
    <th class="text-end">Break-even</th><th class="text-end">Minimum</th><th class="text-end">Target</th><th class="text-end">Margin now</th><th class="text-end">Max discount</th><th class="text-end">vs competitor</th><th>Status</th></tr></thead><tbody>
  @forelse($rows as $r) @php [$c,$l]=$badge[$r['status']]; $x=$r['at']; @endphp
    <tr><td><a class="fw-semibold text-decoration-none" href="{{ route('pricing.show',[$r['product'],$mp]) }}">{{ $r['product']->sku }}</a><div class="text-muted">{{ \Illuminate\Support\Str::limit($r['product']->name,28) }}</div></td>
      <td class="text-end fw-semibold">{{ $inr($r['current']) }}</td><td class="text-end">{{ $inr2($r['unit_cost']) }}</td><td class="text-end">{{ $x?$inr2($x['fees']):'—' }}</td>
      <td class="text-end">{{ $r['history_units']?$inr2($r['ads_per_unit']):'—' }}</td><td class="text-end">{{ $r['history_units']?$inr2($r['return_per_unit']):'—' }}</td>
      <td class="text-end">{{ $inr($r['break_even']) }}</td><td class="text-end">{{ $inr($r['min_price']) }}</td><td class="text-end">{{ $inr($r['target_price']) }}</td>
      <td class="text-end fw-bold {{ $x && $x['margin']<0?'text-danger':'' }}">{{ $x && $x['margin']!==null ? round($x['margin'],1).'%' : '—' }}</td>
      <td class="text-end">{{ $r['max_discount']!==null ? $r['max_discount'].'%' : '—' }}</td>
      <td class="text-end">@if($r['vs_competitor']!==null)<span class="{{ $r['vs_competitor']>0?'text-danger':'text-success' }}">{{ $r['vs_competitor']>0?'+':'' }}{{ $r['vs_competitor'] }}%</span>@else — @endif</td>
      <td><span class="badge text-bg-{{ $c }}">{{ $l }}</span></td></tr>
  @empty<tr><td colspan="13" class="text-center text-muted py-5">No active products.</td></tr>@endforelse
  </tbody></table></div>@if($rows->hasPages())<div class="card-footer">{{ $rows->links() }}</div>@endif
  <div class="card-footer small text-muted">Prices include GST and are rounded up to the next rupee. Break-even = ₹0 profit. Minimum and target use the margins in Settings. Fees come from your fee rules (with GST on fees). Ads and returns per unit are averages of your recent orders (look-back in Settings); "—" means no orders yet, assumed ₹0.@if($capped) Showing the first 300 products.@endif</div></div>
@endsection
