@extends('layouts.app')
@section('title','Smart Reorder Automation')
@section('subtitle','Convert the latest demand forecast into an internal procurement requisition for review')
@section('content')
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif
<div class="row g-3 mb-4">
 <div class="col-md-4"><div class="card h-100"><div class="card-body"><div class="text-muted small">Products with reorder suggestions</div><div class="fs-3 fw-bold">{{ $suggestions->count() }}</div></div></div></div>
 <div class="col-md-4"><div class="card h-100"><div class="card-body"><div class="text-muted small">Suggested units</div><div class="fs-3 fw-bold">{{ number_format($suggestedUnits,0) }}</div></div></div></div>
 <div class="col-md-4"><div class="card h-100"><div class="card-body"><div class="text-muted small">Indicative COGS value</div><div class="fs-3 fw-bold">₹{{ number_format($estimatedValue,2) }}</div></div></div></div>
</div>
<div class="card mb-4"><div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2"><div><h5 class="mb-0">Latest forecast recommendations</h5><small class="text-muted">Uses the latest saved forecast per active product; excludes products already present on open requisitions.</small></div>
<form method="POST" action="{{ route('reorders.smart.generate') }}">@csrf<button class="btn btn-primary" @disabled($suggestions->isEmpty())>Generate internal requisition</button></form></div>
<div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th>Product</th><th>SKU</th><th>Forecast date</th><th>Daily demand</th><th>Stock position</th><th>Lead time</th><th>Suggested qty</th><th>Est. unit cost</th></tr></thead><tbody>
@forelse($suggestions as $f)<tr><td>{{ $f->product->name }}</td><td>{{ $f->product->sku }}</td><td>{{ $f->forecast_date->format('d M Y') }}</td><td>{{ number_format((float)$f->average_daily_demand,2) }}</td><td>{{ $f->stock_position }}</td><td>{{ $f->lead_time_days }} days</td><td class="fw-semibold">{{ $f->suggested_reorder_qty }}</td><td>₹{{ number_format((float)$f->product->currentCost('COGS'),2) }}</td></tr>
@empty<tr><td colspan="8" class="text-center text-muted py-4">No saved forecast suggestions found. Generate a snapshot from Inventory Forecast first.</td></tr>@endforelse
</tbody></table></div></div>
<div class="card"><div class="card-header"><h5 class="mb-0">Recent smart reorder runs</h5></div><div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Run</th><th>Generated</th><th>Products considered</th><th>Lines added</th><th>Requisition</th><th>Note</th></tr></thead><tbody>
@forelse($runs as $run)<tr><td>{{ $run->run_number }}</td><td>{{ $run->created_at->format('d M Y H:i') }}</td><td>{{ $run->products_considered }}</td><td>{{ $run->items_added }}</td><td>{{ $run->requisition?->request_number ?? 'Deleted' }}</td><td>{{ $run->notes }}</td></tr>@empty<tr><td colspan="6" class="text-center text-muted py-3">No runs yet.</td></tr>@endforelse
</tbody></table></div></div>
<div class="alert alert-info mt-3 mb-0">Safety: this creates a submitted internal requisition only. It does not create a purchase order, contact suppliers, send emails, or place marketplace orders.</div>
@endsection
