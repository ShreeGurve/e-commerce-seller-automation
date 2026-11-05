@extends('layouts.app')
@section('title', 'Supplier Invoice Matching')
@section('subtitle', 'Compare supplier invoices against purchase orders')
@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
    <div><h3 class="mb-1">Supplier Invoice Matching</h3><p class="text-muted mb-0">Three-way financial check: purchase order value against supplier invoice totals.</p></div>
    <a href="{{ route('purchase-invoice-matching.create') }}" class="btn btn-primary">Record supplier invoice</a>
</div>
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
<div class="row g-3 mb-4">
    @foreach([['Invoices',$summary['total']],['Matched',$summary['matched']],['Needs review',$summary['review']],['Variance under review','₹'.number_format($summary['variance'],2)]] as $kpi)
    <div class="col-md-3"><div class="card h-100"><div class="card-body"><div class="text-muted small">{{ $kpi[0] }}</div><div class="fs-4 fw-semibold">{{ $kpi[1] }}</div></div></div></div>
    @endforeach
</div>
<div class="card"><div class="card-body">
<form class="row g-2 mb-3" method="GET"><div class="col-md-5"><input class="form-control" name="q" value="{{ request('q') }}" placeholder="Invoice number or PO number"></div><div class="col-md-3"><select class="form-select" name="status"><option value="">All statuses</option>@foreach(['MATCHED','REVIEW_REQUIRED','REVIEWED','REJECTED'] as $s)<option value="{{ $s }}" @selected(request('status')===$s)>{{ str_replace('_',' ', $s) }}</option>@endforeach</select></div><div class="col-auto"><button class="btn btn-outline-primary">Filter</button></div></form>
<div class="table-responsive"><table class="table align-middle"><thead><tr><th>Supplier invoice</th><th>Purchase order</th><th>Supplier</th><th>PO total</th><th>Invoice total</th><th>Variance</th><th>Status / review</th></tr></thead><tbody>
@forelse($matches as $m)<tr><td><strong>{{ $m->supplier_invoice_number }}</strong><div class="small text-muted">{{ optional($m->supplier_invoice_date)->format('d M Y') }}</div></td><td>{{ $m->purchase->po_number }}</td><td>{{ $m->purchase->supplier->name ?? '—' }}</td><td>₹{{ number_format((float)$m->purchase->total_amount,2) }}</td><td>₹{{ number_format((float)$m->invoice_total,2) }}</td><td class="{{ (float)$m->variance_amount > 0 ? 'text-danger' : ((float)$m->variance_amount < 0 ? 'text-warning' : 'text-success') }}">₹{{ number_format((float)$m->variance_amount,2) }}</td><td><span class="badge bg-{{ $m->match_status==='MATCHED'?'success':($m->match_status==='REVIEW_REQUIRED'?'warning':($m->match_status==='REJECTED'?'danger':'info')) }}">{{ str_replace('_',' ',$m->match_status) }}</span>
@if($m->match_status==='REVIEW_REQUIRED')<form method="POST" action="{{ route('purchase-invoice-matching.review',$m) }}" class="mt-2">@csrf @method('PATCH')<input type="text" name="review_notes" class="form-control form-control-sm mb-1" placeholder="Reason / approval note" required><div class="d-flex gap-1"><button name="decision" value="ACCEPT_VARIANCE" class="btn btn-sm btn-outline-success">Accept variance</button><button name="decision" value="REJECT" class="btn btn-sm btn-outline-danger">Reject</button></div></form>@elseif($m->review_notes)<div class="small text-muted">{{ $m->review_notes }}</div>@endif
</td></tr>@empty<tr><td colspan="7" class="text-center text-muted py-4">No supplier invoices recorded yet.</td></tr>@endforelse
</tbody></table></div>{{ $matches->links() }}
</div></div>
@endsection
