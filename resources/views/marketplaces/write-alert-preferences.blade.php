@extends('layouts.app')

@section('content')
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div><h1 class="h3 mb-1">Marketplace Write Alert Preferences</h1><p class="text-muted mb-0">Choose which governance alerts appear in your notification center.</p></div>
        <a href="{{ route('marketplaces.writes.alerts.index') }}" class="btn btn-outline-secondary">Back to alerts</a>
    </div>
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    <form method="POST" action="{{ route('marketplaces.writes.alerts.preferences.update') }}" class="card card-body">
        @csrf
        <h2 class="h5">Alert categories</h2>
        @foreach(['attempt_alerts' => 'Marketplace write attempt alerts', 'incident_alerts' => 'High-priority incident alerts'] as $field => $label)
            <div class="form-check form-switch mb-3"><input class="form-check-input" type="checkbox" id="{{ $field }}" name="{{ $field }}" value="1" @checked($preferences->$field)><label class="form-check-label" for="{{ $field }}">{{ $label }}</label></div>
        @endforeach
        <hr><h2 class="h5">Severity filters</h2>
        @foreach(['critical_alerts' => 'Critical', 'high_alerts' => 'High', 'warning_alerts' => 'Warning'] as $field => $label)
            <div class="form-check form-switch mb-3"><input class="form-check-input" type="checkbox" id="{{ $field }}" name="{{ $field }}" value="1" @checked($preferences->$field)><label class="form-check-label" for="{{ $field }}">{{ $label }} alerts</label></div>
        @endforeach
        <hr>
        <div class="mb-3"><label for="stale_after_minutes" class="form-label">Alert when a submission remains in progress for (minutes)</label><input class="form-control" type="number" min="5" max="1440" required id="stale_after_minutes" name="stale_after_minutes" value="{{ $preferences->stale_after_minutes }}"><div class="form-text">Allowed range: 5–1440 minutes. Applies to SUBMITTING attempts only.</div></div>
        <button class="btn btn-primary align-self-start" type="submit">Save preferences</button>
    </form>
</div>
@endsection
