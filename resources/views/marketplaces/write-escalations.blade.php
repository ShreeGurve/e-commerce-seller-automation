@extends('layouts.app')
@section('content')
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-3"><div><h3 class="mb-1">Write Incident Escalations</h3><p class="text-muted mb-0">Configure automatic assignment when response or resolution targets are breached.</p></div><span class="badge bg-primary">Package 25</span></div>
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
    <div class="card mb-4"><div class="card-header fw-semibold">Add / update escalation rule</div><div class="card-body"><form method="POST" action="{{ route('marketplaces.writes.escalations.rules') }}">@csrf
        <div class="row g-3">
            <div class="col-md-3"><label class="form-label">Severity</label><select name="severity" class="form-select" required><option>CRITICAL</option><option>HIGH</option><option>MEDIUM</option><option>LOW</option></select></div>
            <div class="col-md-3"><label class="form-label">Breach type</label><select name="breach_type" class="form-select"><option value="RESPONSE">Response overdue</option><option value="RESOLUTION">Resolution overdue</option></select></div>
            <div class="col-md-2"><label class="form-label">Escalate after (minutes)</label><input type="number" name="after_minutes" min="1" max="43200" value="15" class="form-control" required></div>
            <div class="col-md-3"><label class="form-label">Assign to</label><select name="escalate_to" class="form-select"><option value="">Keep current assignee</option>@foreach($users as $person)<option value="{{ $person->id }}">{{ $person->name }}</option>@endforeach</select></div>
            <div class="col-md-1 d-flex align-items-end"><button class="btn btn-primary w-100">Save</button></div>
        </div><input type="hidden" name="enabled" value="1"></form></div></div>
    <div class="card mb-4"><div class="card-header fw-semibold">Configured rules</div><div class="table-responsive"><table class="table table-hover mb-0"><thead><tr><th>Severity</th><th>Breach</th><th>Delay</th><th>Assign to</th><th>Status</th></tr></thead><tbody>
    @forelse($rules as $rule)<tr><td>{{ $rule->severity }}</td><td>{{ $rule->breach_type }}</td><td>{{ $rule->after_minutes }} min</td><td>{{ $rule->assignee_name ?? 'Keep current assignee' }}</td><td>{{ $rule->enabled ? 'Enabled' : 'Disabled' }}</td></tr>@empty<tr><td colspan="5" class="text-center text-muted py-4">No escalation rules configured.</td></tr>@endforelse
    </tbody></table></div></div>
    <div class="card"><div class="card-header fw-semibold">Recent escalation history</div><div class="table-responsive"><table class="table table-hover mb-0"><thead><tr><th>When</th><th>Incident</th><th>Severity</th><th>Type</th><th>Assigned to</th></tr></thead><tbody>
    @forelse($history as $event)<tr><td>{{ \Illuminate\Support\Carbon::parse($event->escalated_at)->format('d M Y H:i') }}</td><td>{{ $event->incident_title }}</td><td>{{ $event->severity }}</td><td>{{ data_get(json_decode($event->metadata, true), 'breach_type', '—') }}</td><td>{{ $event->assignee_name ?? 'No reassignment' }}</td></tr>@empty<tr><td colspan="5" class="text-center text-muted py-4">No escalations recorded yet.</td></tr>@endforelse
    </tbody></table></div></div>
</div>
@endsection
