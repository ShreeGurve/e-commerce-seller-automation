@extends('layouts.app')
@section('title','KPI Alert Monitor')
@section('subtitle','Compare monthly targets with actuals and evaluate internal alerts')
@section('content')
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
<div class="card mb-3"><div class="card-body d-flex flex-wrap justify-content-between align-items-end gap-3">
<form method="GET" class="d-flex gap-2 align-items-end"><div><label class="form-label">Month</label><input class="form-control" type="month" name="month" value="{{ $month }}"></div><button class="btn btn-outline-primary">View</button></form>
<form method="POST" action="{{ route('business-alerts.kpi-evaluate') }}" class="d-flex gap-2 align-items-end">@csrf<input type="hidden" name="month" value="{{ $month }}"><button class="btn btn-primary">Evaluate KPI alerts</button></form>
</div></div>
<div class="card"><div class="card-header"><strong>Target attainment</strong><span class="small text-muted ms-2">Alert threshold: below 90% of target</span></div><div class="card-body table-responsive"><table class="table align-middle"><thead><tr><th>Metric</th><th>Actual</th><th>Target</th><th>Attainment</th><th>Status</th></tr></thead><tbody>
@foreach($rows as $row)<tr><td>{{ $row->label }}</td><td>{{ $row->unit }}{{ number_format($row->actual,2) }}</td><td>{{ $row->target>0?$row->unit.number_format($row->target,2):'Not set' }}</td><td>@if($row->attainment!==null){{ $row->attainment }}%<div class="progress mt-1" style="height:6px"><div class="progress-bar" style="width:{{ min(100,max(0,$row->attainment)) }}%"></div></div>@else—@endif</td><td>@if($row->attainment===null)<span class="badge bg-secondary">No target</span>@elseif($row->attainment<90)<span class="badge bg-warning text-dark">Below target</span>@else<span class="badge bg-success">On track</span>@endif</td></tr>@endforeach
</tbody></table></div></div>
<div class="card mt-3"><div class="card-header"><strong>KPI alerts for {{ $month }}</strong></div><div class="card-body">@forelse($events as $event)<div class="border-bottom py-2"><strong>{{ $event->title }}</strong><span class="badge bg-{{ $event->severity==='HIGH'?'danger':'warning' }} ms-2">{{ $event->severity }}</span><div class="small text-muted">{{ $event->message }}</div></div>@empty<p class="text-muted mb-0">No KPI alerts have been created for this month. Run evaluation to check targets.</p>@endforelse</div></div>
<div class="small text-muted mt-3">Order metrics exclude TEST-source orders. KPI alerts are internal records only; no email, SMS, marketplace update or financial transaction is triggered.</div>
@endsection
