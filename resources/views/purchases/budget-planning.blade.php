@extends('layouts.app')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <h3 class="mb-1">Purchase Budget & Procurement Planning</h3>
            <p class="text-muted mb-0">Set monthly procurement limits and compare them with received purchase orders.</p>
        </div>
        <a href="{{ route('purchase-budgets.export') }}" class="btn btn-outline-primary">Export budgets CSV</a>
    </div>

    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('purchase-budgets.index') }}" class="row g-2 align-items-end">
                <div class="col-md-4">
                    <label class="form-label">Planning month</label>
                    <input type="month" name="month" value="{{ $selectedMonth }}" class="form-control">
                </div>
                <div class="col-md-2"><button class="btn btn-primary">View month</button></div>
            </form>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-xl-3 col-md-6"><div class="card border-0 shadow-sm h-100"><div class="card-body"><div class="text-muted small">Monthly budget</div><div class="fs-3 fw-semibold">{{ number_format($budgetAmount, 2) }}</div></div></div></div>
        <div class="col-xl-3 col-md-6"><div class="card border-0 shadow-sm h-100"><div class="card-body"><div class="text-muted small">Actual received purchases</div><div class="fs-3 fw-semibold">{{ number_format($actualSpend, 2) }}</div></div></div></div>
        <div class="col-xl-3 col-md-6"><div class="card border-0 shadow-sm h-100"><div class="card-body"><div class="text-muted small">Budget remaining</div><div class="fs-3 fw-semibold {{ $variance < 0 ? 'text-danger' : 'text-success' }}">{{ number_format($variance, 2) }}</div></div></div></div>
        <div class="col-xl-3 col-md-6"><div class="card border-0 shadow-sm h-100"><div class="card-body"><div class="text-muted small">3-month average forecast</div><div class="fs-3 fw-semibold">{{ number_format($forecast, 2) }}</div></div></div></div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100"><div class="card-body">
                <h5 class="mb-3">Set monthly budget</h5>
                <form method="POST" action="{{ route('purchase-budgets.store') }}">
                    @csrf
                    <div class="mb-3"><label class="form-label">Budget month</label><input type="month" name="budget_month" value="{{ $selectedMonth }}" class="form-control" required></div>
                    <div class="mb-3"><label class="form-label">Budget amount</label><input type="number" name="budget_amount" min="0.01" step="0.01" value="{{ old('budget_amount', $budgetAmount ?: '') }}" class="form-control" required></div>
                    <div class="mb-3"><label class="form-label">Notes</label><textarea name="notes" rows="3" class="form-control">{{ old('notes', $budget?->notes) }}</textarea></div>
                    <button class="btn btn-primary">Save monthly budget</button>
                </form>
                @if($errors->any())<div class="alert alert-danger mt-3 mb-0">@foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>@endif
            </div></div>
        </div>
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100"><div class="card-body">
                <h5 class="mb-3">Budget performance</h5>
                <div class="d-flex justify-content-between mb-2"><span class="text-muted">Budget utilization</span><strong>{{ $utilization === null ? 'Budget not set' : number_format($utilization, 1).'%' }}</strong></div>
                @if($utilization !== null)
                    <div class="progress mb-3" style="height:12px"><div class="progress-bar {{ $utilization > 100 ? 'bg-danger' : 'bg-primary' }}" role="progressbar" style="width: {{ min(100, max(0, $utilization)) }}%"></div></div>
                @endif
                <div class="d-flex justify-content-between py-2 border-bottom"><span>Actual vs budget</span><strong class="{{ $variance < 0 ? 'text-danger' : 'text-success' }}">{{ $variance < 0 ? 'Over budget' : 'Within budget' }}</strong></div>
                <div class="d-flex justify-content-between py-2"><span>Forecast vs budget</span><strong class="{{ $projectedVariance < 0 ? 'text-danger' : 'text-success' }}">{{ number_format($projectedVariance, 2) }}</strong></div>
                <p class="small text-muted mt-3 mb-0">Forecast is the average of the three previous calendar months' received purchase totals. It is a simple planning estimate, not a committed spend forecast.</p>
            </div></div>
        </div>
    </div>

    <div class="card border-0 shadow-sm"><div class="card-body">
        <h5 class="mb-3">Previous three months</h5>
        <div class="table-responsive"><table class="table align-middle mb-0">
            <thead><tr><th>Month</th><th class="text-end">Received purchase total</th></tr></thead>
            <tbody>@foreach($history as $item)<tr><td>{{ \Illuminate\Support\Carbon::parse($item['month'])->format('F Y') }}</td><td class="text-end">{{ number_format($item['amount'], 2) }}</td></tr>@endforeach</tbody>
        </table></div>
    </div></div>
</div>
@endsection
