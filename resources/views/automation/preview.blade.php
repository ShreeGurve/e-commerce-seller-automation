@extends('layouts.app')
@section('title', 'Automation Dry Run')
@section('subtitle', 'Read-only impact preview — no alerts, notifications or business records are created')
@section('content')
<div class="card mb-3">
    <div class="card-body d-flex flex-wrap justify-content-between gap-3">
        <div>
            <div class="small text-uppercase text-primary fw-semibold">Phase 55 · Dry Run</div>
            <h4 class="mb-1">{{ $rule->name }}</h4>
            <div class="text-muted">{{ \App\Models\AutomationRule::TRIGGERS[$rule->trigger_key] ?? $rule->trigger_key }} · Threshold: {{ $rule->threshold }}</div>
        </div>
        <div class="text-end"><div class="small text-muted">Preview generated</div><strong>{{ $previewedAt->format('d M Y, H:i:s') }}</strong></div>
    </div>
</div>
<div class="alert alert-info">This preview evaluates current data using the existing rule logic. It does not create alerts, send notifications, change inventory, update listings or contact external services.</div>
<div class="row g-3 mb-3">
    <div class="col-md-4"><div class="card card-body"><div class="text-muted small">Potential matches</div><div class="fs-3 fw-bold">{{ count($matches) }}</div></div></div>
    <div class="col-md-4"><div class="card card-body"><div class="text-muted small">Rule status</div><div class="fs-5 fw-semibold">{{ $rule->is_active ? 'Enabled' : 'Paused' }}</div></div></div>
    <div class="col-md-4"><div class="card card-body"><div class="text-muted small">Mode</div><div class="fs-5 fw-semibold">Read only</div></div></div>
</div>
<div class="card"><div class="table-responsive"><table class="table align-middle mb-0">
<thead><tr><th class="ps-3">Potential alert</th><th>Details</th><th>Severity</th><th>Source</th></tr></thead>
<tbody>
@forelse($matches as $match)
<tr><td class="ps-3 fw-semibold">{{ $match['title'] ?? 'Potential match' }}</td><td>{{ $match['message'] ?? '' }}</td><td><span class="badge text-bg-warning">{{ strtoupper($match['severity'] ?? 'unknown') }}</span></td><td class="small text-muted">{{ $match['key'] ?? '—' }}</td></tr>
@empty
<tr><td colspan="4" class="text-center text-muted py-5">No matching records were found for the current data.</td></tr>
@endforelse
</tbody></table></div></div>
<a href="{{ route('automation.index') }}" class="btn btn-outline-secondary mt-3">Back to automation</a>
@endsection
