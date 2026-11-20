@extends('layouts.app')
@section('title', 'Marketplace Write SLA & Escalations')
@section('content')
<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-center mb-3"><div><h3 class="mb-1">Write Incident SLA & Escalations</h3><p class="text-muted mb-0">Monitor response and resolution targets for marketplace write incidents.</p></div><span class="badge bg-primary">Package 24</span></div>
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
    <div class="row g-3 mb-4"><div class="col-md-6"><div class="card border-0 shadow-sm"><div class="card-body"><div class="text-muted">Response SLA breaches</div><div class="display-6">{{ $response_breaches }}</div></div></div></div><div class="col-md-6"><div class="card border-0 shadow-sm"><div class="card-body"><div class="text-muted">Resolution SLA breaches</div><div class="display-6">{{ $resolution_breaches }}</div></div></div></div></div>
    <div class="card border-0 shadow-sm mb-4"><div class="card-header bg-white"><strong>Severity SLA policies</strong></div><div class="card-body"><form method="POST" action="{{ route('marketplaces.writes.sla.policy') }}" class="row g-2 align-items-end">@csrf
        <div class="col-md-3"><label class="form-label">Severity</label><select class="form-select" name="severity" required>@foreach(['LOW','MEDIUM','HIGH','CRITICAL'] as $s)<option value="{{ $s }}">{{ $s }}</option>@endforeach</select></div>
        <div class="col-md-3"><label class="form-label">Response target (minutes)</label><input class="form-control" type="number" min="1" max="10080" name="response_minutes" value="60" required></div>
        <div class="col-md-3"><label class="form-label">Resolution target (minutes)</label><input class="form-control" type="number" min="1" max="43200" name="resolution_minutes" value="1440" required></div>
        <div class="col-md-3"><button class="btn btn-primary w-100">Save policy</button></div></form>
        @if($policies->count())<div class="table-responsive mt-3"><table class="table table-sm"><thead><tr><th>Severity</th><th>Response</th><th>Resolution</th><th>Enabled</th></tr></thead><tbody>@foreach($policies as $p)<tr><td>{{ $p->severity }}</td><td>{{ $p->response_minutes }} min</td><td>{{ $p->resolution_minutes }} min</td><td>{{ $p->enabled ? 'Yes' : 'No' }}</td></tr>@endforeach</tbody></table></div>@endif
    </div></div>
    <div class="card border-0 shadow-sm"><div class="card-header bg-white"><strong>Open incidents & SLA status</strong></div><div class="table-responsive"><table class="table table-hover mb-0"><thead><tr><th>Incident</th><th>Severity</th><th>Status</th><th>Response SLA</th><th>Resolution SLA</th><th>Action</th></tr></thead><tbody>
    @forelse($incidents as $i)<tr><td><strong>{{ $i->title }}</strong><div class="small text-muted">Attempt #{{ $i->attempt_id }} · {{ $i->created_at }}</div></td><td>{{ $i->severity }}</td><td>{{ $i->status }}</td><td>@if($i->response_breached)<span class="badge bg-danger">Breached</span>@elseif($i->response_due_at)<span class="badge bg-success">On track / responded</span>@else—@endif</td><td>@if($i->resolution_breached)<span class="badge bg-danger">Breached</span>@elseif($i->resolution_due_at)<span class="badge bg-success">On track</span>@else—@endif</td><td>@if(!$i->response_breached && $i->response_due_at)<form method="POST" action="{{ route('marketplaces.writes.sla.respond', $i->id) }}">@csrf<button class="btn btn-sm btn-outline-primary">Record response</button></form>@endif</td></tr>@empty<tr><td colspan="6" class="text-center text-muted py-4">No open incidents for this business.</td></tr>@endforelse
    </tbody></table></div></div>
</div>
@endsection
