@extends('layouts.app')
@section('title','Claims')
@section('subtitle','SAFE-T, reimbursements and disputes: money you are owed')
@section('content')
<div class="row g-3 mb-3">
  @foreach([['Open claims',$open->c.' · ₹'.number_format($open->amt,0),'t-blue','bi-folder2-open',null],['Urgent / overdue (≤'.$urgentDays.' days)',$urgent,'t-red','bi-alarm',route('claims.index',['deadline'=>'urgent'])],
    ['Warning (≤'.$warningDays.' days)',$warning,'t-orange','bi-exclamation-triangle',null],['Recovered','₹'.number_format($recovered,0).' (₹'.number_format($recoveredMonth,0).' this month)','t-green','bi-cash-coin',null],
    ['Win rate',$winRate===null?'—':$winRate.'%','t-blue','bi-trophy',null]] as [$l,$v,$c,$i,$href])
  <div class="col-6 col-xl"><div class="card h-100"><div class="card-body d-flex align-items-center gap-3"><div class="sg-tile {{ $c }}"><i class="bi {{ $i }}"></i></div>
    <div><div class="small text-muted">{{ $l }}</div>@if($href)<a href="{{ $href }}" class="fw-bold fs-5 text-decoration-none {{ $v>0?'text-danger':'' }}">{{ $v }}</a>@else<div class="fw-bold {{ strlen((string)$v)>10?'fs-6':'fs-5' }}">{{ $v }}</div>@endif</div></div></div></div>
  @endforeach
</div>
@if($candidates->count())
<div class="card border-warning mb-3"><div class="card-header text-warning"><i class="bi bi-lightbulb"></i> Returns marked "claim eligible" with no claim yet ({{ $candidates->count() }})</div><ul class="list-group list-group-flush small">
  @foreach($candidates as $r)<li class="list-group-item d-flex flex-wrap justify-content-between gap-2"><span><a class="text-decoration-none fw-semibold" href="{{ route('returns.show',$r) }}">{{ $r->return_number }}</a> · order {{ $r->order->external_order_id }} · {{ $r->marketplace->name }} · {{ \App\Models\ProductReturn::TYPES[$r->return_type] }}</span>
    @can('claims.create')<a href="{{ route('claims.create',['return_id'=>$r->id]) }}" class="btn btn-sm btn-outline-danger">Create claim</a>@endcan</li>@endforeach</ul></div>
@endif
<div class="d-flex flex-wrap gap-2 mb-3">
  <a href="{{ route('claims.index') }}" class="badge rounded-pill text-bg-{{ request('status')?'light border':'dark' }} fs-6 text-decoration-none">All</a>
  @foreach(\App\Models\Claim::STATUSES as $s)<a href="{{ route('claims.index',['status'=>$s]) }}" class="badge rounded-pill text-bg-{{ request('status')===$s ? \App\Models\Claim::BADGES[$s] : 'light border' }} fs-6 text-decoration-none">{{ \App\Models\Claim::LABELS[$s] }} {{ $counts[$s] ?? 0 }}</a>@endforeach
</div>
<div class="card mb-3"><div class="card-body"><form class="row g-2" method="GET"><input type="hidden" name="status" value="{{ request('status') }}">
  <div class="col-md-3"><input name="q" value="{{ request('q') }}" class="form-control" placeholder="Claim no., portal id or order"></div>
  <div class="col-md-3"><select name="claim_type" class="form-select"><option value="">All types</option>@foreach(\App\Models\Claim::TYPES as $k=>$l)<option value="{{ $k }}" @selected(request('claim_type')===$k)>{{ $l }}</option>@endforeach</select></div>
  <div class="col-md-2"><select name="marketplace_id" class="form-select"><option value="">All channels</option>@foreach($marketplaces as $m)<option value="{{ $m->id }}" @selected(request('marketplace_id')==$m->id)>{{ $m->name }}</option>@endforeach</select></div>
  <div class="col-md-4 d-flex gap-2"><button class="btn btn-outline-primary">Filter</button>@can('claims.create')<a href="{{ route('claims.create') }}" class="btn btn-primary ms-auto"><i class="bi bi-plus-lg"></i> New claim</a>@endcan</div>
</form></div></div>
<div class="card"><div class="table-responsive"><table class="table table-hover align-middle mb-0">
  <thead><tr><th>Claim</th><th>Type</th><th>Order</th><th class="text-end">Claimed</th><th class="text-end">Approved</th><th>Deadline</th><th>Status</th></tr></thead><tbody>
  @forelse($claims as $c)
    <tr><td><a class="fw-semibold text-decoration-none" href="{{ route('claims.show',$c) }}">{{ $c->claim_number }}</a><div class="small text-muted">{{ $c->marketplace->name }}{{ $c->external_claim_id?' · '.$c->external_claim_id:'' }}</div></td>
      <td>{{ \App\Models\Claim::TYPES[$c->claim_type] }}<div class="small text-muted">{{ $c->product->sku ?? '' }}</div></td>
      <td>@if($c->order)<a class="text-decoration-none" href="{{ route('orders.show',$c->order) }}">{{ $c->order->external_order_id }}</a>@else — @endif</td>
      <td class="text-end">₹{{ number_format($c->claim_amount,2) }}</td><td class="text-end {{ (float)$c->approved_amount>0?'text-success fw-bold':'' }}">{{ (float)$c->approved_amount>0?'₹'.number_format($c->approved_amount,2):'—' }}</td>
      <td class="text-nowrap">@include('claims._deadline',['claim'=>$c])</td>
      <td><span class="badge text-bg-{{ \App\Models\Claim::BADGES[$c->status] }}">{{ \App\Models\Claim::LABELS[$c->status] }}</span>@if($c->appeal_count)<span class="badge text-bg-light border ms-1">appeal ×{{ $c->appeal_count }}</span>@endif</td></tr>
  @empty<tr><td colspan="7" class="text-center text-muted py-5">No claims yet. Mark a return "claim eligible" at inspection, or start one from an order.</td></tr>@endforelse
  </tbody></table></div>@if($claims->hasPages())<div class="card-footer">{{ $claims->links() }}</div>@endif
  <div class="card-footer small text-muted">Deadline alerts apply to claims you still have to file (Draft) or appeal. Windows are in Settings. Scheduled e-mail reminders arrive with the notification system.</div></div>
@endsection
