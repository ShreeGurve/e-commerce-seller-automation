@extends('layouts.app')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
        <div>
            <h1 class="h3 mb-1">Marketplace Write Governance</h1>
            <p class="text-muted mb-0">Propose operational write controls and require independent approval before policy changes take effect.</p>
        </div>
        <span class="badge bg-success">Approved governance policy enforced at submission</span>
    </div>

    @if (session('success')) <div class="alert alert-success">{{ session('success') }}</div> @endif
    @if ($errors->any()) <div class="alert alert-danger"><ul class="mb-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div> @endif

    @php($emergencyActive = (bool) ($emergencyControl->is_active ?? false))
    <div class="card mb-4 border-{{ $emergencyActive ? 'danger' : 'success' }}">
        <div class="card-header"><strong>Emergency marketplace write stop</strong></div>
        <div class="card-body">
            <p class="mb-2"><span class="badge {{ $emergencyActive ? 'bg-danger' : 'bg-success' }}">{{ $emergencyActive ? 'ACTIVE — submissions blocked' : 'INACTIVE' }}</span></p>
            @if ($emergencyActive)
                <p class="small">Activated by user #{{ $emergencyControl->activated_by }} at {{ $emergencyControl->activated_at }}. Reason: {{ $emergencyControl->reason }}</p>
                @if (auth()->user()->isSuperAdmin() && (int) $emergencyControl->activated_by !== (int) auth()->id())
                    <form method="POST" action="{{ route('marketplaces.writes.governance.emergency.release') }}" class="d-flex flex-wrap gap-2">
                        @csrf
                        <input class="form-control" style="max-width:420px" name="reason" minlength="10" maxlength="1000" placeholder="Independent release reason (min 10 characters)" required>
                        <button class="btn btn-outline-danger" type="submit">Release emergency stop</button>
                    </form>
                @else
                    <p class="small text-muted mb-0">A different Super Admin must release this stop.</p>
                @endif
            @elseif (auth()->user()->isSuperAdmin())
                <form method="POST" action="{{ route('marketplaces.writes.governance.emergency.activate') }}" class="d-flex flex-wrap gap-2">
                    @csrf
                    <input class="form-control" style="max-width:420px" name="reason" minlength="10" maxlength="1000" placeholder="Emergency reason (min 10 characters)" required>
                    <button class="btn btn-danger" type="submit">Activate emergency stop</button>
                </form>
            @else
                <p class="small text-muted mb-0">Only a Super Admin can activate or release the emergency stop.</p>
            @endif
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header"><strong>Current approved policy</strong></div>
        <div class="card-body">
            @php($defaults = ['writes_enabled' => false, 'inventory_auto_sync' => false, 'require_second_person' => true, 'require_evidence_reference' => true])
            <div class="row g-3">
                @foreach ($defaults as $key => $default)
                    <div class="col-md-3"><div class="border rounded p-3 h-100"><div class="small text-muted">{{ str($key)->replace('_', ' ')->title() }}</div><strong>{{ ($policy->{$key} ?? $default) ? 'Enabled' : 'Disabled' }}</strong></div></div>
                @endforeach
            </div>
            <p class="small text-muted mt-3 mb-0">These approved settings are checked again immediately before marketplace submission. Inventory submissions also require the inventory control to be enabled. Evidence references are checked against active evidence-vault records.</p>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header"><strong>Request policy changes</strong></div>
        <div class="card-body">
            <form method="POST" action="{{ route('marketplaces.writes.governance.store') }}">
                @csrf
                <div class="row g-3">
                    @foreach ($defaults as $key => $default)
                        <div class="col-md-3"><label class="form-label d-block">{{ str($key)->replace('_', ' ')->title() }}</label><select class="form-select" name="{{ $key }}"><option value="">No change</option><option value="1">Enable</option><option value="0">Disable</option></select></div>
                    @endforeach
                </div>
                <div class="mt-3"><label class="form-label">Reason for change</label><textarea name="reason" class="form-control" rows="3" minlength="10" maxlength="2000" required></textarea></div>
                <button class="btn btn-primary mt-3" type="submit">Submit for independent approval</button>
            </form>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header"><strong>Change requests</strong></div>
        <div class="table-responsive"><table class="table table-striped align-middle mb-0">
            <thead><tr><th>ID</th><th>Requested by</th><th>Changes</th><th>Reason</th><th>Status</th><th>Review</th></tr></thead>
            <tbody>
            @forelse ($requests as $item)
                <tr>
                    <td>#{{ $item->id }}</td><td>{{ $item->requested_by }}</td>
                    <td><code>{{ json_encode(json_decode($item->proposed_changes, true), JSON_UNESCAPED_SLASHES) }}</code></td>
                    <td>{{ $item->request_reason }}</td>
                    <td><span class="badge {{ $item->status === 'APPROVED' ? 'bg-success' : ($item->status === 'REJECTED' ? 'bg-danger' : 'bg-warning text-dark') }}">{{ $item->status }}</span></td>
                    <td>
                        @if ($item->status === 'PENDING' && (int) $item->requested_by !== (int) auth()->id())
                            <form method="POST" action="{{ route('marketplaces.writes.governance.review', $item->id) }}" class="d-grid gap-2">
                                @csrf
                                <input type="text" name="reason" class="form-control form-control-sm" placeholder="Review reason (min 10 chars)" minlength="10" maxlength="2000" required>
                                <div class="btn-group"><button class="btn btn-sm btn-success" name="decision" value="approve">Approve</button><button class="btn btn-sm btn-outline-danger" name="decision" value="reject">Reject</button></div>
                            </form>
                        @elseif ($item->reviewed_at)
                            <small>Reviewer: {{ $item->reviewed_by }}<br>{{ $item->review_reason }}</small>
                        @else
                            <small class="text-muted">Awaiting another user</small>
                        @endif
                    </td>
                </tr>
            @empty <tr><td colspan="6" class="text-center text-muted py-4">No governance requests yet.</td></tr> @endforelse
            </tbody>
        </table></div>
        <div class="card-footer">{{ $requests->links() }}</div>
    </div>

    <div class="card"><div class="card-header"><strong>Governance event history</strong></div><div class="table-responsive"><table class="table table-sm mb-0"><thead><tr><th>When</th><th>Event</th><th>Actor</th><th>Request</th><th>Details</th></tr></thead><tbody>
        @forelse ($events as $event)<tr><td>{{ $event->created_at }}</td><td>{{ $event->event_type }}</td><td>{{ $event->actor_id ?? 'System' }}</td><td>{{ $event->request_id ? '#'.$event->request_id : '—' }}</td><td>{{ $event->details }}</td></tr>@empty<tr><td colspan="5" class="text-center text-muted">No events recorded.</td></tr>@endforelse
    </tbody></table></div></div>
</div>
@endsection
