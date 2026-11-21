@extends('layouts.app')
@section('content')
<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div><h3 class="mb-1">Marketplace Write Recovery</h3><p class="text-muted mb-0">Guided remediation, evidence and independent closure approval.</p></div>
    </div>
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <div class="card mb-4"><div class="card-header fw-semibold">Start a recovery playbook</div><div class="card-body">
        <form method="POST" action="{{ route('marketplaces.writes.recovery.store') }}" class="row g-3">@csrf
            <div class="col-md-4"><label class="form-label">Open incident</label><select name="incident_id" class="form-select" required><option value="">Select incident</option>@foreach($incidents as $incident)<option value="{{ $incident->id }}">#{{ $incident->id }} · {{ $incident->severity }} · {{ $incident->title }}</option>@endforeach</select></div>
            <div class="col-md-3"><label class="form-label">Playbook name</label><input name="playbook" class="form-control" maxlength="80" value="Marketplace write recovery" required></div>
            <div class="col-md-2"><label class="form-label">Owner</label><select name="owner_id" class="form-select"><option value="">Unassigned</option>@foreach($users as $u)<option value="{{ $u->id }}">{{ $u->name }}</option>@endforeach</select></div>
            <div class="col-md-2"><label class="form-label">Target date</label><input type="datetime-local" name="target_at" class="form-control"></div>
            <div class="col-md-1 d-flex align-items-end"><button class="btn btn-primary w-100">Create</button></div>
        </form>
    </div></div>
    @forelse($cases as $case)
        <div class="card mb-3"><div class="card-header d-flex justify-content-between flex-wrap gap-2"><div><strong>#{{ $case->id }} · {{ $case->playbook }}</strong><div class="small text-muted">Incident #{{ $case->incident_id }} · {{ $case->incident_title }} · {{ $case->severity }}</div></div><div><span class="badge bg-secondary">{{ $case->status }}</span> <span class="small text-muted">Owner: {{ $case->owner_name ?? 'Unassigned' }}</span></div></div>
        <div class="card-body">
        @php($retryAuthorization = $retryAuthorizations[$case->incident_id] ?? null)
        @if($retryAuthorization)
            <div class="alert alert-success py-2"><strong>Retry authorization approved</strong> · Authorization #{{ $retryAuthorization->id }}. Create a fresh preview through the normal Marketplace Write Execution workflow; this recovery screen never submits a marketplace change.</div>
        @else
            <div class="alert alert-warning py-2">Retry checkpoint remains locked until an independent reviewer approves a valid retry authorization. <a href="{{ url('/marketplace-writes/retry-authorizations') }}">Open Retry Authorizations</a>.</div>
        @endif
        <div class="row g-3">@foreach(($steps[$case->id] ?? collect()) as $step)
            <div class="col-lg-6"><form method="POST" action="{{ route('marketplaces.writes.recovery.steps.update', [$case->id, $step->id]) }}" class="border rounded p-3 h-100">@csrf @method('PATCH')
                <div class="fw-semibold mb-2">{{ $step->sort_order }}. {{ $step->step_title }}</div>
                @if((int) $step->sort_order === 5)<div class="small text-muted mb-2">Requires Package 33 authorization approval. No automatic retry is performed.</div>@endif
                <div class="row g-2"><div class="col-sm-4"><select name="status" class="form-select form-select-sm"><option value="PENDING" @selected($step->status==='PENDING')>Pending</option><option value="DONE" @selected($step->status==='DONE')>Done</option><option value="BLOCKED" @selected($step->status==='BLOCKED')>Blocked</option></select></div><div class="col-sm-6"><input name="step_notes" class="form-control form-control-sm" placeholder="Notes / evidence reference" value="{{ $step->step_notes }}"></div><div class="col-sm-2"><button class="btn btn-sm btn-outline-primary w-100">Save</button></div></div>
            </form></div>
        @endforeach</div>
        <div class="mt-3 border rounded p-3">
            <div class="d-flex justify-content-between align-items-center"><strong>Recovery evidence</strong><a class="btn btn-sm btn-outline-primary" href="{{ url('/marketplace-writes/evidence') }}">Open Evidence Vault</a></div>
            @forelse(($evidence[$case->id] ?? collect()) as $item)
                <div class="small mt-2">{{ $item->title }} <span class="text-muted">· {{ $item->evidence_type }} · {{ $item->reference_code ?: 'No reference code' }}</span></div>
            @empty
                <div class="small text-muted mt-2">No evidence linked. Add evidence in the Evidence Vault using subject type RECOVERY and subject ID {{ $case->id }}.</div>
            @endforelse
        </div>
        @if($case->status !== 'CLOSED')
            <form method="POST" action="{{ route('marketplaces.writes.recovery.closure.request', $case->id) }}" class="row g-2 mt-3">@csrf
                <div class="col-md-10"><textarea name="resolution_summary" class="form-control" rows="2" placeholder="Resolution summary (required; all steps done and recovery evidence attached)" required></textarea></div>
                <div class="col-md-2"><button class="btn btn-warning w-100">Request Closure Review</button></div>
            </form>
        @else
            <div class="alert alert-success mt-3 mb-0">Closed after independent approval. {{ $case->resolution_summary }}</div>
        @endif
        @foreach(($closureReviews[$case->id] ?? collect()) as $review)
            <div class="border rounded p-3 mt-3">
                <div class="d-flex justify-content-between"><strong>Closure review #{{ $review->id }}</strong><span class="badge bg-{{ $review->status === 'APPROVED' ? 'success' : ($review->status === 'REJECTED' ? 'danger' : 'warning') }}">{{ $review->status }}</span></div>
                <div class="small text-muted">Requested by {{ $review->requester_name ?? 'Unknown' }} · {{ $review->created_at }} @if($review->reviewer_name) · Reviewed by {{ $review->reviewer_name }} @endif</div>
                <div class="mt-2">{{ $review->resolution_summary }}</div>
                @if($review->review_note)<div class="small text-muted mt-1">Review note: {{ $review->review_note }}</div>@endif
                @if($review->status === 'PENDING')
                    <form method="POST" action="{{ route('marketplaces.writes.recovery.closure.review', $review->id) }}" class="row g-2 mt-2">@csrf
                        <div class="col-md-3"><select name="decision" class="form-select" required><option value="">Decision</option><option value="APPROVED">Approve & close</option><option value="REJECTED">Reject</option></select></div>
                        <div class="col-md-7"><input name="review_note" class="form-control" placeholder="Review note (required for rejection)"></div>
                        <div class="col-md-2"><button class="btn btn-outline-success w-100">Submit Review</button></div>
                    </form>
                @endif
            </div>
        @endforeach
        </div></div>
    @empty <div class="alert alert-info">No recovery cases yet. Create one from an open incident.</div> @endforelse
</div>
@endsection
