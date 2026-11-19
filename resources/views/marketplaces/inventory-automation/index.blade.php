@extends('layouts.app')

@section('content')
<div class="container-fluid py-3">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1">Inventory sync automation</h1>
            <p class="text-muted mb-0">Schedule read-only inventory sync, reconciliation and stock-risk alerts.</p>
        </div>
        <span class="badge bg-info text-dark">Read-only marketplace access</span>
    </div>
    @if(session('success')) <div class="alert alert-success">{{ session('success') }}</div> @endif
    @if($errors->any()) <div class="alert alert-danger">{{ $errors->first() }}</div> @endif

    <div class="card mb-4">
        <div class="card-header fw-semibold">Connected marketplace schedules</div>
        <div class="card-body p-0">
            @forelse($connections as $connection)
                @php($setting = $settings->get($connection->id))
                <form method="POST" action="{{ route('marketplaces.inventory-automation.update', $connection) }}" class="border-bottom p-3">
                    @csrf
                    <div class="row g-3 align-items-end">
                        <div class="col-lg-3">
                            <div class="fw-semibold">{{ $connection->marketplace->name ?? 'Marketplace' }}</div>
                            <div class="small text-muted">{{ $connection->account_label }} · {{ ucfirst($connection->status) }}</div>
                        </div>
                        <div class="col-sm-4 col-lg-2">
                            <label class="form-label">Interval (hours)</label>
                            <input type="number" name="interval_hours" min="1" max="168" class="form-control" value="{{ old('interval_hours', $setting->interval_hours ?? 6) }}" required>
                        </div>
                        <div class="col-sm-4 col-lg-2">
                            <label class="form-label">Low-stock threshold</label>
                            <input type="number" name="low_stock_threshold" min="0" max="1000000" class="form-control" value="{{ old('low_stock_threshold', $setting->low_stock_threshold ?? 5) }}" required>
                        </div>
                        <div class="col-sm-4 col-lg-3">
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" name="enabled" value="1" id="auto-{{ $connection->id }}" @checked(old('enabled', $setting->enabled ?? false))>
                                <label class="form-check-label" for="auto-{{ $connection->id }}">Enable scheduled sync</label>
                            </div>
                            <div class="small text-muted">Next: {{ $setting?->next_run_at?->format('d M Y H:i') ?? 'Not scheduled' }}</div>
                        </div>
                        <div class="col-lg-2"><button class="btn btn-primary w-100">Save schedule</button></div>
                    </div>
                </form>
            @empty
                <div class="p-3 text-muted">No marketplace connections found for this business.</div>
            @endforelse
        </div>
    </div>

    <div class="card">
        <div class="card-header fw-semibold">Open inventory alerts</div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead><tr><th>Severity</th><th>Alert</th><th>Marketplace</th><th>SKU</th><th>Details</th><th>Last seen</th><th></th></tr></thead>
                <tbody>
                @forelse($alerts as $alert)
                    <tr>
                        <td><span class="badge {{ $alert->severity === 'critical' ? 'bg-danger' : 'bg-warning text-dark' }}">{{ strtoupper($alert->severity) }}</span></td>
                        <td>{{ str_replace('_', ' ', $alert->type) }}</td>
                        <td>{{ $alert->connection->marketplace->name ?? 'Marketplace' }}</td>
                        <td>{{ $alert->marketplace_sku ?? '—' }}</td>
                        <td class="small">{{ collect($alert->payload ?? [])->map(fn($v, $k) => $k.': '.(is_scalar($v) ? $v : json_encode($v)))->implode(' · ') }}</td>
                        <td>{{ $alert->last_seen_at?->format('d M Y H:i') }}</td>
                        <td>
                            @if($alert->status === 'OPEN')
                                <form method="POST" action="{{ route('marketplaces.inventory-automation.alerts.acknowledge', $alert) }}">@csrf<button class="btn btn-sm btn-outline-secondary">Acknowledge</button></form>
                            @else <span class="small text-muted">Acknowledged</span> @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-muted py-4">No open inventory alerts.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-body">{{ $alerts->links() }}</div>
    </div>
</div>
@endsection
