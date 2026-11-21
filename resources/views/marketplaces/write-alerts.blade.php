@extends('layouts.app')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <h1 class="h3 mb-1">Marketplace Write Alerts</h1>
            <p class="text-muted mb-0">Governance alerts for failed, uncertain and high-priority marketplace write activity.</p>
        </div>
        <div class="d-flex align-items-center gap-2">
            <span class="badge bg-danger">{{ $unreadCount }} unread</span>
            @if($unreadCount > 0)
                <form method="POST" action="{{ route('marketplaces.writes.alerts.read-all') }}">
                    @csrf
                    <button class="btn btn-outline-secondary btn-sm" type="submit">Mark all read</button>
                </form>
            @endif
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    @forelse($notifications as $notification)
        @php($data = $notification->data)
        @php($severity = strtolower($data['severity'] ?? 'warning'))
        <div class="card mb-3 {{ $notification->read_at ? 'border-light' : 'border-primary' }}">
            <div class="card-body d-flex flex-wrap justify-content-between gap-3">
                <div class="flex-grow-1">
                    <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                        <span class="badge bg-{{ in_array($severity, ['critical', 'high'], true) ? 'danger' : 'warning' }}">{{ strtoupper($severity) }}</span>
                        @if(!$notification->read_at)<span class="badge bg-primary">NEW</span>@endif
                        <small class="text-muted">{{ $notification->created_at?->format('d M Y, h:i A') }}</small>
                    </div>
                    <h2 class="h6 mb-1">{{ $data['title'] ?? 'Marketplace write alert' }}</h2>
                    <p class="mb-0 text-muted">{{ $data['message'] ?? '' }}</p>
                    @if(!empty($data['attempt_id']))<small class="text-muted">Attempt #{{ $data['attempt_id'] }}</small>@endif
                    @if(!empty($data['incident_id']))<small class="text-muted">Incident #{{ $data['incident_id'] }}</small>@endif
                </div>
                <div class="d-flex align-items-center gap-2">
                    <a class="btn btn-outline-primary btn-sm" href="{{ $data['url'] ?? url('/marketplace-writes/monitoring') }}">Open monitoring</a>
                    @if(!$notification->read_at)
                        <form method="POST" action="{{ route('marketplaces.writes.alerts.read', $notification->id) }}">
                            @csrf
                            <button class="btn btn-light btn-sm" type="submit">Mark read</button>
                        </form>
                    @endif
                </div>
            </div>
        </div>
    @empty
        <div class="card"><div class="card-body text-center py-5">
            <h2 class="h5">No marketplace write alerts</h2>
            <p class="text-muted mb-0">New alerts will appear here after the scheduled scan runs.</p>
        </div></div>
    @endforelse
</div>
@endsection
