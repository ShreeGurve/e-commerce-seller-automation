@extends('layouts.app')
@section('content')
<div class="container-fluid py-4">
 <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
  <div><h1 class="h3 mb-1">Executive Intelligence</h1><p class="text-muted mb-0">Operational snapshot across supplier invoices, procurement and internal warehouse records.</p></div>
  <a class="btn btn-outline-primary" href="{{ route('executive.intelligence.export') }}">Export CSV snapshot</a>
 </div>
 <div class="row g-3 mb-4">
 @foreach([
 ['Supplier invoice records',$metrics['invoice_count'],number_format($metrics['invoice_value'],2).' total recorded'],
 ['Confirmed supplier payments',number_format($metrics['payments_confirmed'],2),$metrics['payments_count'].' payment records'],
 ['Open requisitions',$metrics['requisitions_open'],$metrics['quotes_received'].' supplier quotes received'],
 ['Warehouse SKUs',$metrics['warehouse_skus'],number_format($metrics['warehouse_units'],3).' total units (mixed units)'],
 ['Transfers in transit',$metrics['transfers_in_transit'],'Internal warehouse transfers'],
 ['Draft stock counts',$metrics['stock_count_drafts'],'Awaiting review/finalization']
 ] as $card)
 <div class="col-12 col-sm-6 col-xl-4"><div class="card h-100 shadow-sm"><div class="card-body"><div class="text-muted small">{{ $card[0] }}</div><div class="h3 my-2">{{ $card[1] }}</div><div class="small text-muted">{{ $card[2] }}</div></div></div></div>
 @endforeach
 </div>
 <div class="card shadow-sm mb-4"><div class="card-body">
  <div class="d-flex justify-content-between flex-wrap gap-2"><h2 class="h5">Automation rules</h2>
   <form method="POST" action="{{ route('executive.intelligence.evaluate') }}">@csrf<button class="btn btn-primary btn-sm">Evaluate rules now</button></form>
  </div>
  <p class="text-muted small">Rules create internal alerts only. Evaluation is manual; no emails, marketplace writes, payments or purchase orders are triggered.</p>
  @php($ruleMap=$rules->keyBy('rule_key'))
  @foreach([
   'low_stock'=>['Low warehouse stock','Alert when internal SKU quantity is at or below this value.'],
   'overdue_requisition'=>['Overdue procurement request','Alert for open requests past their required-by date.'],
   'invoice_review'=>['Invoice review required','Alert for invoice matches awaiting review.']
  ] as $key=>$info)
  <form method="POST" action="{{ route('executive.intelligence.rules.save') }}" class="row g-2 align-items-end border-top py-3">@csrf
   <input type="hidden" name="rule_key" value="{{ $key }}">
   <div class="col-md-5"><strong>{{ $info[0] }}</strong><div class="small text-muted">{{ $info[1] }}</div></div>
   <div class="col-md-3"><label class="form-label small">Threshold</label><input class="form-control form-control-sm" type="number" step="0.001" min="0" name="threshold_value" value="{{ $ruleMap[$key]->threshold_value ?? ($key==='low_stock' ? 5 : 0) }}"></div>
   <div class="col-md-2"><label class="form-label small">Enabled</label><select class="form-select form-select-sm" name="is_enabled"><option value="1" @selected(($ruleMap[$key]->is_enabled ?? true)==true)>Yes</option><option value="0" @selected(($ruleMap[$key]->is_enabled ?? true)==false)>No</option></select></div>
   <div class="col-md-2"><button class="btn btn-outline-primary btn-sm w-100">Save rule</button></div>
  </form>
  @endforeach
 </div></div>
 <div class="card shadow-sm"><div class="card-body"><h2 class="h5">Open operational alerts</h2>
 @if($events->isEmpty())<p class="text-muted mb-0">No open alerts. Run rule evaluation to check current records.</p>
 @else<div class="table-responsive"><table class="table table-sm align-middle"><thead><tr><th>Severity</th><th>Alert</th><th>Details</th><th>Created</th><th></th></tr></thead><tbody>
 @foreach($events as $event)<tr><td><span class="badge bg-{{ $event->severity==='HIGH'?'danger':'warning' }}">{{ $event->severity }}</span></td><td>{{ $event->title }}</td><td>{{ $event->message }}</td><td>{{ $event->created_at->format('d M Y H:i') }}</td><td><form method="POST" action="{{ route('executive.intelligence.events.resolve',$event) }}">@csrf @method('PATCH')<button class="btn btn-sm btn-outline-success">Resolve</button></form></td></tr>@endforeach
 </tbody></table></div>@endif
 </div></div>
 <p class="small text-muted mt-3">Scope note: figures are limited to modules installed in this package series. Warehouse quantities combine different units and are not a stock valuation. Invoice totals are recorded supplier invoice amounts, not sales or profit. TEST orders are not included because this snapshot does not query orders or sales.</p>
</div>
@endsection
