@extends('layouts.app')

@section('content')
<div class="container-fluid py-3">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div><h3 class="mb-1">Marketplace Write Audit</h3><p class="text-muted mb-0">Review the complete lifecycle of marketplace price and inventory submissions.</p></div>
        <a class="btn btn-outline-primary" href="{{ route('marketplaces.writes.audit.export', request()->query()) }}"><i class="fas fa-file-csv me-1"></i> Export CSV</a>
    </div>

    <div class="row g-3 mb-4">
        @foreach ([['Total Attempts', $counts['total']], ['Accepted', $counts['accepted']], ['Failed', $counts['failed']], ['Needs Review', $counts['review_required']], ['Prepared / Submitting', $counts['prepared']]] as [$label, $value])
            <div class="col-6 col-xl"><div class="card border-0 shadow-sm h-100"><div class="card-body"><div class="small text-muted">{{ $label }}</div><div class="fs-3 fw-semibold">{{ number_format($value) }}</div></div></div></div>
        @endforeach
    </div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white py-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div><strong>Executive risk overview</strong><div class="small text-muted">Operational indicators for the selected filters</div></div>
            <span class="badge bg-{{ $riskLevel === 'HIGH' ? 'danger' : ($riskLevel === 'ELEVATED' ? 'warning text-dark' : ($riskLevel === 'GUARDED' ? 'info text-dark' : 'success')) }}">{{ $riskLevel }} RISK</span>
        </div>
        <div class="card-body">
            <div class="row g-3 align-items-center">
                <div class="col-md-3"><div class="small text-muted">Risk signal score</div><div class="display-6 fw-semibold">{{ number_format($riskScore) }}<span class="fs-6 text-muted"> / 100</span></div></div>
                <div class="col-md-9"><div class="progress" role="progressbar" aria-label="Operational risk signal" aria-valuenow="{{ $riskScore }}" aria-valuemin="0" aria-valuemax="100" style="height: 12px"><div class="progress-bar bg-{{ $riskLevel === 'HIGH' ? 'danger' : ($riskLevel === 'ELEVATED' ? 'warning' : ($riskLevel === 'GUARDED' ? 'info' : 'success')) }}" style="width: {{ $riskScore }}%"></div></div><div class="small text-muted mt-2">Weighted from same-user approval exceptions, failed submissions, review-required attempts and missing verification records.</div></div>
            </div>
            <div class="table-responsive mt-4"><table class="table table-sm align-middle mb-0"><thead><tr><th>Marketplace</th><th class="text-end">Attempts</th><th class="text-end">Failed</th><th class="text-end">Needs review</th><th class="text-end">Verification pending</th></tr></thead><tbody>
                @forelse($marketplaceBreakdown as $marketplace)
                    <tr><td class="text-capitalize fw-semibold">{{ $marketplace->marketplace_code }}</td><td class="text-end">{{ number_format($marketplace->total_attempts) }}</td><td class="text-end">{{ number_format($marketplace->failed_attempts) }}</td><td class="text-end">{{ number_format($marketplace->review_required) }}</td><td class="text-end">{{ number_format($marketplace->verification_pending) }}</td></tr>
                @empty
                    <tr><td colspan="5" class="text-center text-muted py-3">No marketplace activity for these filters.</td></tr>
                @endforelse
            </tbody></table></div>
            <p class="small text-muted mt-3 mb-0">This score is an operational prioritization heuristic, not a regulatory rating, security certification or legal compliance determination. Review underlying incidents and audit records before taking action.</p>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white py-3"><strong>Compliance indicators</strong><span class="text-muted small ms-2">Based on the selected filters</span></div>
        <div class="card-body"><div class="row g-3">
            <div class="col-6 col-lg-3"><div class="small text-muted">Live confirmed</div><div class="fs-4 fw-semibold text-success">{{ number_format($counts['live_confirmed']) }}</div></div>
            <div class="col-6 col-lg-3"><div class="small text-muted">Awaiting verification</div><div class="fs-4 fw-semibold">{{ number_format($counts['verification_pending']) }}</div></div>
            <div class="col-6 col-lg-3"><div class="small text-muted">Independent approvals</div><div class="fs-4 fw-semibold">{{ number_format($counts['independent_approvals']) }}</div></div>
            <div class="col-6 col-lg-3"><div class="small text-muted">Same-user approval exceptions</div><div class="fs-4 fw-semibold {{ $counts['approval_exceptions'] > 0 ? 'text-danger' : '' }}">{{ number_format($counts['approval_exceptions']) }}</div></div>
        </div><p class="small text-muted mt-3 mb-0">These indicators are read-only reporting signals, not proof of regulatory compliance. Investigate any same-user approval exception against the underlying audit trail.</p></div>
    </div>

    <div class="card border-0 shadow-sm mb-4"><div class="card-body">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-2"><label class="form-label">Marketplace</label><select name="marketplace" class="form-select"><option value="">All</option><option value="amazon" @selected($filters['marketplace']==='amazon')>Amazon</option><option value="flipkart" @selected($filters['marketplace']==='flipkart')>Flipkart</option></select></div>
            <div class="col-md-2"><label class="form-label">Operation</label><select name="operation" class="form-select"><option value="">All</option><option value="PRICE_UPDATE" @selected($filters['operation']==='PRICE_UPDATE')>Price update</option><option value="INVENTORY_UPDATE" @selected($filters['operation']==='INVENTORY_UPDATE')>Inventory update</option></select></div>
            <div class="col-md-2"><label class="form-label">Submission status</label><select name="status" class="form-select"><option value="">All</option>@foreach(['PREPARED','SUBMITTING','ACCEPTED','REVIEW_REQUIRED','FAILED','EXPIRED'] as $status)<option value="{{ $status }}" @selected($filters['status']===$status)>{{ str_replace('_',' ',$status) }}</option>@endforeach</select></div>
            <div class="col-md-2"><label class="form-label">From</label><input type="date" name="from" value="{{ $filters['from'] }}" class="form-control"></div>
            <div class="col-md-2"><label class="form-label">To</label><input type="date" name="to" value="{{ $filters['to'] }}" class="form-control"></div>
            <div class="col-md-2 d-flex gap-2"><button class="btn btn-primary flex-fill">Filter</button><a href="{{ route('marketplaces.writes.audit.index') }}" class="btn btn-light">Reset</a></div>
        </form>
    </div></div>

    <div class="card border-0 shadow-sm"><div class="card-header bg-white py-3"><strong>Submission history</strong><span class="text-muted small ms-2">Showing up to 250 matching attempts</span></div>
        <div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead class="table-light"><tr><th>Attempt</th><th>Marketplace / SKU</th><th>Operation</th><th>Submission</th><th>Verification</th><th>Prepared / Approved</th><th>Verified by</th><th>Dates</th></tr></thead><tbody>
        @forelse($attempts as $attempt)
            <tr><td class="fw-semibold">#{{ $attempt->id }}<div class="small text-muted">{{ substr($attempt->payload_hash,0,12) }}…</div></td>
                <td><span class="text-capitalize">{{ $attempt->marketplace_code }}</span><div class="small text-muted">{{ $attempt->marketplace_sku }}</div></td>
                <td>{{ str_replace('_',' ', $attempt->operation) }}</td>
                <td><span class="badge bg-{{ $attempt->status==='ACCEPTED'?'success':($attempt->status==='FAILED'?'danger':($attempt->status==='REVIEW_REQUIRED'?'warning text-dark':'secondary')) }}">{{ str_replace('_',' ',$attempt->status) }}</span>@if($attempt->error_message)<div class="small text-danger mt-1">{{ \Illuminate\Support\Str::limit($attempt->error_message,90) }}</div>@endif</td>
                <td>@if($attempt->verification_status)<span class="badge bg-{{ $attempt->verification_status==='LIVE_CONFIRMED'?'success':'info text-dark' }}">{{ str_replace('_',' ',$attempt->verification_status) }}</span>@if($attempt->issue_code)<div class="small text-muted">{{ $attempt->issue_code }}</div>@endif @else <span class="text-muted">Not verified</span> @endif</td>
                <td><div>{{ $attempt->prepared_by_name ?? '—' }}</div><div class="small text-muted">Approved: {{ $attempt->approved_by_name ?? '—' }}</div></td>
                <td>{{ $attempt->verified_by_name ?? '—' }}</td>
                <td class="small"><div>{{ $attempt->created_at }}</div><div class="text-muted">Submitted: {{ $attempt->submitted_at ?? '—' }}</div><div class="text-muted">Verified: {{ $attempt->verified_at ?? '—' }}</div></td>
            </tr>
        @empty<tr><td colspan="8" class="text-center text-muted py-5">No marketplace write attempts match these filters.</td></tr>@endforelse
        </tbody></table></div>
    </div>
    <p class="small text-muted mt-3 mb-0">Audit and compliance indicators are read-only. Export is limited to the latest 5,000 matching attempts, includes approval-integrity classification, and is scoped to your business.</p>
</div>
@endsection
