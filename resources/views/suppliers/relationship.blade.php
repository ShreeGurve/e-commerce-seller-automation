@extends('layouts.app')

@section('content')
<div class="container-fluid py-3">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
        <div><h2 class="mb-1">Supplier Relationship Centre</h2><p class="text-muted mb-0">Communication notes, follow-ups, document references and supplier issues.</p></div>
        <a href="{{ route('suppliers.index') }}" class="btn btn-outline-secondary">Supplier Directory</a>
    </div>

    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

    <div class="row g-3 mb-4">
        <div class="col-md-4"><div class="card border-0 shadow-sm"><div class="card-body"><div class="text-muted small">Open records</div><div class="fs-3 fw-semibold">{{ $openCount }}</div></div></div></div>
        <div class="col-md-4"><div class="card border-0 shadow-sm"><div class="card-body"><div class="text-muted small">Overdue follow-ups</div><div class="fs-3 fw-semibold text-danger">{{ $overdueCount }}</div></div></div></div>
        <div class="col-md-4"><div class="card border-0 shadow-sm"><div class="card-body"><div class="text-muted small">Document references</div><div class="fs-3 fw-semibold">{{ $documentCount }}</div></div></div></div>
    </div>

    <div class="card border-0 shadow-sm mb-4"><div class="card-header bg-white"><strong>Add supplier activity</strong></div><div class="card-body">
        <form method="POST" action="{{ route('supplier-relationships.store') }}">@csrf
            <div class="row g-3">
                <div class="col-md-4"><label class="form-label">Supplier</label><select name="supplier_id" class="form-select" required><option value="">Select supplier</option>@foreach($suppliers as $supplier)<option value="{{ $supplier->id }}" @selected(old('supplier_id') == $supplier->id)>{{ $supplier->name }}</option>@endforeach</select></div>
                <div class="col-md-3"><label class="form-label">Record type</label><select name="type" class="form-select" required><option value="NOTE">Communication note</option><option value="FOLLOW_UP">Follow-up</option><option value="DOCUMENT">Document reference</option><option value="ISSUE">Supplier issue</option></select></div>
                <div class="col-md-5"><label class="form-label">Subject</label><input name="subject" class="form-control" maxlength="180" required value="{{ old('subject') }}" placeholder="e.g. Revised delivery commitment"></div>
                <div class="col-md-8"><label class="form-label">Details</label><textarea name="details" class="form-control" rows="2" placeholder="Discussion summary, issue details or document description">{{ old('details') }}</textarea></div>
                <div class="col-md-2"><label class="form-label">Reference no.</label><input name="reference_number" class="form-control" maxlength="100" value="{{ old('reference_number') }}" placeholder="Optional"></div>
                <div class="col-md-2"><label class="form-label">Due / expiry date</label><input type="date" name="due_date" class="form-control" value="{{ old('due_date') }}"></div>
                <div class="col-12"><button class="btn btn-primary">Save supplier record</button></div>
            </div>
        </form>
    </div></div>

    <div class="card border-0 shadow-sm"><div class="card-header bg-white d-flex flex-wrap justify-content-between align-items-center gap-2"><strong>Supplier activity register</strong>
        <form method="GET" class="d-flex flex-wrap gap-2"><select name="supplier_id" class="form-select form-select-sm"><option value="">All suppliers</option>@foreach($suppliers as $supplier)<option value="{{ $supplier->id }}" @selected(request('supplier_id') == $supplier->id)>{{ $supplier->name }}</option>@endforeach</select><select name="type" class="form-select form-select-sm"><option value="">All types</option>@foreach(['NOTE'=>'Notes','FOLLOW_UP'=>'Follow-ups','DOCUMENT'=>'Documents','ISSUE'=>'Issues'] as $key=>$label)<option value="{{ $key }}" @selected(request('type')===$key)>{{ $label }}</option>@endforeach</select><select name="status" class="form-select form-select-sm"><option value="">All statuses</option>@foreach(['OPEN','IN_PROGRESS','DONE','CANCELLED'] as $status)<option value="{{ $status }}" @selected(request('status')===$status)>{{ str_replace('_',' ',$status) }}</option>@endforeach</select><button class="btn btn-sm btn-outline-primary">Filter</button></form>
    </div><div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th>Supplier</th><th>Type</th><th>Subject / details</th><th>Reference</th><th>Due date</th><th>Status</th><th>Recorded by</th><th>Action</th></tr></thead><tbody>
        @forelse($interactions as $item)<tr><td class="fw-semibold">{{ $item->supplier?->name ?? 'Supplier unavailable' }}</td><td><span class="badge bg-light text-dark">{{ str_replace('_',' ',$item->type) }}</span></td><td><div class="fw-medium">{{ $item->subject }}</div><div class="small text-muted">{{ \Illuminate\Support\Str::limit($item->details, 110) }}</div></td><td>{{ $item->reference_number ?: '—' }}</td><td>@if($item->due_date){{ $item->due_date->format('d M Y') }} @if($item->due_date->isPast() && !in_array($item->status,['DONE','CANCELLED']))<span class="badge bg-danger">Overdue</span>@endif @else — @endif</td><td>{{ str_replace('_',' ',$item->status) }}</td><td>{{ $item->creator?->name ?? '—' }}</td><td>@if(!in_array($item->status,['DONE','CANCELLED']))<form method="POST" action="{{ route('supplier-relationships.status', $item) }}" class="d-flex gap-1">@csrf @method('PATCH')<select name="status" class="form-select form-select-sm"><option value="IN_PROGRESS">In progress</option><option value="DONE">Done</option><option value="CANCELLED">Cancel</option></select><button class="btn btn-sm btn-outline-primary">Update</button></form>@else<span class="text-muted small">Closed</span>@endif</td></tr>
        @empty<tr><td colspan="8" class="text-center text-muted py-4">No supplier activity recorded yet.</td></tr>@endforelse
    </tbody></table></div><div class="card-body">{{ $interactions->links() }}</div></div>
</div>
@endsection
