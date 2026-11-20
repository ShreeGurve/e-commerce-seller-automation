@extends('layouts.app')

@section('content')
<div class="container-fluid py-3">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
        <div><h3 class="mb-1">Marketplace Write Incidents</h3><div class="text-muted">Track failed, uncertain and verification-related marketplace write issues.</div></div>
        <a href="{{ route('marketplaces.writes.audit.index') }}" class="btn btn-outline-secondary">Write Audit</a>
    </div>
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <div class="row g-3 mb-4">
        @foreach([['Total',$counts->total ?? 0],['Active',$counts->active ?? 0],['Critical',$counts->critical ?? 0],['Resolved',$counts->resolved ?? 0]] as [$label,$value])
        <div class="col-6 col-xl-3"><div class="card border-0 shadow-sm"><div class="card-body"><div class="text-muted small">{{ $label }}</div><div class="fs-3 fw-semibold">{{ $value }}</div></div></div></div>
        @endforeach
    </div>
    <div class="card border-0 shadow-sm mb-3"><div class="card-body">
        <h5 class="mb-3">Open an incident</h5>
        <form method="POST" action="{{ route('marketplaces.writes.incidents.store') }}" class="row g-2 align-items-end">
            @csrf
            <div class="col-md-2"><label class="form-label">Write attempt ID</label><input type="number" min="1" name="attempt_id" class="form-control" required></div>
            <div class="col-md-4"><label class="form-label">Incident title</label><input name="title" maxlength="180" class="form-control" required placeholder="e.g. Marketplace submission needs review"></div>
            <div class="col-md-2"><label class="form-label">Severity</label><select name="severity" class="form-select"><option>LOW</option><option selected>MEDIUM</option><option>HIGH</option><option>CRITICAL</option></select></div>
            <div class="col-md-3"><label class="form-label">Description</label><input name="description" maxlength="5000" class="form-control" placeholder="Optional investigation context"></div>
            <div class="col-md-1"><button class="btn btn-primary w-100">Open</button></div>
        </form>
    </div></div>
    <div class="card border-0 shadow-sm mb-3"><div class="card-body">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-3"><label class="form-label">Status</label><select name="status" class="form-select"><option value="">All statuses</option>@foreach(['OPEN','INVESTIGATING','RESOLVED','DISMISSED'] as $s)<option value="{{ $s }}" @selected(($filters['status'] ?? '')===$s)>{{ str_replace('_',' ',$s) }}</option>@endforeach</select></div>
            <div class="col-md-3"><label class="form-label">Severity</label><select name="severity" class="form-select"><option value="">All severities</option>@foreach(['LOW','MEDIUM','HIGH','CRITICAL'] as $s)<option value="{{ $s }}" @selected(($filters['severity'] ?? '')===$s)>{{ $s }}</option>@endforeach</select></div>
            <div class="col-md-2"><button class="btn btn-primary w-100">Apply filters</button></div>
            <div class="col-md-2"><a href="{{ route('marketplaces.writes.incidents.index') }}" class="btn btn-light w-100">Reset</a></div>
        </form>
    </div></div>
    <div class="card border-0 shadow-sm"><div class="table-responsive"><table class="table align-middle mb-0">
        <thead class="table-light"><tr><th>Incident</th><th>Marketplace / SKU</th><th>Severity</th><th>Status</th><th>Assigned to</th><th>Created</th><th>Update</th></tr></thead>
        <tbody>
        @forelse($incidents as $incident)
            <tr><td><div class="fw-semibold">{{ $incident->title }}</div><small class="text-muted">#{{ $incident->id }} · Attempt #{{ $incident->attempt_id }}</small><div class="small text-muted">{{ $incident->description }}</div></td>
                <td>{{ ucfirst($incident->marketplace_code) }}<div class="small text-muted">{{ $incident->operation }} · {{ $incident->marketplace_sku }}</div><div class="small">Submission: {{ $incident->submission_status }}</div></td>
                <td><span class="badge text-bg-{{ $incident->severity === 'CRITICAL' ? 'danger' : ($incident->severity === 'HIGH' ? 'warning' : 'secondary') }}">{{ $incident->severity }}</span></td>
                <td>{{ str_replace('_',' ',$incident->status) }}</td><td>{{ $incident->assignee_name ?: 'Unassigned' }}</td><td>{{ $incident->created_at }}</td>
                <td><form method="POST" action="{{ route('marketplaces.writes.incidents.update', $incident->id) }}" class="d-flex flex-column gap-1">@csrf @method('PATCH')
                    <select name="status" class="form-select form-select-sm"><option value="{{ $incident->status }}">Keep: {{ $incident->status }}</option>@foreach(['OPEN','INVESTIGATING','RESOLVED','DISMISSED'] as $s)@if($s !== $incident->status)<option value="{{ $s }}">{{ $s }}</option>@endif @endforeach</select>
                    <select name="severity" class="form-select form-select-sm">@foreach(['LOW','MEDIUM','HIGH','CRITICAL'] as $s)<option value="{{ $s }}" @selected($incident->severity===$s)>{{ $s }}</option>@endforeach</select>
                    <input name="note" class="form-control form-control-sm" placeholder="Update note (optional)"><button class="btn btn-sm btn-outline-primary">Save</button>
                </form></td></tr>
        @empty<tr><td colspan="7" class="text-center text-muted py-5">No marketplace write incidents found.</td></tr>@endforelse
        </tbody></table></div></div>
    <div class="text-muted small mt-3">This workspace tracks operational incidents only. It never retries or changes marketplace submissions, prices, inventory or orders.</div>
</div>
@endsection
