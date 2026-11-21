@extends('layouts.app')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
        <div>
            <h3 class="mb-1">Evidence Retention & Compliance</h3>
            <p class="text-muted mb-0">Manage retention periods, legal holds, archival and deletion approvals.</p>
        </div>
        <a href="{{ route('marketplaces.writes.evidence.index') }}" class="btn btn-outline-secondary">Evidence vault</a>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif

    <div class="row g-3 mb-4">
        @foreach([
            'Active' => $active, 'Expiring soon' => $expiring, 'Archived' => $archived,
            'Legal hold' => $held, 'Pending deletion approval' => $pending_deletion
        ] as $label => $value)
            <div class="col-6 col-xl">
                <div class="card h-100"><div class="card-body">
                    <div class="text-muted small">{{ $label }}</div>
                    <div class="fs-3 fw-semibold">{{ number_format($value) }}</div>
                </div></div>
            </div>
        @endforeach
    </div>

    <div class="card mb-4">
        <div class="card-header fw-semibold">Business retention policy</div>
        <div class="card-body">
            <form method="POST" action="{{ route('marketplaces.writes.retention.policy') }}" class="row g-3">
                @csrf
                <div class="col-md-3">
                    <label class="form-label">Retention period (days)</label>
                    <input type="number" name="retention_days" class="form-control" min="30" max="3650" value="{{ old('retention_days', $policy->retention_days ?? 365) }}" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Expiry alert before (days)</label>
                    <input type="number" name="alert_before_days" class="form-control" min="1" max="365" value="{{ old('alert_before_days', $policy->alert_before_days ?? 30) }}" required>
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="auto_archive" value="1" id="auto_archive" @checked(old('auto_archive', $policy->auto_archive ?? true))><label class="form-check-label" for="auto_archive">Automatically archive expired evidence</label></div>
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="deletion_requests_enabled" value="1" id="deletion_requests_enabled" @checked(old('deletion_requests_enabled', $policy->deletion_requests_enabled ?? false))><label class="form-check-label" for="deletion_requests_enabled">Allow deletion requests</label></div>
                </div>
                <div class="col-12"><button class="btn btn-primary">Save policy</button></div>
            </form>
            <small class="text-muted d-block mt-2">Changing the policy does not silently rewrite existing evidence expiry dates. New records receive their expiry when created in a future integration update.</small>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header fw-semibold">Evidence governance</div>
        <div class="card-body table-responsive">
            <table class="table table-hover align-middle">
                <thead><tr><th>Evidence</th><th>Status</th><th>Expiry</th><th>Actions</th></tr></thead>
                <tbody>
                @forelse($evidence as $item)
                    <tr>
                        <td><a href="{{ route('marketplaces.writes.evidence.show', $item->id) }}">{{ $item->title }}</a><div class="small text-muted">#{{ $item->id }} · {{ $item->evidence_type }}</div></td>
                        <td><span class="badge bg-secondary">{{ $item->retention_status ?? 'ACTIVE' }}</span></td>
                        <td>{{ $item->retention_expires_at ? \Illuminate\Support\Carbon::parse($item->retention_expires_at)->format('d M Y') : 'Not set' }}</td>
                        <td>
                            <form method="POST" action="{{ route('marketplaces.writes.retention.hold', $item->id) }}" class="d-flex gap-2 mb-2">
                                @csrf <input name="reason" class="form-control form-control-sm" placeholder="Legal hold reason" required maxlength="1000">
                                <button class="btn btn-sm btn-outline-warning">Place hold</button>
                            </form>
                            @if(($item->retention_status ?? '') === 'PENDING_DELETION' && (int) $item->deletion_requested_by !== (int) auth()->id())
                                <form method="POST" action="{{ route('marketplaces.writes.retention.approve', $item->id) }}" class="d-flex gap-2">
                                    @csrf <input name="reason" class="form-control form-control-sm" placeholder="Approval reason" required maxlength="1000">
                                    <button class="btn btn-sm btn-outline-danger">Approve request</button>
                                </form>
                            @endif
                            @if(($item->retention_status ?? '') !== 'PENDING_DELETION' && ($policy->deletion_requests_enabled ?? false))
                                <form method="POST" action="{{ route('marketplaces.writes.retention.request-deletion', $item->id) }}" class="d-flex gap-2 mt-2">
                                    @csrf <input name="reason" class="form-control form-control-sm" placeholder="Deletion request reason" required maxlength="1000">
                                    <button class="btn btn-sm btn-outline-secondary">Request deletion</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="text-center text-muted py-4">No evidence records found.</td></tr>
                @endforelse
                </tbody>
            </table>
            {{ $evidence->links() }}
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header fw-semibold">Active legal holds</div>
        <div class="card-body table-responsive">
            <table class="table table-sm">
                <thead><tr><th>Hold</th><th>Evidence</th><th>Reason</th><th>Placed</th><th>Action</th></tr></thead>
                <tbody>
                @forelse($holds as $hold)
                    <tr>
                        <td>#{{ $hold->id }}</td><td>#{{ $hold->evidence_id }}</td><td>{{ $hold->reason }}</td><td>{{ $hold->placed_at }}</td>
                        <td>
                            <form method="POST" action="{{ route('marketplaces.writes.retention.release', $hold->id) }}" class="d-flex gap-2">
                                @csrf <input name="reason" class="form-control form-control-sm" placeholder="Release reason" required maxlength="1000">
                                <button class="btn btn-sm btn-outline-warning">Release hold</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-muted text-center">No active legal holds.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <div class="card-header fw-semibold">Recent retention audit events</div>
        <div class="card-body table-responsive">
            <table class="table table-sm">
                <thead><tr><th>Date</th><th>Evidence</th><th>Event</th><th>Details</th><th>Actor</th></tr></thead>
                <tbody>
                @forelse($events as $event)
                    <tr><td>{{ $event->created_at }}</td><td>{{ $event->evidence_id ? '#'.$event->evidence_id : '—' }}</td><td>{{ $event->event_type }}</td><td>{{ $event->details }}</td><td>{{ $event->actor_id ?: 'System' }}</td></tr>
                @empty
                    <tr><td colspan="5" class="text-muted text-center">No retention events recorded.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
