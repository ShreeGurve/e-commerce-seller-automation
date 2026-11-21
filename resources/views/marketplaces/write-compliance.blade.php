@extends('layouts.app')

@section('content')
<div class="container-fluid py-3">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1">Marketplace Write Compliance</h1>
            <p class="text-muted mb-0">Read-only governance checks for marketplace write evidence and retention.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('marketplaces.writes.compliance.export') }}" class="btn btn-outline-secondary">Export CSV</a>
            <form method="POST" action="{{ route('marketplaces.writes.compliance.run') }}">
                @csrf
                <button class="btn btn-primary" type="submit">Run compliance check</button>
            </form>
        </div>
    </div>

    @if(session('success')) <div class="alert alert-success">{{ session('success') }}</div> @endif
    @if($errors->any()) <div class="alert alert-danger">{{ $errors->first() }}</div> @endif

    <div class="row g-3 mb-4">
        <div class="col-md-4"><div class="card h-100"><div class="card-body"><div class="text-muted small">Latest scan</div><div class="h5 mb-0">{{ $latestRun?->completed_at ? \Illuminate\Support\Carbon::parse($latestRun->completed_at)->format('d M Y, H:i') : 'Not run yet' }}</div></div></div></div>
        <div class="col-md-4"><div class="card h-100"><div class="card-body"><div class="text-muted small">Findings in latest scan</div><div class="h5 mb-0">{{ $latestRun->finding_count ?? 0 }}</div></div></div></div>
        <div class="col-md-4"><div class="card h-100"><div class="card-body"><div class="text-muted small">Open findings (all scans)</div><div class="h5 mb-0">{{ $openCount }}</div></div></div></div>
    </div>

    <div class="card mb-4">
        <div class="card-header bg-white"><strong>Latest compliance findings</strong></div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead><tr><th>Severity</th><th>Rule</th><th>Finding</th><th>Subject</th><th>Details</th><th>Detected</th></tr></thead>
                <tbody>
                @forelse($findings as $finding)
                    <tr>
                        <td><span class="badge {{ $finding->severity === 'CRITICAL' ? 'bg-danger' : ($finding->severity === 'WARNING' ? 'bg-warning text-dark' : 'bg-info text-dark') }}">{{ $finding->severity }}</span></td>
                        <td><code>{{ $finding->rule_key }}</code></td>
                        <td>{{ $finding->title }}</td>
                        <td>{{ $finding->subject_type }} @if($finding->subject_id) #{{ $finding->subject_id }} @endif</td>
                        <td class="text-muted">{{ $finding->details }}</td>
                        <td>{{ $finding->created_at ? \Illuminate\Support\Carbon::parse($finding->created_at)->format('d M Y H:i') : '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted py-4">No findings in the latest scan. Run a check to refresh the report.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer">{{ $findings->links() }}</div>
    </div>

    <div class="card">
        <div class="card-header bg-white"><strong>Recent scan history</strong></div>
        <div class="table-responsive"><table class="table mb-0"><thead><tr><th>Run</th><th>Completed</th><th>Findings</th><th>Critical</th><th>Warnings</th></tr></thead><tbody>
        @forelse($history as $run)
            <tr><td>#{{ $run->id }}</td><td>{{ $run->completed_at ? \Illuminate\Support\Carbon::parse($run->completed_at)->format('d M Y H:i') : 'In progress' }}</td><td>{{ $run->finding_count }}</td><td>{{ $run->critical_count }}</td><td>{{ $run->warning_count }}</td></tr>
        @empty <tr><td colspan="5" class="text-center text-muted py-3">No scan history yet.</td></tr> @endforelse
        </tbody></table></div>
    </div>
    <p class="small text-muted mt-3 mb-0">This monitor records findings only. It does not block marketplace writes, release legal holds, approve deletion, or physically delete evidence.</p>
</div>
@endsection
