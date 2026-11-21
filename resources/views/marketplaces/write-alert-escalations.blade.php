@extends('layouts.app')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
        <div>
            <h1 class="h3 mb-1">Alert Escalation Rules</h1>
            <p class="text-muted mb-0">Escalate unread marketplace write alerts to business administrators.</p>
        </div>
        <a href="{{ route('marketplaces.writes.alerts.index') }}" class="btn btn-outline-secondary">Notification Center</a>
    </div>

    @if(session('success')) <div class="alert alert-success">{{ session('success') }}</div> @endif
    @if($errors->any()) <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div> @endif

    <div class="card shadow-sm mb-4">
        <div class="card-header bg-white"><strong>Create or update a rule</strong></div>
        <div class="card-body">
            <form method="POST" action="{{ route('marketplaces.writes.alerts.escalations.store') }}" class="row g-3">
                @csrf
                <div class="col-md-4"><label class="form-label">Alert category</label><select name="category" class="form-select" required><option value="marketplace_write_attempt">Marketplace write attempt</option><option value="marketplace_write_incident">Marketplace write incident</option></select></div>
                <div class="col-md-2"><label class="form-label">Severity</label><select name="severity" class="form-select" required><option value="critical">Critical</option><option value="high">High</option><option value="warning">Warning</option></select></div>
                <div class="col-md-2"><label class="form-label">Initial delay (min)</label><input type="number" name="delay_minutes" class="form-control" min="5" max="1440" value="30" required></div>
                <div class="col-md-2"><label class="form-label">Repeat interval (min)</label><input type="number" name="repeat_every_minutes" class="form-control" min="5" max="1440" value="60" required></div>
                <div class="col-md-2"><label class="form-label">Maximum escalations</label><input type="number" name="max_escalations" class="form-control" min="1" max="10" value="3" required></div>
                <div class="col-md-6"><div class="form-check"><input type="checkbox" class="form-check-input" name="notify_business_admins" value="1" id="notifyAdmins" checked><label class="form-check-label" for="notifyAdmins">Notify Super Admin and Admin users</label></div></div>
                <div class="col-md-6"><div class="form-check"><input type="checkbox" class="form-check-input" name="enabled" value="1" id="ruleEnabled" checked><label class="form-check-label" for="ruleEnabled">Enable rule</label></div></div>
                <div class="col-12"><button class="btn btn-primary" type="submit">Save escalation rule</button></div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header bg-white"><strong>Configured rules</strong></div>
        <div class="table-responsive"><table class="table table-hover align-middle mb-0">
            <thead><tr><th>Category</th><th>Severity</th><th>Initial delay</th><th>Repeat</th><th>Max</th><th>Recipients</th><th>Status</th></tr></thead>
            <tbody>
            @forelse($rules as $rule)
                <tr><td>{{ str_replace('_', ' ', ucfirst($rule->category)) }}</td><td>{{ ucfirst($rule->severity) }}</td><td>{{ $rule->delay_minutes }} min</td><td>{{ $rule->repeat_every_minutes }} min</td><td>{{ $rule->max_escalations }}</td><td>{{ $rule->notify_business_admins ? 'Business admins' : 'Permission holders' }}</td><td><span class="badge {{ $rule->enabled ? 'bg-success' : 'bg-secondary' }}">{{ $rule->enabled ? 'Enabled' : 'Disabled' }}</span></td></tr>
            @empty
                <tr><td colspan="7" class="text-center text-muted py-4">No escalation rules configured yet.</td></tr>
            @endforelse
            </tbody>
        </table></div>
    </div>
</div>
@endsection
