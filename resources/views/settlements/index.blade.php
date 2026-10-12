@extends('layouts.app')
@section('title','Settlements')
@section('subtitle','What the marketplaces actually paid you')
@section('content')
@if($unmatched)<div class="alert alert-warning">{{ $unmatched }} payout line(s) belong to orders that are not in the system yet. Import those orders, then run reconciliation to link them.
  <a class="alert-link" href="{{ route('reconciliation.index') }}">Open reconciliation</a></div>@endif
<div class="d-flex flex-wrap gap-2 mb-3">
  @can('finance.manage')<a href="{{ route('imports.create',['type'=>'SETTLEMENTS']) }}" class="btn btn-primary"><i class="bi bi-cloud-upload"></i> Import settlement file</a>@endcan
  <form method="GET" class="ms-auto"><select name="marketplace_id" class="form-select" onchange="this.form.submit()"><option value="">All channels</option>@foreach($marketplaces as $m)<option value="{{ $m->id }}" @selected(request('marketplace_id')==$m->id)>{{ $m->name }}</option>@endforeach</select></form>
</div>
<div class="card"><div class="table-responsive"><table class="table table-hover align-middle mb-0">
  <thead><tr><th>Settlement</th><th>Channel</th><th>Period</th><th class="text-end">Lines</th><th class="text-end">Net payout</th></tr></thead><tbody>
  @forelse($settlements as $s)
    <tr><td><a class="fw-semibold text-decoration-none" href="{{ route('settlements.show',$s) }}">{{ $s->external_settlement_id }}</a></td><td>{{ $s->marketplace->name }}</td>
      <td class="text-nowrap">{{ $s->period_start?->format('d M') }} – {{ $s->period_end?->format('d M Y') }}</td><td class="text-end">{{ $s->item_count }}</td>
      <td class="text-end fw-bold {{ (float)$s->total_amount<0?'text-danger':'' }}">₹{{ number_format($s->total_amount,2) }}</td></tr>
  @empty<tr><td colspan="5" class="text-center text-muted py-5">No settlements yet. Download the payment/settlement report from Seller Central and import it.</td></tr>@endforelse
  </tbody></table></div>
  @if($settlements->hasPages())<div class="card-footer">{{ $settlements->links() }}</div>@endif
</div>
@endsection
