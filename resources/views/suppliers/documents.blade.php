@extends('layouts.app')
@section('title', 'Supplier Document Vault')
@section('subtitle', 'Secure supplier documents, verification and expiry compliance')
@section('content')
<div class="row g-3 mb-3">
    <div class="col-6 col-xl-3"><div class="card h-100"><div class="card-body"><div class="small text-muted">Total documents</div><div class="fs-3 fw-semibold">{{ $totalCount }}</div></div></div></div>
    <div class="col-6 col-xl-3"><div class="card h-100"><div class="card-body"><div class="small text-muted">Awaiting review</div><div class="fs-3 fw-semibold text-warning">{{ $pendingCount }}</div></div></div></div>
    <div class="col-6 col-xl-3"><div class="card h-100"><div class="card-body"><div class="small text-muted">Expiring in 30 days</div><div class="fs-3 fw-semibold text-warning">{{ $expiringCount }}</div></div></div></div>
    <div class="col-6 col-xl-3"><div class="card h-100"><div class="card-body"><div class="small text-muted">Expired</div><div class="fs-3 fw-semibold text-danger">{{ $expiredCount }}</div></div></div></div>
</div>
@can('purchases.create')
<div class="card mb-3"><div class="card-header fw-semibold">Upload supplier document</div><div class="card-body">
<form method="POST" action="{{ route('supplier-documents.store') }}" enctype="multipart/form-data" class="row g-3">@csrf
    <div class="col-md-4"><label class="form-label">Supplier *</label><select name="supplier_id" class="form-select" required><option value="">Select supplier</option>@foreach($suppliers as $supplier)<option value="{{ $supplier->id }}" @selected(old('supplier_id', request('supplier_id')) == $supplier->id)>{{ $supplier->name }}</option>@endforeach</select></div>
    <div class="col-md-4"><label class="form-label">Document category *</label><select name="document_type" class="form-select" required><option value="">Select category</option>@foreach(['GST_CERTIFICATE'=>'GST Certificate','PAN_CARD'=>'PAN Card','BUSINESS_LICENSE'=>'Business License','BANK_PROOF'=>'Bank Proof','ADDRESS_PROOF'=>'Address Proof','AGREEMENT'=>'Agreement','QUALITY_CERTIFICATE'=>'Quality Certificate','OTHER'=>'Other'] as $key=>$label)<option value="{{ $key }}" @selected(old('document_type')===$key)>{{ $label }}</option>@endforeach</select></div>
    <div class="col-md-4"><label class="form-label">Document title *</label><input name="title" class="form-control" value="{{ old('title') }}" maxlength="180" required placeholder="e.g. GST registration certificate"></div>
    <div class="col-md-4"><label class="form-label">Reference number</label><input name="reference_number" class="form-control" value="{{ old('reference_number') }}" maxlength="120"></div>
    <div class="col-md-4"><label class="form-label">Issue date</label><input type="date" name="issued_at" class="form-control" value="{{ old('issued_at') }}"></div>
    <div class="col-md-4"><label class="form-label">Expiry date</label><input type="date" name="expires_at" class="form-control" value="{{ old('expires_at') }}"></div>
    <div class="col-md-8"><label class="form-label">File * <span class="text-muted fw-normal">(PDF, JPG, PNG, WEBP; max 10 MB)</span></label><input type="file" name="file" class="form-control" accept=".pdf,.jpg,.jpeg,.png,.webp" required></div>
    <div class="col-md-4 d-flex align-items-end"><button class="btn btn-primary w-100"><i class="bi bi-cloud-arrow-up me-1"></i> Upload for review</button></div>
</form></div></div>
@endcan
@if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
<div class="card mb-3"><div class="card-body"><form method="GET" class="row g-2">
    <div class="col-md-3"><select name="supplier_id" class="form-select"><option value="">All suppliers</option>@foreach($suppliers as $supplier)<option value="{{ $supplier->id }}" @selected(request('supplier_id')==$supplier->id)>{{ $supplier->name }}</option>@endforeach</select></div>
    <div class="col-md-2"><select name="status" class="form-select"><option value="">All review statuses</option>@foreach(['PENDING','APPROVED','REJECTED'] as $status)<option value="{{ $status }}" @selected(request('status')===$status)>{{ $status }}</option>@endforeach</select></div>
    <div class="col-md-3"><select name="type" class="form-select"><option value="">All categories</option>@foreach(['GST_CERTIFICATE','PAN_CARD','BUSINESS_LICENSE','BANK_PROOF','ADDRESS_PROOF','AGREEMENT','QUALITY_CERTIFICATE','OTHER'] as $type)<option value="{{ $type }}" @selected(request('type')===$type)>{{ str_replace('_',' ',$type) }}</option>@endforeach</select></div>
    <div class="col-md-3"><input name="q" class="form-control" value="{{ request('q') }}" placeholder="Search title or reference"></div><div class="col-md-1 d-grid"><button class="btn btn-outline-primary">Filter</button></div>
</form></div></div>
<div class="card"><div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th>Document</th><th>Supplier</th><th>Reference</th><th>Expiry</th><th>Review</th><th>Uploaded</th><th class="text-end">Actions</th></tr></thead><tbody>
@forelse($documents as $doc)
<tr><td><div class="fw-semibold">{{ $doc->title }}</div><div class="small text-muted">{{ str_replace('_',' ',$doc->document_type) }} · {{ $doc->original_filename }} · {{ number_format($doc->file_size/1024,1) }} KB</div></td>
<td>{{ $doc->supplier?->name ?? '—' }}</td><td>{{ $doc->reference_number ?: '—' }}</td>
<td>@if(!$doc->expires_at)<span class="text-muted">No expiry</span>@elseif($doc->expiry_state==='EXPIRED')<span class="badge text-bg-danger">Expired {{ $doc->expires_at->format('d M Y') }}</span>@elseif($doc->expiry_state==='EXPIRING')<span class="badge text-bg-warning">Expiring {{ $doc->expires_at->format('d M Y') }}</span>@else{{ $doc->expires_at->format('d M Y') }}@endif</td>
<td><span class="badge text-bg-{{ $doc->status==='APPROVED'?'success':($doc->status==='REJECTED'?'danger':'warning') }}">{{ $doc->status }}</span>@if($doc->review_notes)<div class="small text-muted" title="{{ $doc->review_notes }}">{{ \Illuminate\Support\Str::limit($doc->review_notes,55) }}</div>@endif</td>
<td><div>{{ $doc->created_at->format('d M Y') }}</div><div class="small text-muted">{{ $doc->uploader?->name ?? '—' }}</div></td>
<td class="text-end text-nowrap"><a class="btn btn-sm btn-outline-secondary" href="{{ route('supplier-documents.download',$doc) }}"><i class="bi bi-download"></i> Download</a>
@can('purchases.edit')<button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#review-{{ $doc->id }}">Review</button>@endcan</td></tr>
@can('purchases.edit')<tr class="collapse" id="review-{{ $doc->id }}"><td colspan="7"><form method="POST" action="{{ route('supplier-documents.review',$doc) }}" class="row g-2">@csrf @method('PATCH')<div class="col-md-3"><select name="status" class="form-select" required>@foreach(['PENDING','APPROVED','REJECTED'] as $status)<option value="{{ $status }}" @selected($doc->status===$status)>{{ $status }}</option>@endforeach</select></div><div class="col-md-7"><input name="review_notes" class="form-control" value="{{ $doc->review_notes }}" placeholder="Verification notes or rejection reason"></div><div class="col-md-2 d-grid"><button class="btn btn-primary">Save review</button></div></form>@if($doc->verifier)<div class="small text-muted mt-2">Last reviewed by {{ $doc->verifier->name }} on {{ optional($doc->verified_at)->format('d M Y, h:i A') }}</div>@endif</td></tr>@endcan
@empty<tr><td colspan="7" class="text-center text-muted py-5">No supplier documents found. Upload a document to start the compliance register.</td></tr>@endforelse
</tbody></table></div>@if($documents->hasPages())<div class="card-footer">{{ $documents->links() }}</div>@endif</div>
@endsection
