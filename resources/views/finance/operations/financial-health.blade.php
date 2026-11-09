@extends('layouts.app')
@section('title', 'Business Financial Health')
@section('subtitle', 'A compact financial pulse based on recorded orders, profits, expenses and supplier commitments')
@section('content')
<div class="alert alert-info small">Management indicators, not audited financial statements. Order profit uses recorded actual profit when available and estimated profit otherwise. TEST orders are excluded from sales and order-profit figures.</div>
<form method="GET" class="card mb-3"><div class="card-body row g-2 align-items-end"><div class="col-md-3"><label class="form-label">Review period</label><select name="period_days" class="form-select">@foreach([30,60,90] as $d)<option value="{{ $d }}" @selected($period===$d)>{{ $d }} days</option>@endforeach</select></div><div class="col-md-2"><button class="btn btn-primary">Refresh</button></div></div></form>
<div class="row g-3 mb-3">
@foreach([['Non-test order sales',$sales],['Recorded order profit',$profit],['Recorded expenses',$expenses],['Open supplier payables',$payables],['Settlement batches',$settlementCount],['Settlement net amount',$settlementValue]] as [$label,$value])<div class="col-md-6 col-xl-4"><div class="card h-100"><div class="card-body"><div class="small text-muted">{{ $label }}</div><div class="fs-4 fw-bold">{{ is_numeric($value) && in_array($label,['Settlement batches']) ? number_format($value) : '₹'.number_format($value,2) }}</div></div></div></div>@endforeach
</div>
<div class="card mb-3"><div class="card-header fw-semibold">Performance ratios</div><div class="card-body row"><div class="col-md-6"><div class="small text-muted">Order profit / sales</div><div class="fs-4 fw-bold">{{ number_format($profitMargin,2) }}%</div></div><div class="col-md-6"><div class="small text-muted">Recorded expenses / sales</div><div class="fs-4 fw-bold">{{ number_format($expenseRatio,2) }}%</div></div></div></div>
<div class="card"><div class="card-header fw-semibold">Review signals</div><div class="list-group list-group-flush">@foreach($signals as [$type,$message])<div class="list-group-item"><span class="badge text-bg-{{ $type }} me-2">{{ strtoupper($type) }}</span>{{ $message }}</div>@endforeach</div></div>
@endsection
