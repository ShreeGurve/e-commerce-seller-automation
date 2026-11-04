@extends('layouts.app')
@section('title', 'Inventory Forecasting')
@section('subtitle', 'Demand forecasts and replenishment recommendations based on internal non-test sales and open purchase orders')
@section('content')
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div class="small text-muted">Forecasts are estimates, not guaranteed sales. Test orders are excluded. No stock is changed automatically.</div>
        <a href="{{ route('reorders.index') }}" class="btn btn-outline-primary btn-sm"><i class="bi bi-arrow-repeat"></i> Open Reorder Planning</a>
    </div>

    <form method="GET" action="{{ route('inventory-forecast.index') }}" class="card mb-3">
        <div class="card-body">
            <div class="row g-2 align-items-end">
                <div class="col-sm-6 col-lg-2"><label class="form-label">Sales lookback</label><select name="lookback_days" class="form-select"><option value="30" @selected($settings['lookback_days']===30)>30 days</option><option value="60" @selected($settings['lookback_days']===60)>60 days</option><option value="90" @selected($settings['lookback_days']===90)>90 days</option></select></div>
                <div class="col-sm-6 col-lg-2"><label class="form-label">Forecast horizon</label><select name="horizon_days" class="form-select"><option value="7" @selected($settings['horizon_days']===7)>7 days</option><option value="14" @selected($settings['horizon_days']===14)>14 days</option><option value="30" @selected($settings['horizon_days']===30)>30 days</option><option value="60" @selected($settings['horizon_days']===60)>60 days</option></select></div>
                <div class="col-sm-6 col-lg-2"><label class="form-label">Supplier lead time</label><input type="number" min="0" max="180" name="lead_time_days" class="form-control" value="{{ $settings['lead_time_days'] }}"></div>
                <div class="col-sm-6 col-lg-2"><label class="form-label">Safety stock days</label><input type="number" min="0" max="180" name="safety_stock_days" class="form-control" value="{{ $settings['safety_stock_days'] }}"></div>
                <div class="col-lg-2"><button class="btn btn-primary w-100"><i class="bi bi-calculator"></i> Calculate</button></div>
                <div class="col-lg-2"><a href="{{ route('inventory-forecast.index') }}" class="btn btn-outline-secondary w-100">Reset</a></div>
            </div>
        </div>
    </form>

    <div class="row g-3 mb-3">
        <div class="col-md-4"><div class="card h-100"><div class="card-body"><div class="small text-muted">Critical stock-out risk</div><div class="fs-3 fw-bold text-danger">{{ $criticalCount }}</div><div class="small text-muted">Expected to run out within lead time</div></div></div></div>
        <div class="col-md-4"><div class="card h-100"><div class="card-body"><div class="small text-muted">Products needing replenishment</div><div class="fs-3 fw-bold">{{ $reorderCount }}</div><div class="small text-muted">Based on lead time + safety stock coverage</div></div></div></div>
        <div class="col-md-4"><div class="card h-100"><div class="card-body"><div class="small text-muted">Suggested replenishment units</div><div class="fs-3 fw-bold">{{ number_format($totalSuggested) }}</div><div class="small text-muted">After subtracting outstanding ordered quantities</div></div></div></div>
    </div>

    <div class="card mb-3">
        <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2"><span class="fw-semibold">Demand forecast & stock cover</span><span class="small text-muted">Average daily demand = units sold ÷ {{ $settings['lookback_days'] }} days</span></div>
        <div class="table-responsive"><table class="table table-hover align-middle mb-0">
            <thead><tr><th>Product</th><th class="text-end">Sold</th><th class="text-end">Daily avg.</th><th class="text-end">Forecast</th><th class="text-end">Available</th><th class="text-end">Reserved</th><th class="text-end">On order</th><th>Est. stock-out</th><th class="text-end">Suggested qty</th><th>Status</th></tr></thead>
            <tbody>
            @forelse($rows as $row)
                <tr>
                    <td><a href="{{ route('inventory.show', $row->product) }}" class="text-decoration-none fw-semibold">{{ $row->product->sku }}</a><div class="small text-muted">{{ $row->product->name }}</div></td>
                    <td class="text-end">{{ number_format($row->units_sold) }}</td>
                    <td class="text-end">{{ number_format($row->daily_demand, 2) }}</td>
                    <td class="text-end">{{ number_format($row->predicted_demand) }}</td>
                    <td class="text-end">{{ number_format($row->available) }}</td>
                    <td class="text-end">{{ number_format($row->reserved) }}</td>
                    <td class="text-end">{{ number_format($row->on_order) }}</td>
                    <td>@if($row->stock_out_date)<div>{{ $row->stock_out_date }}</div><div class="small text-muted">{{ $row->stock_out_days }} days</div>@else<span class="text-muted">No sales basis</span>@endif</td>
                    <td class="text-end fw-semibold">{{ number_format($row->suggested) }}</td>
                    <td>@if($row->status==='CRITICAL')<span class="badge text-bg-danger">Critical</span>@elseif($row->status==='REORDER')<span class="badge text-bg-warning">Reorder</span>@elseif($row->status==='COVERED')<span class="badge text-bg-success">Covered</span>@else<span class="badge text-bg-secondary">No sales data</span>@endif</td>
                </tr>
            @empty
                <tr><td colspan="10" class="text-center text-muted py-4">No active products found.</td></tr>
            @endforelse
            </tbody>
        </table></div>
        <div class="card-footer d-flex flex-wrap justify-content-between align-items-center gap-2">
            <span class="small text-muted">Stock position = available − reserved + outstanding quantities on ORDERED purchase orders. Draft POs are excluded.</span>
            @can('purchases.edit')
                <form method="POST" action="{{ route('inventory-forecast.snapshot') }}" onsubmit="return confirm('Save this forecast as a snapshot for later accuracy comparison?')">
                    @csrf
                    @foreach($settings as $key=>$value)<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endforeach
                    <button class="btn btn-outline-primary btn-sm"><i class="bi bi-camera"></i> Save forecast snapshot</button>
                </form>
            @endcan
        </div>
    </div>

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center"><span class="fw-semibold">Forecast accuracy history</span>@if($averageAccuracy!==null)<span class="badge text-bg-info">Average accuracy: {{ $averageAccuracy }}%</span>@endif</div>
        @if($accuracyRows->isEmpty())
            <div class="card-body text-muted small">No completed forecast snapshots yet. Save a forecast snapshot; accuracy will appear after its forecast period ends.</div>
        @else
            <div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th>Snapshot date</th><th>Product</th><th>Forecast period</th><th class="text-end">Predicted</th><th class="text-end">Actual</th><th class="text-end">Accuracy</th></tr></thead><tbody>
                @foreach($accuracyRows as $entry)
                    <tr><td>{{ $entry->snapshot->forecast_date->toDateString() }}</td><td>{{ $entry->snapshot->product?->sku ?? 'Deleted product' }}<div class="small text-muted">{{ $entry->snapshot->product?->name }}</div></td><td>{{ $entry->snapshot->forecast_start_date->toDateString() }} – {{ $entry->snapshot->forecast_end_date->toDateString() }}</td><td class="text-end">{{ number_format($entry->snapshot->predicted_demand) }}</td><td class="text-end">{{ number_format($entry->actual) }}</td><td class="text-end fw-semibold">{{ number_format($entry->accuracy, 1) }}%</td></tr>
                @endforeach
            </tbody></table></div>
        @endif
        <div class="card-footer small text-muted">Accuracy uses 100 − absolute forecast error ÷ actual units. If actual demand is zero, accuracy is 0% when units were forecast and 100% when zero units were forecast. Only non-test orders in eligible sales statuses are counted.</div>
    </div>
@endsection
