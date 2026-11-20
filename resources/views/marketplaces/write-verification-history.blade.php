@extends('layouts.app')

@section('content')
<div class="container-fluid py-3">
    <div class="d-flex align-items-center justify-content-between gap-2 mb-3">
        <div><h1 class="h4 mb-1">Verification History — Attempt #{{ $attempt }}</h1><div class="small text-muted">Append-only record of manual marketplace outcome checks.</div></div>
        <a href="{{ route('marketplaces.writes.verification.index') }}" class="btn btn-outline-secondary btn-sm">Back to verification</a>
    </div>
    <div class="card border-0 shadow-sm"><div class="table-responsive"><table class="table align-middle mb-0">
        <thead><tr><th>Recorded at</th><th>From → To</th><th>Recorded by</th><th>Issue / note</th><th>Evidence</th></tr></thead>
        <tbody>
        @forelse ($events as $event)
            <tr><td>{{ $event->recorded_at }}</td><td>{{ $event->from_status ? str_replace('_', ' ', $event->from_status) : 'First check' }} → <strong>{{ str_replace('_', ' ', $event->to_status) }}</strong></td><td>{{ $event->recorded_by_name ?? '—' }}</td><td>@if($event->issue_code)<div class="small fw-semibold">{{ $event->issue_code }}</div>@endif{{ $event->verification_note }}</td><td>@if($event->evidence_url)<a href="{{ $event->evidence_url }}" target="_blank" rel="noopener noreferrer">Open evidence</a>@else — @endif</td></tr>
        @empty
            <tr><td colspan="5" class="text-center text-muted py-4">No verification events recorded.</td></tr>
        @endforelse
        </tbody>
    </table></div></div>
</div>
@endsection
