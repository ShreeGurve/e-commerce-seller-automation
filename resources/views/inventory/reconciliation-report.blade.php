@extends('layouts.app')
@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3"><div><h1 class="h3 mb-1">Inventory Reconciliation Report</h1><p class="text-muted mb-0">Audit trail of count variances and approved stock corrections.</p></div>
        <a class="btn btn-outline-primary" href="{{ route('inventory.reconciliation-report.export', request()->query()) }}">Export CSV</a>
    </div>
    <div class="row g-3 mb-3">
        <div class="col-md-3"><div class="card card-body"><small class="text-muted">Pending</small><strong class="h4">{{ $summary['pending'] }}</strong></div></div>
        <div class="col-md-3"><div class="card card-body"><small class="text-muted">Applied</small><strong class="h4">{{ $summary['applied'] }}</strong></div></div>
        <div class="col-md-3"><div class="card card-body"><small class="text-muted">Rejected</small><strong class="h4">{{ $summary['rejected'] }}</strong></div></div>
        <div class="col-md-3"><div class="card card-body"><small class="text-muted">Net Applied Variance</small><strong class="h4">{{ $summary['net_variance'] > 0 ? '+' : '' }}{{ $summary['net_variance'] }}</strong></div></div>
    </div>
    <form class="row g-2 mb-3">
        <div class="col-md-2"><select name="status" class="form-select"><option value="">All statuses</option>@foreach(['pending','applied','rejected'] as $status)<option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>@endforeach</select></div>
        <div class="col-md-2"><input type="date" name="from" value="{{ request('from') }}" class="form-control"></div>
        <div class="col-md-2"><input type="date" name="to" value="{{ request('to') }}" class="form-control"></div>
        <div class="col-md-2"><button class="btn btn-primary">Filter</button> <a class="btn btn-outline-secondary" href="{{ route('inventory.reconciliation-report') }}">Reset</a></div>
    </form>
    <div class="card"><div class="table-responsive"><table class="table table-hover mb-0">
        <thead><tr><th>#</th><th>Product</th><th>System</th><th>Counted</th><th>Variance</th><th>Status</th><th>Requested / Reviewed</th><th>Dates</th></tr></thead>
        <tbody>@forelse($requests as $r)<tr>
            <td>{{ $r->id }}</td><td>{{ $r->product?->name }}<div class="small text-muted">{{ $r->product?->sku }}</div></td>
            <td>{{ $r->system_qty }}</td><td>{{ $r->counted_qty }}</td><td>{{ $r->variance > 0 ? '+' : '' }}{{ $r->variance }}</td>
            <td>{{ ucfirst($r->status) }}</td><td>{{ $r->requester?->name ?? '—' }} / {{ $r->reviewer?->name ?? '—' }}</td>
            <td>{{ $r->created_at?->format('d M Y') }}<div class="small text-muted">{{ $r->applied_at?->format('d M Y H:i') }}</div></td>
        </tr>@empty<tr><td colspan="8" class="text-center text-muted py-4">No matching records.</td></tr>@endforelse</tbody>
    </table></div></div>
    <div class="mt-3">{{ $requests->links() }}</div>
</div>
@endsection
