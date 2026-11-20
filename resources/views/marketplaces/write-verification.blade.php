@extends('layouts.app')

@section('content')
<div class="container-fluid py-3">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1">Marketplace Submission Verification</h1>
            <div class="text-muted small">Confirm what actually happened in the Amazon or Flipkart seller portal after an API submission.</div>
        </div>
        <a href="{{ route('marketplaces.writes.index') }}" class="btn btn-outline-secondary btn-sm">Write Control Center</a>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger"><strong>Unable to record verification.</strong><ul class="mb-0 mt-1">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif
    @if (session('success')) <div class="alert alert-success">{{ session('success') }}</div> @endif

    <div class="alert alert-info small">
        An API response of ACCEPTED does not prove that a listing change is live. Check the seller portal and record the observed result here. This page is manual verification only: it does not call either marketplace, retry a write, or change prices or inventory.
    </div>

    <div class="row g-3 mb-4">
        <div class="col-sm-4"><div class="card border-0 shadow-sm"><div class="card-body"><div class="small text-muted">Awaiting verification</div><div class="h3 mb-0">{{ $counts['awaiting'] }}</div></div></div></div>
        <div class="col-sm-4"><div class="card border-0 shadow-sm"><div class="card-body"><div class="small text-muted">Live confirmed</div><div class="h3 mb-0 text-success">{{ $counts['confirmed'] }}</div></div></div></div>
        <div class="col-sm-4"><div class="card border-0 shadow-sm"><div class="card-body"><div class="small text-muted">Needs attention / not visible</div><div class="h3 mb-0 text-warning">{{ $counts['attention'] }}</div></div></div></div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white"><strong>Submitted marketplace writes</strong></div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead><tr><th>Attempt</th><th>Marketplace / operation</th><th>SKU</th><th>Submission</th><th>Verification</th><th>Record seller-portal result</th></tr></thead>
                <tbody>
                @forelse ($attempts as $attempt)
                    <tr>
                        <td>#{{ $attempt->id }}<div class="small text-muted">{{ $attempt->submitted_at }}</div><div class="small text-muted">Hash {{ substr($attempt->payload_hash, 0, 12) }}…</div></td>
                        <td>{{ strtoupper($attempt->marketplace_code) }}<div class="small text-muted">{{ str_replace('_', ' ', $attempt->operation) }}</div><div class="small text-muted">Prepared: {{ $attempt->prepared_by_name ?? '—' }} · Submitted: {{ $attempt->approved_by_name ?? '—' }}</div></td>
                        <td>{{ $attempt->marketplace_sku }}</td>
                        <td><span class="badge {{ $attempt->submission_status === 'ACCEPTED' ? 'text-bg-success' : 'text-bg-warning' }}">{{ $attempt->submission_status }}</span></td>
                        <td>
                            @if ($attempt->verification_status)
                                <span class="badge {{ $attempt->verification_status === 'LIVE_CONFIRMED' ? 'text-bg-success' : (in_array($attempt->verification_status, ['ACTION_REQUIRED','REJECTED','NOT_VISIBLE'], true) ? 'text-bg-warning' : 'text-bg-secondary') }}">{{ str_replace('_', ' ', $attempt->verification_status) }}</span>
                                <div class="small text-muted">{{ $attempt->checked_at }} · {{ $attempt->verified_by_name ?? '—' }}</div>
                                @if ($attempt->issue_code)<div class="small">Issue: {{ $attempt->issue_code }}</div>@endif
                                @if ($attempt->verification_note)<div class="small text-muted">{{ \Illuminate\Support\Str::limit($attempt->verification_note, 120) }}</div>@endif
                                <a class="small d-inline-block mt-1" href="{{ route('marketplaces.writes.verification.history', $attempt->id) }}">View verification history</a>
                                @if ($attempt->evidence_url)<a href="{{ $attempt->evidence_url }}" target="_blank" rel="noopener noreferrer" class="small">Evidence link</a>@endif
                            @else
                                <span class="text-muted small">Not checked yet</span>
                            @endif
                        </td>
                        <td style="min-width: 300px">
                            @if ($attempt->verification_status !== 'LIVE_CONFIRMED')
                                <form method="POST" action="{{ route('marketplaces.writes.verification.store', $attempt->id) }}" class="d-grid gap-2">
                                    @csrf
                                    <select name="status" class="form-select form-select-sm" required>
                                        <option value="">Select observed outcome</option>
                                        @foreach (['LIVE_CONFIRMED' => 'Live change confirmed', 'PENDING' => 'Still processing / pending', 'NOT_VISIBLE' => 'Change not visible', 'ACTION_REQUIRED' => 'Marketplace action required', 'REJECTED' => 'Marketplace rejected change'] as $value => $label)
                                            <option value="{{ $value }}" @selected(old('status') === $value)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                    <input name="external_submission_id" class="form-control form-control-sm" maxlength="150" placeholder="Marketplace submission ID (optional)" value="{{ old('external_submission_id') }}">
                                    <input name="issue_code" class="form-control form-control-sm" maxlength="100" placeholder="Issue code (optional)" value="{{ old('issue_code') }}">
                                    <textarea name="verification_note" class="form-control form-control-sm" rows="2" minlength="10" maxlength="2000" required placeholder="What did you observe in Seller Central / Flipkart Seller Hub?">{{ old('verification_note') }}</textarea>
                                    <input type="url" name="evidence_url" class="form-control form-control-sm" maxlength="2048" placeholder="HTTPS evidence link (optional)" value="{{ old('evidence_url') }}">
                                    <button type="submit" class="btn btn-sm btn-primary">Record verification</button>
                                    <div class="small text-muted">The submitter cannot verify their own submission. A live-confirmed result is final.</div>
                                </form>
                            @else
                                <span class="small text-success">Final confirmation recorded.</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted py-4">No accepted or uncertain marketplace submissions are awaiting verification.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
