@extends('layouts.app')
@section('title','Dashboard')
@section('subtitle',"Here's what's going on at your business right now")
@section('content')
@php $other = max($productCount - $activeCount, 0); $pct = $productCount ? round($activeCount/$productCount*100) : 0; @endphp

<div class="row g-3 g-lg-4 mb-4 pb-3 border-bottom" style="border-color:var(--sg-border)!important">
  @foreach([[$activeCount,'active products','Ready to sell','t-green','bi-check2-circle'],
            [$other,'other products','Inactive / draft / discontinued','t-orange','bi-pause-circle'],
            [$categoryCount,'categories','Catalog groups','t-blue','bi-tags'],
            [$marketplaceCount,'marketplaces','Amazon, Flipkart ...','t-red','bi-shop']] as [$n,$l,$s,$c,$i])
  <div class="col-6 col-xl-3 d-flex align-items-center gap-3">
    <div class="sg-tile {{ $c }}"><i class="bi {{ $i }}"></i></div>
    <div><div class="fw-bold fs-5 lh-1">{{ $n }} <span class="fs-6">{{ $l }}</span></div><div class="small text-muted mt-1">{{ $s }}</div></div>
  </div>
  @endforeach
</div>

<div class="row g-3 mb-4">
 @foreach([['Sales this month','₹'.number_format($metrics['sales'],2),'bi-currency-rupee','t-green'],['Orders this month',number_format($metrics['orders']),'bi-receipt','t-blue'],['Estimated profit','₹'.number_format($metrics['profit'],2),'bi-graph-up-arrow','t-green'],['Ad spend','₹'.number_format($metrics['ads'],2),'bi-megaphone','t-orange'],['Low stock',number_format($metrics['low_stock']),'bi-boxes','t-red']] as [$label,$value,$icon,$tile])
 <div class="col-6 col-xl"><div class="card h-100"><div class="card-body d-flex align-items-center gap-3"><div class="sg-tile {{$tile}}"><i class="bi {{$icon}}"></i></div><div><div class="small text-muted">{{$label}}</div><div class="fw-bold fs-5">{{$value}}</div></div></div></div></div>
 @endforeach
</div>

<div class="row g-3 g-lg-4">
  <div class="col-xl-7">
    <div class="card h-100"><div class="card-body">
      <div class="d-flex flex-wrap justify-content-between gap-2 mb-3">
        <div><h4 class="fw-bold mb-1">Net profit</h4><div class="text-muted">After fees, ads, COGS and returns, across all channels</div></div>
        <select class="form-select form-select-sm" style="width:170px" disabled><option>This month</option></select>
      </div>
      <div class="position-relative" style="height:260px">
        <svg viewBox="0 0 600 220" preserveAspectRatio="none" width="100%" height="100%" style="opacity:.45">
          <g stroke="currentColor" class="text-muted" stroke-opacity=".25">@for($x=0;$x<=600;$x+=60)<line x1="{{ $x }}" y1="0" x2="{{ $x }}" y2="220"/>@endfor</g>
          <polyline fill="none" stroke="#3874ff" stroke-width="2.5" points="0,170 60,120 120,120 180,150 240,150 300,70 360,20 420,110 480,110 540,160 600,130"/>
          <polyline fill="none" stroke="#9aa5be" stroke-width="2" stroke-dasharray="5 4" points="0,140 60,175 180,175 240,110 300,90 360,60 420,120 480,80 540,100 600,130"/>
        </svg>
        <div class="position-absolute top-50 start-50 translate-middle text-center w-75">
          <span class="badge text-bg-light border mb-2">Preview</span>
          <div class="fw-semibold">Live chart appears after Orders + Settlements</div>
          <div class="small text-muted">Planned for Phases 2–4</div>
        </div>
      </div>
    </div></div>
  </div>

  <div class="col-xl-5">
    <div class="row g-3 g-lg-4">
      <div class="col-md-6 col-xl-12"><div class="card h-100"><div class="card-body">
        <h6 class="fw-bold mb-0">Catalog health</h6><div class="small text-muted mb-3">Active share of all products</div>
        <div class="d-flex align-items-end justify-content-between mb-2"><span class="sg-bignum">{{ $pct }}%</span><span class="small text-muted">{{ $activeCount }} of {{ $productCount }}</span></div>
        <div class="progress" style="height:8px"><div class="progress-bar" style="width:{{ $pct }}%"></div></div>
      </div></div></div>
      <div class="col-md-6 col-xl-12"><div class="card h-100"><div class="card-body">
        <h6 class="fw-bold mb-3">Setup checklist</h6>
        @foreach([['Add categories',$categoryCount>0,'categories.index','categories.manage'],['Add your first product',$productCount>0,'products.create','products.create'],['Marketplaces ready',$marketplaceCount>0,null,null]] as [$l,$ok,$r,$perm])
          <div class="d-flex align-items-center gap-2 mb-2">
            <i class="bi {{ $ok?'bi-check-circle-fill text-success':'bi-circle text-muted' }}"></i>
            <span class="{{ $ok?'text-muted text-decoration-line-through':'' }}">{{ $l }}</span>
            @if(!$ok && $r)@can($perm)<a href="{{ route($r) }}" class="ms-auto small fw-bold text-decoration-none">Do it</a>@endcan @endif
          </div>
        @endforeach
      </div></div></div>
    </div>
  </div>

  @if($claimAlerts->count())
  <div class="col-12"><div class="card border-danger"><div class="card-header text-danger"><i class="bi bi-exclamation-triangle"></i> Claims that need action</div>
    <ul class="list-group list-group-flush small">@foreach($claimAlerts as $c) @php $st=$c->deadlineState(); @endphp
      <li class="list-group-item d-flex flex-wrap justify-content-between gap-2"><a class="text-decoration-none fw-semibold" href="{{ route('claims.show',$c) }}">{{ $c->claim_number }}</a>
        <span class="text-muted">{{ \App\Models\Claim::TYPES[$c->claim_type] }} · {{ \App\Models\Claim::LABELS[$c->status] }} · ₹{{ number_format($c->claim_amount,2) }}</span>
        @include('claims._deadline',['claim'=>$c])</li>@endforeach</ul></div></div>
  @endif
  <div class="col-lg-7">
    <div class="card h-100"><div class="card-header">Recent activity</div>
      <ul class="list-group list-group-flush">
        @forelse($activity as $a)
          <li class="list-group-item d-flex justify-content-between gap-2 small">
            <span><b>{{ $a->action }}</b> <span class="text-muted">by {{ $a->user->name ?? 'system' }}</span></span>
            <span class="text-muted text-nowrap">{{ $a->created_at->diffForHumans() }}</span>
          </li>
        @empty<li class="list-group-item text-muted">No activity yet.</li>@endforelse
      </ul></div>
  </div>
  <div class="col-12"><div class="row g-3">
    @can('reports.view')<div class="col-md-4"><a class="card card-body h-100 text-decoration-none" href="{{route('reports.index')}}"><b><i class="bi bi-file-earmark-bar-graph me-2"></i>Business reports</b><span class="small text-muted">Filter and export operational and financial reports.</span></a></div>@endcan
    @can('automation.view')<div class="col-md-4"><a class="card card-body h-100 text-decoration-none" href="{{route('automation.index')}}"><b><i class="bi bi-gear-wide-connected me-2"></i>Automation rules</b><span class="small text-muted">Configure and run internal alerts.</span></a></div>@endcan
    @can('reports.view')<div class="col-md-4"><a class="card card-body h-100 text-decoration-none" href="{{route('notifications.index')}}"><b><i class="bi bi-bell me-2"></i>Notifications ({{$metrics['unread']}})</b><span class="small text-muted">Review unread alerts and follow related links.</span></a></div>@endcan
  </div></div>
  <div class="col-lg-5">
    <div class="card h-100"><div class="card-header">Roadmap</div>
      <ul class="list-group list-group-flush small">
        @foreach([['Phases 1–7','Catalog, imports, orders, finance, ads, claims, listings','success','Completed'],['Phase 8','Dashboard, reports, notifications, automation','primary','Current']] as [$p,$d,$c,$b])
          <li class="list-group-item d-flex align-items-center gap-2"><b style="width:64px">{{ $p }}</b><span class="text-muted">{{ $d }}</span><span class="badge text-bg-{{ $c }} ms-auto">{{ $b }}</span></li>
        @endforeach
      </ul></div>
  </div>
</div>
@endsection
