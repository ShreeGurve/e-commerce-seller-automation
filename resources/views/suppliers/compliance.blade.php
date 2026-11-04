@extends('layouts.app')
@section('title', 'Supplier Compliance Alerts')
@section('subtitle', 'Monitor supplier document expiry and verification obligations')
@section('content')
<style>
.sc-hero{border:1px solid var(--bs-border-color);border-radius:16px;padding:22px;background:linear-gradient(120deg,rgba(13,110,253,.08),rgba(13,110,253,.015))}.sc-stat{height:100%;border:1px solid var(--bs-border-color);border-radius:14px;padding:16px;background:var(--bs-body-bg)}.sc-stat strong{font-size:1.7rem}.sc-muted{color:var(--bs-secondary-color)}@media(max-width:767px){.sc-table{min-width:760px}}
</style>
<div class="sc-hero mb-4 d-flex flex-wrap justify-content-between align-items-center gap-3">
 <div><div class="small text-primary fw-semibold text-uppercase mb-1">Phase 20 · Compliance monitoring</div><h4 class="mb-1">Never miss a supplier document renewal</h4><div class="sc-muted">Expiry overview and scheduled internal reminders for your supplier records.</div></div>
 <a href="{{ route('supplier-documents.index') }}" class="btn btn-outline-primary">Open document vault <i class="bi bi-arrow-up-right ms-1"></i></a>
</div>
<div class="row g-3 mb-4">
 <div class="col-6 col-xl-3"><div class="sc-stat"><div class="sc-muted small mb-2">Expired</div><strong class="text-danger">{{ number_format($counts['expired']) }}</strong><div class="small sc-muted">Renewal required</div></div></div>
 <div class="col-6 col-xl-3"><div class="sc-stat"><div class="sc-muted small mb-2">Due in 7 days</div><strong style="color:#b36b00">{{ number_format($counts['seven']) }}</strong><div class="small sc-muted">Urgent follow-up</div></div></div>
 <div class="col-6 col-xl-3"><div class="sc-stat"><div class="sc-muted small mb-2">Due in 30 days</div><strong class="text-primary">{{ number_format($counts['thirty']) }}</strong><div class="small sc-muted">Upcoming renewal</div></div></div>
 <div class="col-6 col-xl-3"><div class="sc-stat"><div class="sc-muted small mb-2">Due in 60 days</div><strong>{{ number_format($counts['sixty']) }}</strong><div class="small sc-muted">Plan ahead</div></div></div>
</div>
<div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-3">
 <div><h5 class="mb-1">Expiry register</h5><div class="small sc-muted">Pending and approved documents with an expiry date.</div></div>
 <form method="GET" class="d-flex flex-wrap gap-2">
  <select name="supplier_id" class="form-select form-select-sm"><option value="">All suppliers</option>@foreach($suppliers as $supplier)<option value="{{ $supplier->id }}" @selected((string)request('supplier_id')===(string)$supplier->id)>{{ $supplier->name }}</option>@endforeach</select>
  <select name="state" class="form-select form-select-sm"><option value="">All expiry dates</option><option value="expired" @selected(request('state')==='expired')>Expired</option><option value="7" @selected(request('state')==='7')>Next 7 days</option><option value="30" @selected(request('state')==='30')>Next 30 days</option><option value="60" @selected(request('state')==='60')>Next 60 days</option></select>
  <button class="btn btn-sm btn-outline-primary">Filter</button><a href="{{ route('supplier-compliance.index') }}" class="btn btn-sm btn-light">Reset</a>
 </form>
</div>
<div class="card"><div class="table-responsive"><table class="table align-middle mb-0 sc-table">
 <thead><tr><th class="ps-3">Supplier</th><th>Document</th><th>Reference</th><th>Expires</th><th>Time remaining</th><th>Status</th><th class="text-end pe-3">Action</th></tr></thead>
 <tbody>@forelse($documents as $document)@php($expired=$document->expires_at->lt(today()))@php($days=today()->diffInDays($document->expires_at,false))
 <tr><td class="ps-3 fw-semibold">{{ $document->supplier->name ?? 'Supplier' }}</td><td>{{ $document->title }}<div class="small sc-muted">{{ str_replace('_',' ',$document->document_type) }}</div></td><td>{{ $document->reference_number ?: '—' }}</td><td class="text-nowrap">{{ $document->expires_at->format('d M Y') }}</td>
 <td><span class="badge {{ $expired?'text-bg-danger':($days<=7?'text-bg-warning':'text-bg-info') }}">{{ $expired?'Expired '.abs($days).' day(s) ago':$days.' day(s) left' }}</span></td>
 <td><span class="badge {{ $document->status==='APPROVED'?'text-bg-success':'text-bg-secondary' }}">{{ $document->status }}</span></td><td class="text-end pe-3"><a class="btn btn-sm btn-outline-primary" href="{{ route('supplier-documents.download',$document) }}">Download</a></td></tr>
 @empty<tr><td colspan="7" class="text-center text-muted py-5">No supplier documents match this expiry filter.</td></tr>@endforelse</tbody>
 </table></div></div>
<div class="mt-3">{{ $documents->links() }}</div>
<div class="small sc-muted mt-3">Reminders are delivered as internal database notifications to active users with supplier-view or automation-view access. Configure Laravel's scheduler on your server to run them automatically.</div>
@endsection
