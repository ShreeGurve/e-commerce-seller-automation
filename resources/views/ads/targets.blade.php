@extends('layouts.app')
@section('title',$level==='keywords'?'Keywords':'Search terms')
@section('subtitle',$level==='keywords'?'How each keyword / target performs':'What customers actually typed')
@section('content')
@include('ads._nav')
@include('ads._filters')
@php $sortUrl=fn($s)=>request()->fullUrlWithQuery(['sort'=>$s]); @endphp
<div class="card"><div class="card-header d-flex flex-wrap justify-content-between gap-2"><span>{{ $rows->total() }} {{ $level==='keywords'?'keywords':'search terms' }}</span>
  <span class="small">@foreach(['spend'=>'Spend','sales'=>'Sales','clicks'=>'Clicks','orders'=>'Orders','acos_desc'=>'Highest ACOS','wasted'=>'Most wasted'] as $k=>$l)<a href="{{ $sortUrl($k) }}" class="ms-2 text-decoration-none {{ $f['sort']===$k?'fw-bold':'text-muted' }}">{{ $l }}</a>@endforeach</span></div>
  <div class="table-responsive"><table class="table table-hover align-middle mb-0">
  <thead><tr><th>{{ $level==='keywords'?'Keyword':'Search term' }}</th><th>Campaign</th><th class="text-end">Spend</th><th class="text-end">Sales</th><th class="text-end">ACOS</th><th class="text-end">Orders</th><th class="text-end">Clicks</th><th class="text-end">CVR</th><th></th></tr></thead><tbody>
  @forelse($rows as $r) @php $m=$ads->metrics($r); @endphp
    <tr><td class="fw-semibold">{{ $r->label }}@if($r->match_type)<span class="badge text-bg-light border ms-1">{{ $r->match_type }}</span>@endif</td><td class="small text-muted">{{ \Illuminate\Support\Str::limit($r->campaign,30) }}</td>
      <td class="text-end">₹{{ number_format($m['spend'],2) }}</td><td class="text-end">₹{{ number_format($m['sales'],2) }}</td>
      <td class="text-end {{ $m['acos']!==null && $m['acos']>$targetAcos ? 'text-danger fw-bold':'' }}">{{ $m['acos']===null?'—':$m['acos'].'%' }}</td><td class="text-end">{{ $m['orders'] }}</td><td class="text-end">{{ $m['clicks'] }}</td><td class="text-end">{{ $m['cvr']===null?'—':$m['cvr'].'%' }}</td>
      <td>@if($m['orders']==0 && $m['sales']==0 && $m['spend']>=$wasteMin)<span class="badge text-bg-danger">Wasted</span>
        @elseif($m['sales']>0 && $m['orders']>=1 && $m['acos']<=$targetAcos)<span class="badge text-bg-success">Winner</span>
        @elseif($m['clicks']>=20 && $m['orders']>=1 && $m['cvr']<3)<span class="badge text-bg-warning">Low conversion</span>@endif</td></tr>
  @empty<tr><td colspan="9" class="text-center text-muted py-5">No {{ $level==='keywords'?'keyword':'search term' }} data. Import an "Ads: {{ $level==='keywords'?'keyword / targeting':'search term' }} report".</td></tr>@endforelse
  </tbody></table></div>@if($rows->hasPages())<div class="card-footer">{{ $rows->links() }}</div>@endif
  <div class="card-footer small text-muted">Winner = sold, ACOS at or under {{ $targetAcos }}%. Wasted = spent ₹{{ number_format($wasteMin,0) }}+ with no sale. Both limits are in Settings.</div></div>
@endsection
