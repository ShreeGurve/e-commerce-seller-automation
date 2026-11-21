@extends('layouts.app')

@section('content')
<div class="container-fluid py-4">
    <div class="mb-4">
        <h1 class="h3 mb-1">Marketplace Write Retry Authorizations</h1>
        <p class="text-muted mb-0">Controlled retry review for definitively failed writes. Uncertain submissions are deliberately excluded.</p>
    </div>
    @if(session('success')) <div class="alert alert-success">{{ session('success') }}</div> @endif
    @if($errors->any())
        <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif

    <div class="alert alert-warning">
        <strong>Safety rule:</strong> Never retry a SUBMITTING or REVIEW_REQUIRED attempt. Verify the marketplace state first. An approval here only permits preparing a new attempt through the existing preview and approval workflow; it never calls Amazon or Flipkart.
    </div>

    <div class="row g-4">
        <div class="col-xl-5">
            <div class="card">
                <div class="card-header bg-white"><strong>Request retry authorization</strong></div>
                <div class="card-body">
                    <form method="POST" action="{{ route('marketplaces.writes.retry-authorizations.store') }}">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label">Eligible failed incident</label>
                            <select class="form-select" name="incident_id" required>
                                <option value="">Select incident</option>
                                @foreach($eligibleIncidents as $incident)
                                    <option value="{{ $incident->id }}">#{{ $incident->id }} — {{ $incident->marketplace_code }} / {{ $incident->operation }} / {{ $incident->marketplace_sku }} (Attempt #{{ $incident->attempt_id }})</option>
                                @endforeach
                            </select>
                            <div class="form-text">Only FAILED attempts appear. Uncertain outcomes are not eligible.</div>
                        </div>
                        <input type="hidden" name="verification_outcome" value="NOT_APPLIED_CONFIRMED">
                        <div class="mb-3">
                            <label class="form-label">Marketplace evidence reference</label>
                            <input class="form-control" name="evidence_reference" maxlength="500" required placeholder="Seller Central / Seller Hub case, report, or evidence vault reference">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Why is a fresh attempt required?</label>
                            <textarea class="form-control" name="request_reason" rows="4" minlength="10" maxlength="3000" required></textarea>
                        </div>
                        <button class="btn btn-primary" type="submit" @disabled($eligibleIncidents->isEmpty())>Submit for independent approval</button>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-xl-7">
            <div class="card">
                <div class="card-header bg-white"><strong>Authorization history</strong></div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead><tr><th>Request</th><th>Attempt</th><th>Requester</th><th>Status</th><th>Evidence</th><th>Review</th></tr></thead>
                        <tbody>
                        @forelse($requests as $item)
                            <tr>
                                <td>#{{ $item->id }}<div class="small text-muted">{{ $item->incident_title }}</div></td>
                                <td>{{ strtoupper($item->marketplace_code) }}<div class="small text-muted">{{ $item->operation }} · {{ $item->marketplace_sku }}</div></td>
                                <td>{{ $item->requester_name ?? 'Former user' }}</td>
                                <td><span class="badge bg-secondary">{{ $item->status }}</span><div class="small text-muted">Attempt: {{ $item->attempt_status }}</div></td>
                                <td class="small">{{ $item->evidence_reference }}</td>
                                <td style="min-width:210px">
                                    @if($item->status === 'PENDING')
                                        <form method="POST" action="{{ route('marketplaces.writes.retry-authorizations.review', $item->id) }}">
                                            @csrf
                                            <select class="form-select form-select-sm mb-1" name="decision" required>
                                                <option value="APPROVED">Approve</option>
                                                <option value="REJECTED">Reject</option>
                                            </select>
                                            <input class="form-control form-control-sm mb-1" name="review_note" minlength="5" maxlength="2000" required placeholder="Independent review note">
                                            <button class="btn btn-sm btn-outline-primary">Record review</button>
                                        </form>
                                    @else
                                        <span class="small">{{ $item->reviewer_name ?? 'Reviewer unavailable' }}</span>
                                        <div class="small text-muted">{{ $item->review_note }}</div>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-muted py-4">No retry authorization requests yet.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="card-body">{{ $requests->links() }}</div>
            </div>
        </div>
    </div>
</div>
@endsection
