@extends('layouts.app')
@section('title','Advertising')
@section('subtitle','Is ad money earning more than it costs?')
@section('content')
@include('ads._nav')
@include('ads._filters')
@php $t=$totals; $n=fn($v,$d=0)=>number_format((float)$v,$d); @endphp
@if(!$t['spend'] && !$t['impressions'])
  <div class="card"><div class="card-body text-center py-5"><i class="bi bi-megaphone fs-1 text-primary"></i><h5 class="mt-2">No ad data in this period</h5>
    <p class="text-muted mb-3">Download an advertising report (campaign, keyword, search term or advertised product) from Amazon / Flipkart and import it. Product-level reports let the system put ad cost on each product's profit.</p>
    @can('advertising.manage')<a href="{{ route('imports.create',['type'=>'ADS_PRODUCT']) }}" class="btn btn-primary">Import ad report</a>@endcan</div></div>
@else
<div class="row g-3 mb-3">
  @foreach([['Ad spend','₹'.$n($t['spend'],2),null],['Sales from ads','₹'.$n($t['sales'],2),null],
    ['ACOS',$t['acos']===null?'—':$t['acos'].'%',$t['acos']!==null?($t['acos']<=$targetAcos?'good':'bad'):null],['ROAS',$t['roas']===null?'—':$t['roas'].'x',null],
    ['TACOS (ad spend ÷ all sales)',$tacos===null?'—':$tacos.'%',null],['Ad orders',$n($t['orders']),null]] as [$l,$v,$st])
  <div class="col-6 col-xl-2"><div class="card h-100"><div class="card-body"><div class="small text-muted">{{ $l }}</div><div class="fw-bold fs-4 {{ $st==='good'?'text-success':($st==='bad'?'text-danger':'') }}">{{ $v }}</div></div></div></div>
  @endforeach
</div>
<div class="row g-3 mb-3">
  @foreach([['Impressions',$n($t['impressions'])],['Clicks',$n($t['clicks'])],['CTR',$t['ctr']===null?'—':$t['ctr'].'%'],['CPC',$t['cpc']===null?'—':'₹'.$t['cpc']],['Conversion rate',$t['cvr']===null?'—':$t['cvr'].'%']] as [$l,$v])
  <div class="col-6 col-md"><div class="card"><div class="card-body py-2"><div class="small text-muted">{{ $l }}</div><div class="fw-semibold">{{ $v }}</div></div></div></div>@endforeach
</div>
<div class="card mb-3"><div class="card-header">Spend vs sales per day</div><div class="card-body"><div style="height:260px"><canvas id="adChart"></canvas></div></div></div>
<div class="card"><div class="card-header">Campaigns <span class="text-muted fw-normal small">· target ACOS {{ $targetAcos }}% (Settings)</span></div><div class="table-responsive"><table class="table table-hover align-middle mb-0">
  <thead><tr><th>Campaign</th><th class="text-end">Spend</th><th class="text-end">Sales</th><th class="text-end">ACOS</th><th class="text-end">ROAS</th><th class="text-end">Orders</th><th class="text-end">Clicks</th><th class="text-end">CPC</th><th></th></tr></thead><tbody>
  @foreach($campaignRows as $c) @php $m=$ads->metrics($c); @endphp
    <tr><td class="fw-semibold">{{ $c->campaign }}</td><td class="text-end">₹{{ $n($m['spend'],2) }}</td><td class="text-end">₹{{ $n($m['sales'],2) }}</td>
      <td class="text-end {{ $m['acos']!==null && $m['acos']>$targetAcos ? 'text-danger fw-bold':'' }}">{{ $m['acos']===null?'—':$m['acos'].'%' }}</td><td class="text-end">{{ $m['roas']===null?'—':$m['roas'].'x' }}</td>
      <td class="text-end">{{ $m['orders'] }}</td><td class="text-end">{{ $n($m['clicks']) }}</td><td class="text-end">{{ $m['cpc']===null?'—':'₹'.$m['cpc'] }}</td>
      <td>@if($m['spend']>0 && $m['orders']==0)<span class="badge text-bg-danger">No sales</span>@elseif($m['acos']!==null && $m['acos']>$targetAcos)<span class="badge text-bg-warning">High ACOS</span>@endif</td></tr>@endforeach
  </tbody></table></div>
  <div class="card-footer small text-muted">Totals use one report type per channel (campaign, else product, else keyword, else search term) so nothing is counted twice. Sales are as reported by the marketplace (attribution window applies).</div></div>
@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script>
@php
    $chartData = $daily->map(function ($r) {
        return [
            'd' => \Illuminate\Support\Str::substr((string) $r->date, 0, 10),
            's' => (float) $r->spend,
            'a' => (float) $r->sales,
        ];
    })->values();
@endphp
const D = {{ \Illuminate\Support\Js::from($chartData) }};
const dark=document.documentElement.getAttribute('data-bs-theme')==='dark', tick=dark?'#8f9bb8':'#6e7891';
new Chart(document.getElementById('adChart'),{type:'line',data:{labels:D.map(x=>x.d),datasets:[
 {label:'Ad spend',data:D.map(x=>x.s),borderColor:'#ed2b2a',backgroundColor:'rgba(237,43,42,.1)',tension:.25,fill:true},
 {label:'Sales from ads',data:D.map(x=>x.a),borderColor:'#3874ff',backgroundColor:'rgba(56,116,255,.1)',tension:.25,fill:true}]},
 options:{maintainAspectRatio:false,interaction:{mode:'index',intersect:false},plugins:{legend:{labels:{color:tick}}},scales:{x:{ticks:{color:tick,maxTicksLimit:10}},y:{ticks:{color:tick},beginAtZero:true}}}});
</script>
@endpush
@endif
@endsection
