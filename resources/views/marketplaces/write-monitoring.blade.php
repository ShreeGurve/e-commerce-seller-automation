@extends('layouts.app')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <h1 class="h3 mb-1">Marketplace Write Execution Monitor</h1>
            <p class="text-muted mb-0">Track uncertain, failed, and stalled marketplace submissions. No automatic retries are performed.</p>
        </div>
        <form method="POST" action="{{ route('marketplaces.writes.monitoring.scan') }}">
            @csrf
            <button class="btn btn-primary" type="submit">Run Compliance Scan</button>
        </form>
    </div>

    @if(session('success')) <div class="alert alert-success">{{ session('success') }}</div> @endif
    @if($errors->any())
        <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif

    <div class="row g-3 mb-4">
        @foreach(['total' => 'Total incidents', 'open' => 'Open', 'in_progress' => 'In progress', 'resolved' => 'Resolved'] as $key => $label)
            <div class="col-6 col-xl-3">
                <div class="card h-100"><div class="card-body">
                    <div class="text-muted small">{{ $label }}</div>
                    <div class="h3 mb-0">{{ number_format((int) ($counts->$key ?? 0)) }}</div>
                </div></div>
            </div>
        @endforeach
    </div>

    <div class="card">
        <div class="card-header bg-white"><strong>Execution incidents</strong></div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead><tr><th>Incident</th><th>Marketplace</th><th>Operation</th><th>SKU</th><th>Attempt</th><th>Severity</th><th>Status</th><th>Created</th><th>Action</th></tr></thead>
                <tbody>
                @forelse($incidents as $incident)
                    <tr>
                        <td><strong>#{{ $incident->id }}</strong><div class="small text-muted">{{ $incident->title }}</div></td>
                        <td>{{ strtoupper($incident->marketplace_code) }}</td>
                        <td>{{ $incident->operation }}</td>
                        <td>{{ $incident->marketplace_sku }}</td>
                        <td><span class="badge bg-secondary">{{ $incident->attempt_status }}</span></td>
                        <td>{{ $incident->severity }}</td>
                        <td>{{ str_replace('_', ' ', $incident->status) }}</td>
                        <td>{{ $incident->created_at }}</td>
                        <td style="min-width:230px">
                            @if($incident->status === 'OPEN')
                                <form method="POST" action="{{ route('marketplaces.writes.monitoring.acknowledge', $incident->id) }}" class="mb-2">
                                    @csrf
                                    <input class="form-control form-control-sm mb-1" name="note" minlength="5" maxlength="2000" required placeholder="Investigation note">
                                    <button class="btn btn-sm btn-outline-primary">Acknowledge</button>
                                </form>
                            @elseif($incident->status === 'IN_PROGRESS')
                                <form method="POST" action="{{ route('marketplaces.writes.monitoring.resolve', $incident->id) }}">
                                    @csrf
                                    <input class="form-control form-control-sm mb-1" name="note" minlength="10" maxlength="2000" required placeholder="Resolution and marketplace verification">
                                    <button class="btn btn-sm btn-outline-success">Resolve incident</button>
                                </form>
                            @else
                                <span class="text-muted small">Closed</span>
                            @endif
                        </td>
                    </tr>
                    @if($incident->description)
                        <tr class="table-light"><td colspan="9" class="small text-muted">{{ $incident->description }}</td></tr>
                    @endif
                @empty
                    <tr><td colspan="9" class="text-center text-muted py-4">No execution incidents detected. Run a scan to check recent write attempts.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-body">{{ $incidents->links() }}</div>
    </div>
</div>
@endsection
