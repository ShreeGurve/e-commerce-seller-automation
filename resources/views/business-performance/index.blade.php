@extends('layouts.app')
@section('title','Business Performance')
@section('subtitle','Set monthly KPI targets and compare them with actual operating results')
@section('content')
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif
<div class="card mb-4"><div class="card-body d-flex flex-wrap justify-content-between align-items-end gap-3"><form method="GET" class="d-flex gap-2 align-items-end"><div><label class="form-label">Reporting month</label><input class="form-control" type="month" name="month" value="{{ $month }}"></div><button class="btn btn-outline-primary">View</button></form><span class="small text-muted">Sales excludes TEST-source orders. Sales value is order total, not net profit.</span></div></div>
<div class="row g-3 mb-4">
@foreach($rows as $row)<div class="col-12 col-md-6 col-xl-4"><div class="card h-100"><div class="card-body"><div class="text-muted small">{{ $row->label }}</div><div class="d-flex align-items-baseline gap-2 mt-2"><div class="fs-3 fw-bold">{{ $row->unit==='currency'?'₹':'' }}{{ number_format($row->actual,$row->unit==='currency'?2:0) }}</div><div class="small text-muted">/ {{ $row->target>0?($row->unit==='currency'?'₹':'').number_format($row->target,$row->unit==='currency'?2:0):'No target' }}</div></div>
@if($row->attainment!==null)<div class="progress mt-3" style="height:8px"><div class="progress-bar" role="progressbar" style="width:{{ min(100,max(0,$row->attainment)) }}%" aria-valuenow="{{ $row->attainment }}" aria-valuemin="0" aria-valuemax="100"></div></div><div class="small mt-2">{{ $row->attainment }}% of target</div>@else<div class="small text-muted mt-3">Set a target to measure attainment.</div>@endif
</div></div></div>@endforeach
</div>
<div class="card"><div class="card-header"><h5 class="mb-0">Set monthly KPI target</h5></div><div class="card-body"><form method="POST" action="{{ route('business-performance.targets.store') }}" class="row g-3">@csrf
<div class="col-md-3"><label class="form-label">Month</label><input class="form-control" type="month" name="target_month" value="{{ $month }}" required></div>
<div class="col-md-3"><label class="form-label">Metric</label><select class="form-select" name="metric_key" required>@foreach(['sales_value'=>'Order sales value','orders'=>'Non-test orders','fulfilled_orders'=>'Delivered / closed orders','approved_requisitions'=>'Approved / awarded requisitions','supplier_payments'=>'Confirmed supplier payments'] as $key=>$label)<option value="{{ $key }}">{{ $label }}</option>@endforeach</select></div>
<div class="col-md-3"><label class="form-label">Target value</label><input class="form-control" type="number" name="target_value" min="0" step="0.01" required></div>
<div class="col-md-3"><label class="form-label">Notes</label><input class="form-control" name="notes" maxlength="1000"></div>
<div class="col-12"><button class="btn btn-primary">Save target</button></div>
</form></div></div>
<div class="alert alert-info mt-3">Metrics are based on local records only. Sales uses non-TEST orders in CONFIRMED, PACKED, SHIPPED, DELIVERED or CLOSED status. Delivered/closed count is a subset of those orders. Supplier payment values are manually recorded confirmed invoice payments; no bank reconciliation is implied.</div>
@endsection
