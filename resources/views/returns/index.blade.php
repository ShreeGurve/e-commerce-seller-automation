@extends('layouts.app')
@section('title','Returns')
@section('subtitle','Inspect what came back and put it in the right stock bucket')
@section('content')
<div class="card mb-3"><div class="card-body"><form class="row g-2" method="GET">
  <div class="col-md-4"><input name="q" value="{{ request('q') }}" class="form-control" placeholder="Return no. or order id"></div>
  <div class="col-md-3"><select name="status" class="form-select"><option value="">Any status</option>@foreach(['REQUESTED','INSPECTED','CLOSED'] as $s)<option value="{{ $s }}" @selected(request('status')===$s)>{{ ucfirst(strtolower($s)) }}</option>@endforeach</select></div>
  <div class="col-md-3"><select name="return_type" class="form-select"><option value="">Any reason</option>@foreach(\App\Models\ProductReturn::TYPES as $k=>$l)<option value="{{ $k }}" @selected(request('return_type')===$k)>{{ $l }}</option>@endforeach</select></div>
  <div class="col-md-2"><button class="btn btn-outline-primary">Filter</button></div>
</form><div class="small text-muted mt-2">Create a return from an order's page (Orders &rarr; open order &rarr; Create return).</div></div></div>
<div class="card"><div class="table-responsive"><table class="table table-hover align-middle mb-0">
  <thead><tr><th>Return</th><th>Date</th><th>Order</th><th>Reason</th><th>Status</th><th>Claim</th></tr></thead><tbody>
  @forelse($returns as $r)
    <tr><td><a class="fw-semibold text-decoration-none" href="{{ route('returns.show',$r) }}">{{ $r->return_number }}</a></td><td>{{ $r->return_date->format('d M Y') }}</td>
      <td><a class="text-decoration-none" href="{{ route('orders.show',$r->order) }}">{{ $r->order->order_number }}</a> <span class="text-muted small">{{ $r->marketplace->name }}</span></td>
      <td>{{ \App\Models\ProductReturn::TYPES[$r->return_type] }}</td>
      <td><span class="badge text-bg-{{ ['REQUESTED'=>'warning','INSPECTED'=>'info','CLOSED'=>'dark'][$r->status] }}">{{ ucfirst(strtolower($r->status)) }}</span></td>
      <td>@if($r->claim_eligible)<span class="badge text-bg-danger">Eligible</span>@else<span class="text-muted">—</span>@endif</td></tr>
  @empty<tr><td colspan="6" class="text-center text-muted py-5">No returns yet.</td></tr>@endforelse
  </tbody></table></div>
  @if($returns->hasPages())<div class="card-footer">{{ $returns->links() }}</div>@endif
</div>
@endsection
