@extends('layouts.app')
@section('title', 'Cash Flow Forecast')
@section('subtitle', 'Scenario-based operating cash estimate from internal sales, expenses and supplier commitments')
@section('content')
<div class="alert alert-info small">Planning estimate only—not a bank balance, confirmed receivable, or cash commitment. Collections and expense buffer are assumptions. TEST orders are excluded. No payments or purchase orders are created.</div>
<form method="GET" class="card mb-3"><div class="card-body"><div class="row g-2 align-items-end">
<div class="col-md-3"><label class="form-label">Historical lookback</label><select name="lookback_days" class="form-select">@foreach([30,60,90] as $d)<option value="{{ $d }}" @selected($lookback===$d)>{{ $d }} days</option>@endforeach</select></div>
<div class="col-md-3"><label class="form-label">Forecast horizon</label><select name="horizon_days" class="form-select">@foreach([30,60,90] as $d)<option value="{{ $d }}" @selected($horizon===$d)>{{ $d }} days</option>@endforeach</select></div>
<div class="col-md-2"><label class="form-label">Collection rate (%)</label><input class="form-control" type="number" name="collection_rate" min="0" max="100" step="1" value="{{ $collectionRate }}"></div>
<div class="col-md-2"><label class="form-label">Expense buffer (%)</label><input class="form-control" type="number" name="expense_buffer" min="0" max="100" step="1" value="{{ $expenseBuffer }}"></div>
<div class="col-md-2"><button class="btn btn-primary w-100">Update forecast</button></div></div></div></form>
<div class="row g-3 mb-3">
@foreach([['Projected sales revenue',$projectedRevenue],['Estimated collections',$expectedCollections],['Projected operating expenses',$projectedExpenses],['Supplier payable commitments due',$duePayables]] as [$label,$value])<div class="col-md-6 col-xl-3"><div class="card h-100"><div class="card-body"><div class="small text-muted">{{ $label }}</div><div class="fs-4 fw-bold">₹{{ number_format($value,2) }}</div></div></div></div>@endforeach
</div>
<div class="card mb-3"><div class="card-body"><div class="small text-muted">Estimated net operating position</div><div class="display-6 fw-bold {{ $netOperatingEstimate < 0 ? 'text-danger' : 'text-success' }}">₹{{ number_format($netOperatingEstimate,2) }}</div><div class="small text-muted">Collections less forecast expenses, historical supplier-payment run rate and due PO balances</div></div></div>
<div class="card"><div class="card-header fw-semibold">Forecast inputs</div><div class="table-responsive"><table class="table mb-0"><tbody>
<tr><th>Historical non-test order revenue</th><td class="text-end">₹{{ number_format((float)$sales->revenue,2) }}</td></tr><tr><th>Recorded expenses</th><td class="text-end">₹{{ number_format($expenseTotal,2) }}</td></tr><tr><th>Confirmed supplier payments</th><td class="text-end">₹{{ number_format($paidSupplier,2) }}</td></tr><tr><th>Total open supplier payable balance</th><td class="text-end">₹{{ number_format($openPayables,2) }}</td></tr><tr><th>Projected supplier payments at historical run rate</th><td class="text-end">₹{{ number_format($projectedSupplierPayments,2) }}</td></tr>
</tbody></table></div></div>
@endsection
