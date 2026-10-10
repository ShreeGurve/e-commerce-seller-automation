@extends('layouts.app')
@section('title','Profit')
@section('subtitle','Which products actually make money, and where it leaks')
@section('content')
@php
  $t=$totals; $rev=(float)$t->revenue; $op=(float)$t->profit; $net=$op-$expenses-$adsUnallocated; $margin=$rev>0?round($op/$rev*100,1):0;
  $coverage=$t->all_rows>0?round($t->actual_rows/$t->all_rows*100):0;
  $q=fn($a)=>route('profit.index',array_merge(request()->query(),$a));
  $presets=['Today'=>[now()->toDateString(),now()->toDateString()],'7 days'=>[now()->subDays(6)->toDateString(),now()->toDateString()],'30 days'=>[now()->subDays(29)->toDateString(),now()->toDateString()],
    'This month'=>[now()->startOfMonth()->toDateString(),now()->toDateString()],'Last month'=>[now()->subMonthNoOverflow()->startOfMonth()->toDateString(),now()->subMonthNoOverflow()->endOfMonth()->toDateString()]];
@endphp
<div class="card mb-3"><div class="card-body">
  <div class="d-flex flex-wrap gap-1 mb-2">@foreach($presets as $l=>[$a,$b])<a href="{{ $q(['from'=>$a,'to'=>$b]) }}" class="btn btn-sm btn-{{ $f['from']===$a&&$f['to']===$b?'primary':'outline-secondary' }}">{{ $l }}</a>@endforeach</div>
  <form class="row g-2" method="GET"><input type="hidden" name="sort" value="{{ $f['sort'] }}">
    <div class="col-6 col-md-2"><input type="date" name="from" value="{{ $f['from'] }}" class="form-control"></div>
    <div class="col-6 col-md-2"><input type="date" name="to" value="{{ $f['to'] }}" class="form-control"></div>
    <div class="col-md-3"><select name="marketplace_id" class="form-select"><option value="">All channels</option>@foreach($marketplaces as $m)<option value="{{ $m->id }}" @selected($f['marketplace_id']==$m->id)>{{ $m->name }}</option>@endforeach</select></div>
    <div class="col-md-3"><select name="category_id" class="form-select"><option value="">All categories</option>@foreach($categories as $c)<option value="{{ $c->id }}" @selected($f['category_id']==$c->id)>{{ $c->name }}</option>@endforeach</select></div>
    <div class="col-md-2 d-flex gap-2"><button class="btn btn-outline-primary">Apply</button>
      @can('finance.manage')<button type="submit" form="recalcForm" class="btn btn-outline-secondary" title="Re-apply fee rules to all orders"><i class="bi bi-arrow-repeat"></i></button>@endcan</div>
  </form>
  @can('finance.manage')<form id="recalcForm" method="POST" action="{{ route('profit.recalculate') }}" onsubmit="return confirm('Recalculate profit for all orders with current fee rules?')">@csrf</form>@endcan
</div></div>

<div class="row g-3 mb-3">
  <div class="col-md-6 col-xl-3"><div class="card h-100"><div class="card-body"><div class="small text-muted">Order profit {{ $coverage==100?'(actual)':'(estimated)' }}</div>
    <div class="sg-bignum {{ $op<0?'text-danger':'text-success' }}">₹{{ number_format($op,2) }}</div><div class="small text-muted mt-1">{{ $margin }}% margin · {{ number_format($t->orders) }} orders · {{ number_format($t->units) }} units</div>
    <div class="small mt-1"><span class="badge text-bg-{{ $coverage==100?'success':'secondary' }}">{{ $coverage }}% settled</span></div></div></div></div>
  <div class="col-md-6 col-xl-3"><div class="card h-100"><div class="card-body"><div class="small text-muted">Expenses (period)</div><div class="sg-bignum">₹{{ number_format($expenses,2) }}</div><div class="small text-muted mt-1"><a href="{{ route('expenses.index',['from'=>$f['from'],'to'=>$f['to']]) }}" class="text-decoration-none">View expenses</a></div>
    @if($adsUnallocated>0)<div class="small text-danger mt-1">+ ₹{{ number_format($adsUnallocated,2) }} ad spend not tied to a sale</div>@endif</div></div></div>
  <div class="col-md-6 col-xl-3"><div class="card h-100"><div class="card-body"><div class="small text-muted">Net profit after expenses &amp; unassigned ad spend</div><div class="sg-bignum {{ $net<0?'text-danger':'text-success' }}">₹{{ number_format($net,2) }}</div></div></div></div>
  <div class="col-md-6 col-xl-3"><div class="card h-100"><div class="card-body"><div class="small text-muted">Revenue (ex-GST)</div><div class="sg-bignum">₹{{ number_format($rev,2) }}</div></div></div></div>
</div>
<div class="card mb-3"><div class="card-header">Where the money goes</div><div class="card-body"><div class="row text-center g-2">
  @foreach(['Revenue'=>[$t->revenue,''],'COGS'=>[$t->cogs,'−'],'Marketplace fees'=>[$t->fees,'−'],'Refunds'=>[$t->refunded,'−'],'Recovered'=>[$t->recovery,'+'],'Ads'=>[$t->ads,'−']] as $l=>[$v,$sg])
    <div class="col-4 col-md-2"><div class="small text-muted">{{ $sg }} {{ $l }}</div><div class="fw-bold">₹{{ number_format((float)$v,0) }}</div></div>@endforeach</div></div></div>

@if($byMarketplace->count()>1)
<div class="card mb-3"><div class="card-header">Channel comparison</div><div class="table-responsive"><table class="table align-middle mb-0">
  <thead><tr><th>Channel</th><th class="text-end">Orders</th><th class="text-end">Revenue</th><th class="text-end">Fees</th><th class="text-end">Refunds</th><th class="text-end">Profit</th><th class="text-end">Margin</th></tr></thead><tbody>
  @foreach($byMarketplace as $m)@php $mr=(float)$m->revenue; $mp=(float)$m->profit; @endphp
    <tr><td class="fw-semibold">{{ $marketplaces[$m->marketplace_id]->name ?? '—' }}</td><td class="text-end">{{ $m->orders }}</td><td class="text-end">₹{{ number_format($mr,2) }}</td><td class="text-end">₹{{ number_format((float)$m->fees,2) }}</td>
      <td class="text-end">₹{{ number_format((float)$m->refunded,2) }}</td><td class="text-end fw-bold {{ $mp<0?'text-danger':'text-success' }}">₹{{ number_format($mp,2) }}</td><td class="text-end">{{ $mr>0?round($mp/$mr*100,1):0 }}%</td></tr>@endforeach</tbody></table></div></div>
@endif

<div class="card"><div class="card-header d-flex flex-wrap justify-content-between gap-2"><span>Product profitability</span>
  <span class="small">@foreach(['profit_desc'=>'Highest profit','profit_asc'=>'Lowest profit (losses first)','revenue_desc'=>'Highest sales','returns_desc'=>'Highest refunds','units_desc'=>'Most units'] as $k=>$l)<a href="{{ $q(['sort'=>$k]) }}" class="ms-2 text-decoration-none {{ $f['sort']===$k?'fw-bold':'text-muted' }}">{{ $l }}</a>@endforeach</span></div>
  <div class="table-responsive"><table class="table table-hover align-middle mb-0">
  <thead><tr><th>Product</th><th class="text-end">Units</th><th class="text-end">Sales</th><th class="text-end">COGS</th><th class="text-end">Fees</th><th class="text-end">Refunds</th><th class="text-end">Profit</th><th class="text-end">Margin</th><th></th></tr></thead><tbody>
  @forelse($byProduct as $p)@php $pr=(float)$p->revenue; $pp=(float)$p->profit; $n=$names[$p->product_id] ?? null; @endphp
    <tr><td><a class="fw-semibold text-decoration-none" href="{{ route('products.show',$p->product_id) }}">{{ $n->sku ?? '#'.$p->product_id }}</a><div class="small text-muted">{{ \Illuminate\Support\Str::limit($n->name ?? '',40) }}</div></td>
      <td class="text-end">{{ $p->units }}</td><td class="text-end">₹{{ number_format($pr,2) }}</td><td class="text-end">₹{{ number_format((float)$p->cogs,2) }}</td><td class="text-end">₹{{ number_format((float)$p->fees,2) }}</td>
      <td class="text-end">₹{{ number_format((float)$p->refunded,2) }}</td><td class="text-end fw-bold {{ $pp<0?'text-danger':'text-success' }}">₹{{ number_format($pp,2) }}</td><td class="text-end">{{ $pr>0?round($pp/$pr*100,1):0 }}%</td>
      <td>@if($pp<0)<span class="badge text-bg-danger">Loss</span>@elseif($pr>0 && $pp/$pr<0.1)<span class="badge text-bg-warning">Thin</span>@elseif($pr>0 && (float)$p->refunded/$pr>0.15)<span class="badge text-bg-warning">High returns</span>@endif</td></tr>
  @empty<tr><td colspan="9" class="text-center text-muted py-5">No sales in this period. Create or import orders first.</td></tr>@endforelse
  </tbody></table></div>
  <div class="card-footer small text-muted">Estimated = your fee rules + cost on order date. It becomes actual when marketplace settlements are imported (next update). "Thin" = margin under 10%.</div></div>
@endsection
