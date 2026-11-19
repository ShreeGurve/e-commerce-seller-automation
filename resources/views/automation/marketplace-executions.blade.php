@extends('layouts.app')

@section('content')
<div class="container-fluid py-3">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h3 mb-1">Automation Execution Center</h1>
            <p class="text-muted mb-0">Validate approved recommendations before any operational action.</p>
        </div>
        <span class="badge bg-warning text-dark px-3 py-2">LIVE EXECUTION DISABLED</span>
    </div>

    @if(session('success')) <div class="alert alert-success">{{ session('success') }}</div> @endif
    @if(session('warning')) <div class="alert alert-warning">{{ session('warning') }}</div> @endif

    <div class="alert alert-info">
        <strong>Safe mode:</strong> Dry runs create an execution preview only. They do not call marketplace APIs or change inventory, prices, listings, orders, refunds, payouts or accounting data.
    </div>

    <div class="card mb-4">
        <div class="card-header bg-white"><h2 class="h5 mb-0">Approved tasks ready for dry run</h2></div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead><tr><th>Task</th><th>Type</th><th>Priority</th><th>Approved task status</th><th class="text-end">Action</th></tr></thead>
                <tbody>
                @forelse($tasks as $task)
                    <tr>
                        <td><div class="fw-semibold">{{ $task->title }}</div><small class="text-muted">{{ \Illuminate\Support\Str::limit($task->reason, 110) }}</small></td>
                        <td>{{ str_replace('_', ' ', $task->recommendation_type) }}</td>
                        <td>{{ $task->priority }}</td>
                        <td><span class="badge bg-success">{{ $task->status }}</span></td>
                        <td class="text-end">
                            <form method="POST" action="{{ route('automation.marketplace-executions.dry-run', $task->id) }}">
                                @csrf
                                <button class="btn btn-sm btn-primary" type="submit">Run dry run</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center text-muted py-4">No approved tasks are waiting for a dry run.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header bg-white"><h2 class="h5 mb-0">Execution policies</h2></div>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead><tr><th>Recommendation type</th><th>Dry run</th><th>Live mode</th><th>Approval required</th><th>Policy note</th></tr></thead>
                <tbody>@foreach($policies as $policy)
                    <tr><td>{{ str_replace('_', ' ', $policy->recommendation_type) }}</td><td>{{ $policy->dry_run_enabled ? 'Enabled' : 'Disabled' }}</td><td><span class="text-danger fw-semibold">Disabled</span></td><td>{{ $policy->requires_approval ? 'Yes' : 'No' }}</td><td class="text-muted">{{ $policy->policy_note }}</td></tr>
                @endforeach</tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <div class="card-header bg-white"><h2 class="h5 mb-0">Execution history</h2></div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead><tr><th>When</th><th>Task</th><th>Mode</th><th>Status</th><th>Initiated by</th><th>Message</th></tr></thead>
                <tbody>@forelse($executions as $execution)
                    <tr><td>{{ $execution->created_at }}</td><td>{{ $execution->task_title }}</td><td>{{ $execution->mode }}</td><td><span class="badge {{ $execution->status === 'SUCCEEDED' ? 'bg-success' : ($execution->status === 'BLOCKED' ? 'bg-warning text-dark' : 'bg-secondary') }}">{{ $execution->status }}</span></td><td>{{ $execution->initiated_by_name ?? 'System' }}</td><td>{{ $execution->message }}</td></tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted py-4">No execution attempts recorded yet.</td></tr>
                @endforelse</tbody>
            </table>
        </div>
    </div>
</div>
@endsection
