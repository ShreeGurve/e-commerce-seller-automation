@extends('layouts.app')
@section('title','Ad insights')
@section('subtitle','Where to spend more, where to stop. Recommendations only: nothing changes in Amazon or Flipkart by itself.')
@section('content')
@include('ads._nav')
@include('ads._filters')
@forelse($i['levels'] as $lv=>$L)
  <h5 class="fw-bold mt-4 mb-2">{{ $L['label'] }}</h5>
  <div class="row g-3">
  @foreach([['winners','Winners','success','Sold, ACOS at or under '.$i['target'].'%. Consider raising bids or moving to exact match.'],
            ['wasted','Wasted spend','danger','Spent ₹'.number_format($i['wasteMin'],0).'+ with no sale. Consider pausing or adding as negative.'],
            ['potential','Needs work (high clicks, low conversion)','warning','20+ clicks but under 3% convert. Check price, listing and relevance before cutting.']] as [$k,$title,$c,$hint])
    <div class="col-xl-4"><div class="card h-100"><div class="card-header text-{{ $c==='warning'?'warning':$c }}">{{ $title }} <span class="badge text-bg-{{ $c }}">{{ $L[$k]->count() }}</span></div>
      <div class="px-3 pt-2 small text-muted">{{ $hint }}</div>
      <div class="table-responsive"><table class="table table-sm align-middle mb-0"><tbody>
      @forelse($L[$k] as $r) @php $sp=(float)$r->spend; $sl=(float)$r->sales; @endphp
        <tr><td>{{ \Illuminate\Support\Str::limit($r->label,34) }}<div class="small text-muted">{{ \Illuminate\Support\Str::limit($r->campaign,28) }}</div></td>
          <td class="text-end small">₹{{ number_format($sp,0) }} spent<br>{{ $r->clicks }} clicks · {{ $r->orders }} orders</td>
          <td class="text-end small">@if($sl>0)ACOS {{ round($sp/$sl*100,1) }}%@else — @endif</td></tr>
      @empty<tr><td class="text-muted small py-3">Nothing flagged.</td></tr>@endforelse</tbody></table></div></div></div>
  @endforeach
  </div>
@empty
  <div class="alert alert-info">No keyword or search-term data yet. Import an "Ads: keyword / targeting report" or "Ads: search term report" to see winners and wasted spend.</div>
@endforelse

<h5 class="fw-bold mt-4 mb-2">Products</h5>
<div class="row g-3">
  @foreach([['increase','Advertise more','success','Healthy margin before ads, sells less than most, ads (if any) under target ACOS.'],['reduce','Advertise less','danger','Ads running while margin is thin or negative, ACOS above target, or refunds high.']] as [$k,$title,$c,$hint])
  <div class="col-xl-6"><div class="card h-100"><div class="card-header text-{{ $c }}">{{ $title }} <span class="badge text-bg-{{ $c }}">{{ count($i['products'][$k]) }}</span></div><div class="px-3 pt-2 small text-muted">{{ $hint }}</div>
    <div class="table-responsive"><table class="table table-sm align-middle mb-0"><tbody>
    @forelse($i['products'][$k] as $p)
      <tr><td><a class="fw-semibold text-decoration-none" href="{{ route('products.show',$p['product_id']) }}">{{ $p['sku'] }}</a><div class="small text-muted">{{ \Illuminate\Support\Str::limit($p['name'],38) }}</div></td>
        <td class="small" style="max-width:330px">{{ $p['why'] }}</td>
        <td class="text-end small text-nowrap">margin {{ $p['margin'] }}%<br>ads ₹{{ number_format($p['spend'],0) }}{{ $p['acos']!==null?' · ACOS '.$p['acos'].'%':'' }}</td></tr>
    @empty<tr><td class="text-muted small py-3">Nothing flagged. Product advice needs orders, and product-level ad reports for ACOS.</td></tr>@endforelse</tbody></table></div></div></div>
  @endforeach
</div>
<div class="small text-muted mt-3">Rules use your Settings: target ACOS {{ $i['target'] }}%, target margin, return threshold and wasted-spend limit. They are guides, not orders.</div>
@endsection
