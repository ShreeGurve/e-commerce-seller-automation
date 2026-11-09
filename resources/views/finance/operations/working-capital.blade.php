@extends('layouts.app')
@section('title', 'Working Capital Intelligence')
@section('subtitle', 'Inventory investment and supplier payable exposure using recorded operational data')
@section('content')
<div class="alert alert-info small">Indicative working-capital view. Inventory is estimated at current product unit cost using available plus reserved units. This is not a statutory balance sheet; cash, tax balances and unrecorded receivables are not inferred.</div>
<form method="GET" class="card mb-3"><div class="card-body row g-2 align-items-end"><div class="col-md-3"><label class="form-label">Performance period</label><select name="period_days" class="form-select">@foreach([30,60,90] as $d)<option value="{{ $d }}" @selected($period===$d)>{{ $d }} days</option>@endforeach</select></div><div class="col-md-2"><button class="btn btn-primary">Apply</button></div></div></form>
<div class="row g-3 mb-3">
@foreach([['Estimated inventory investment',$inventoryValue],['Open supplier payables',$purchasePayables],['Working-capital proxy',$workingCapitalProxy],['Recorded sales · '.$period.' days',$recentSales],['Recorded order profit · '.$period.' days',$recentProfit],['Recorded expenses · '.$period.' days',$recentExpenses]] as [$label,$value])<div class="col-md-6 col-xl-4"><div class="card h-100"><div class="card-body"><div class="small text-muted">{{ $label }}</div><div class="fs-4 fw-bold">₹{{ number_format($value,2) }}</div></div></div></div>@endforeach
</div>
<div class="card"><div class="card-header fw-semibold">Inventory and liability details</div><div class="card-body"><div class="row"><div class="col-md-6"><div class="small text-muted">Inventory units included</div><div class="fs-5 fw-semibold">{{ number_format($inventoryUnits) }}</div></div><div class="col-md-6"><div class="small text-muted">Inventory valuation method</div><div class="fw-semibold">Current product unit cost × available and reserved quantity</div></div></div></div></div>
@endsection
