@extends('layouts.app')

@section('content')
<div class="container-fluid py-3">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div><h4 class="mb-1">Listing Compliance & Suppression</h4><div class="text-muted">Track reported marketplace issues, evidence and resolution history.</div></div>
    </div>
    @if(session('success')) <div class="alert alert-success">{{ session('success') }}</div> @endif
    @if($errors->any()) <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div> @endif
    <div class="row g-3 mb-4">
        @foreach(['Open issues'=>'open','Suppression issues'=>'suppression','Critical / blockers'=>'critical','Resolved'=>'resolved'] as $label=>$key)
        <div class="col-6 col-xl-3"><div class="card h-100"><div class="card-body"><div class="text-muted small">{{ $label }}</div><div class="fs-3 fw-semibold">{{ $stats[$key] }}</div></div></div></div>
        @endforeach
    </div>
    <div class="card mb-4"><div class="card-header fw-semibold">Record marketplace issue</div><div class="card-body">
        <form method="POST" action="{{ route('listings.compliance.store') }}">@csrf
            <div class="row g-3">
                <div class="col-md-6"><label class="form-label">Listing</label><select name="listing_id" class="form-select" required><option value="">Select listing</option>@foreach($listings as $listing)<option value="{{ $listing->id }}">{{ $listing->product?->sku }} — {{ $listing->product?->name }} ({{ $listing->marketplace?->name }})</option>@endforeach</select></div>
                <div class="col-md-3"><label class="form-label">Issue code</label><input name="issue_code" class="form-control" maxlength="100" required placeholder="e.g. IMAGE_POLICY"></div>
                <div class="col-md-3"><label class="form-label">External issue ID</label><input name="external_issue_id" class="form-control" maxlength="120"></div>
                <div class="col-md-3"><label class="form-label">Issue type</label><select name="issue_type" class="form-select">@foreach(['COMPLIANCE','CONTENT','IMAGE','DOCUMENTATION','IDENTITY','PRODUCT','OTHER'] as $v)<option>{{ $v }}</option>@endforeach</select></div>
                <div class="col-md-3"><label class="form-label">Severity</label><select name="severity" class="form-select">@foreach(['INFO','WARNING','CRITICAL','BLOCKER'] as $v)<option {{ $v==='WARNING'?'selected':'' }}>{{ $v }}</option>@endforeach</select></div>
                <div class="col-md-3"><label class="form-label">Due date</label><input type="datetime-local" name="due_at" class="form-control"></div>
                <div class="col-md-3"><label class="form-label">Source</label><select name="source" class="form-select"><option>MANUAL</option><option>IMPORT</option></select></div>
                <div class="col-md-8"><label class="form-label">Issue title</label><input name="title" class="form-control" maxlength="180" required></div>
                <div class="col-md-4 d-flex align-items-end"><div class="form-check mb-2"><input type="checkbox" class="form-check-input" name="is_suppression" value="1" id="is_suppression"><label for="is_suppression" class="form-check-label">Listing is suppressed / at risk</label></div></div>
                <div class="col-12"><label class="form-label">Description / required action</label><textarea name="description" class="form-control" rows="2"></textarea></div>
                <div class="col-12"><button class="btn btn-primary">Record issue</button></div>
            </div>
        </form>
    </div></div>
    <div class="card"><div class="card-header d-flex justify-content-between align-items-center"><span class="fw-semibold">Issue register</span><form method="GET" class="d-flex flex-wrap gap-2"><select name="status" class="form-select form-select-sm"><option value="">All statuses</option>@foreach(['OPEN','IN_PROGRESS','RESOLVED','DISMISSED'] as $v)<option value="{{ $v }}" @selected(request('status')===$v)>{{ $v }}</option>@endforeach</select><select name="severity" class="form-select form-select-sm"><option value="">All severity</option>@foreach(['INFO','WARNING','CRITICAL','BLOCKER'] as $v)<option value="{{ $v }}" @selected(request('severity')===$v)>{{ $v }}</option>@endforeach</select><label class="form-check small"><input class="form-check-input" type="checkbox" name="suppression_only" value="1" @checked(request()->boolean('suppression_only'))> Suppression only</label><button class="btn btn-sm btn-outline-primary">Filter</button></form></div>
        <div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th>Listing / Issue</th><th>Severity</th><th>Status</th><th>Detected</th><th>Due</th><th>Action</th></tr></thead><tbody>
        @forelse($issues as $issue)<tr><td><div class="fw-semibold">{{ $issue->title }}</div><div class="small text-muted">{{ $issue->listing?->product?->sku }} · {{ $issue->listing?->marketplace?->name }} · {{ $issue->issue_code }}</div>@if($issue->is_suppression)<span class="badge bg-danger">Suppression</span>@endif</td><td>{{ $issue->severity }}</td><td>{{ $issue->status }}</td><td>{{ $issue->detected_at?->format('d M Y') }}</td><td>{{ $issue->due_at?->format('d M Y') ?? '—' }}</td><td><form method="POST" action="{{ route('listings.compliance.update', $issue) }}" class="d-flex gap-1">@csrf @method('PATCH')<select name="status" class="form-select form-select-sm">@foreach(['OPEN','IN_PROGRESS','RESOLVED','DISMISSED'] as $v)<option value="{{ $v }}" @selected($issue->status===$v)>{{ $v }}</option>@endforeach</select><input name="note" class="form-control form-control-sm" placeholder="Resolution / note" style="min-width:140px"><button class="btn btn-sm btn-outline-primary">Save</button></form></td></tr>
        @empty<tr><td colspan="6" class="text-center text-muted py-4">No marketplace issues recorded.</td></tr>@endforelse
        </tbody></table></div><div class="card-body">{{ $issues->links() }}</div>
    </div>
    <div class="mt-3"><div class="card"><div class="card-body d-flex flex-wrap align-items-center justify-content-between gap-2"><span class="small text-muted">Create a local recheck snapshot for a listing (no marketplace API request).</span><form method="POST" action="#" id="compliance-recheck-form">@csrf<select class="form-select form-select-sm d-inline-block" style="width:auto" onchange="if(this.value){document.getElementById('compliance-recheck-form').action='/listings/'+this.value+'/compliance/recheck'}"><option value="">Select listing</option>@foreach($listings as $listing)<option value="{{ $listing->id }}">{{ $listing->product?->sku }} — {{ $listing->marketplace?->name }}</option>@endforeach</select><button class="btn btn-sm btn-outline-primary">Run local recheck</button></form></div></div></div>
    <div class="mt-3 small text-muted">Recheck is a local issue-register snapshot. It does not query Amazon / Flipkart or change marketplace listing status.</div>
</div>
@endsection
