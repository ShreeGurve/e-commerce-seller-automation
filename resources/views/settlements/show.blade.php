@extends('layouts.app')
@section('title','Settlement '.$settlement->external_settlement_id)
@section('subtitle',$settlement->marketplace->name.' · '.$settlement->period_start?->format('d M').' – '.$settlement->period_end?->format('d M Y'))
@section('content')
<div class="row g-3 mb-3">
  <div class="col-lg-4"><div class="card h-100"><div class="card-body"><div class="small text-muted">Net payout</div><div class="sg-bignum {{ (float)$settlement->total_amount<0?'text-danger':'text-success' }}">₹{{ number_format($settlement->total_amount,2) }}</div><div class="small text-muted mt-1">{{ $settlement->item_count }} lines</div></div></div></div>
  <div class="col-lg-8"><div class="card h-100"><div class="card-header">By type</div><div class="table-responsive"><table class="table table-sm mb-0">
    @foreach($byKind as $k)<tr><td><a class="text-decoration-none" href="{{ route('settlements.show',[$settlement,'kind'=>$k->kind]) }}">{{ \App\Models\SettlementItem::KINDS[$k->kind] ?? $k->kind }}</a></td><td class="text-muted">{{ $k->n }} line(s)</td>
      <td class="text-end fw-semibold {{ (float)$k->amt<0?'text-danger':'' }}">₹{{ number_format($k->amt,2) }}</td></tr>@endforeach</table></div></div></div>
</div>
<div class="d-flex flex-wrap gap-2 mb-2">
  <a href="{{ route('settlements.show',$settlement) }}" class="btn btn-sm btn-{{ request('kind')||request('unmatched')?'outline-secondary':'primary' }}">All lines</a>
  <a href="{{ route('settlements.show',[$settlement,'unmatched'=>1]) }}" class="btn btn-sm btn-{{ request('unmatched')?'primary':'outline-secondary' }}">No order in system</a></div>
<div class="card"><div class="table-responsive"><table class="table table-sm align-middle mb-0">
  <thead><tr><th>Date</th><th>Order</th><th>SKU</th><th>Type</th><th>Description</th><th class="text-end">Amount</th></tr></thead><tbody>
  @forelse($items as $i)
    <tr><td class="text-nowrap">{{ $i->posted_date?->format('d M Y') }}</td>
      <td>@if($i->order)<a class="text-decoration-none" href="{{ route('orders.show',$i->order) }}">{{ $i->order->external_order_id }}</a>@elseif($i->external_order_id)<span class="text-danger">{{ $i->external_order_id }} <small>(not found)</small></span>@else — @endif</td>
      <td>{{ $i->sku ?? '—' }}</td><td><span class="badge text-bg-light border">{{ \App\Models\SettlementItem::KINDS[$i->kind] ?? $i->kind }}</span></td><td class="text-muted small">{{ $i->description }}</td>
      <td class="text-end {{ (float)$i->amount<0?'text-danger':'' }}">₹{{ number_format($i->amount,2) }}</td></tr>
  @empty<tr><td colspan="6" class="text-center text-muted py-4">No lines.</td></tr>@endforelse
  </tbody></table></div>@if($items->hasPages())<div class="card-footer">{{ $items->links() }}</div>@endif</div>
@endsection
