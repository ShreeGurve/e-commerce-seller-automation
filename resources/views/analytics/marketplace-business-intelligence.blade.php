@extends('layouts.app')

@section('content')
<div class="container-fluid py-3">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h3 class="mb-1">Marketplace Business Intelligence</h3>
            <div class="text-muted">Sales trends, product profitability and return performance</div>
        </div>
        <span class="badge bg-light text-dark border">Read-only analytics</span>
    </div>

    <form method="GET" class="card card-body mb-3">
        <div class="row g-2 align-items-end">
            <div class="col-md-2"><label class="form-label">From</label><input type="date" name="from" class="form-control" value="{{ $report['filters']['from'] }}"></div>
            <div class="col-md-2"><label class="form-label">To</label><input type="date" name="to" class="form-control" value="{{ $report['filters']['to'] }}"></div>
            <div class="col-md-3"><label class="form-label">Marketplace</label><select name="marketplace_id" class="form-select"><option value="">All marketplaces</option>@foreach($report['marketplaces'] as $marketplace)<option value="{{ $marketplace->id }}" @selected((string)$report['filters']['marketplace_id'] === (string)$marketplace->id)>{{ $marketplace->name }}</option>@endforeach</select></div>
            <div class="col-md-2"><label class="form-label">Low margin threshold (%)</label><input type="number" min="0" max="100" step="0.5" name="low_margin_threshold" class="form-control" value="{{ $report['filters']['low_margin_threshold'] }}"></div>
            <div class="col-md-2"><button class="btn btn-primary w-100">Apply filters</button></div>
        </div>
    </form>

    @php($totals = $report['totals'])
    <div class="row g-3 mb-3">
        @foreach([
            ['Orders', number_format((int)($totals->orders ?? 0))],
            ['Units sold', number_format((int)($totals->units ?? 0))],
            ['Revenue', '₹'.number_format((float)($totals->revenue ?? 0), 2)],
            ['Net profit', '₹'.number_format((float)($totals->net_profit ?? 0), 2)],
            ['Profit margin', number_format($report['profit_margin'], 2).'%'],
            ['Return rate', number_format($report['returns']['rate'], 2).'%'],
        ] as $metric)
            <div class="col-6 col-md-4 col-xl-2"><div class="card h-100"><div class="card-body"><div class="text-muted small">{{ $metric[0] }}</div><div class="fs-5 fw-semibold">{{ $metric[1] }}</div></div></div></div>
        @endforeach
    </div>

    <div class="row g-3 mb-3">
        <div class="col-xl-7"><div class="card h-100"><div class="card-header bg-white fw-semibold">Monthly sales & profit trend</div><div class="table-responsive"><table class="table table-sm table-hover mb-0"><thead><tr><th>Month</th><th class="text-end">Units</th><th class="text-end">Revenue</th><th class="text-end">Profit</th></tr></thead><tbody>
            @forelse($report['sales_trend'] as $row)<tr><td>{{ \Carbon\Carbon::parse($row['month'].'-01')->format('M Y') }}</td><td class="text-end">{{ number_format($row['units']) }}</td><td class="text-end">₹{{ number_format($row['revenue'],2) }}</td><td class="text-end">₹{{ number_format($row['profit'],2) }}</td></tr>@empty<tr><td colspan="4" class="text-center text-muted py-4">No sales data for this period.</td></tr>@endforelse
        </tbody></table></div></div></div>
        <div class="col-xl-5"><div class="card h-100"><div class="card-header bg-white fw-semibold">Performance coverage</div><div class="card-body"><div class="d-flex justify-content-between"><span>Actual profit coverage</span><strong>{{ number_format($report['actual_coverage'],1) }}%</strong></div><div class="progress mt-2 mb-3" style="height:8px"><div class="progress-bar" style="width:{{ min(100,$report['actual_coverage']) }}%"></div></div><div class="row text-center"><div class="col-6"><div class="text-muted small">Returns</div><div class="fs-4 fw-semibold">{{ number_format($report['returns']['count']) }}</div></div><div class="col-6"><div class="text-muted small">Returned units</div><div class="fs-4 fw-semibold">{{ number_format($report['returns']['units']) }}</div></div></div><p class="small text-muted mt-3 mb-0">Return rate = return-item expected quantity divided by sold units for the selected date range. It is a unit-based indicator, not a claim rate.</p></div></div></div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-xl-7"><div class="card h-100"><div class="card-header bg-white fw-semibold">Top products by profit</div><div class="table-responsive"><table class="table table-sm table-hover mb-0"><thead><tr><th>Product</th><th>SKU</th><th class="text-end">Units</th><th class="text-end">Revenue</th><th class="text-end">Profit</th><th class="text-end">Margin</th></tr></thead><tbody>
            @forelse($report['top_products'] as $product)<tr><td>{{ $product->product_name }}</td><td>{{ $product->sku }}</td><td class="text-end">{{ number_format($product->units) }}</td><td class="text-end">₹{{ number_format($product->revenue,2) }}</td><td class="text-end">₹{{ number_format($product->profit,2) }}</td><td class="text-end">{{ number_format($product->margin,2) }}%</td></tr>@empty<tr><td colspan="6" class="text-center text-muted py-4">No product profitability data.</td></tr>@endforelse
        </tbody></table></div></div></div>
        <div class="col-xl-5"><div class="card h-100"><div class="card-header bg-white fw-semibold">Low-margin products <span class="text-muted fw-normal">(below {{ number_format($report['filters']['low_margin_threshold'],1) }}%)</span></div><div class="table-responsive"><table class="table table-sm table-hover mb-0"><thead><tr><th>Product</th><th>SKU</th><th class="text-end">Margin</th></tr></thead><tbody>@forelse($report['low_margin_products'] as $product)<tr><td>{{ $product->product_name }}</td><td>{{ $product->sku }}</td><td class="text-end text-danger">{{ number_format($product->margin,2) }}%</td></tr>@empty<tr><td colspan="3" class="text-center text-muted py-4">No products below this threshold.</td></tr>@endforelse</tbody></table></div></div></div>
    </div>

    <div class="row g-3">
        <div class="col-xl-7"><div class="card"><div class="card-header bg-white fw-semibold">Marketplace comparison</div><div class="table-responsive"><table class="table table-sm mb-0"><thead><tr><th>Marketplace</th><th class="text-end">Orders</th><th class="text-end">Units</th><th class="text-end">Revenue</th><th class="text-end">Profit</th><th class="text-end">Margin</th></tr></thead><tbody>@forelse($report['by_marketplace'] as $row)<tr><td>{{ $row->marketplace_name }}</td><td class="text-end">{{ number_format($row->orders) }}</td><td class="text-end">{{ number_format($row->units) }}</td><td class="text-end">₹{{ number_format($row->revenue,2) }}</td><td class="text-end">₹{{ number_format($row->profit,2) }}</td><td class="text-end">{{ number_format($row->margin,2) }}%</td></tr>@empty<tr><td colspan="6" class="text-center text-muted py-4">No marketplace data.</td></tr>@endforelse</tbody></table></div></div></div>
        <div class="col-xl-5"><div class="card"><div class="card-header bg-white fw-semibold">Products with most returned units</div><div class="table-responsive"><table class="table table-sm mb-0"><thead><tr><th>Product</th><th>SKU</th><th class="text-end">Returned units</th></tr></thead><tbody>@forelse($report['return_by_product'] as $row)<tr><td>{{ $row->product_name }}</td><td>{{ $row->sku }}</td><td class="text-end">{{ number_format($row->returned_units) }}</td></tr>@empty<tr><td colspan="3" class="text-center text-muted py-4">No returned units in this period.</td></tr>@endforelse</tbody></table></div></div></div>
    </div>
    <div class="small text-muted mt-3">Profit uses existing order-profit records (actual profit when present, otherwise estimated profit). This dashboard is read-only and does not change prices, inventory, returns or accounting records.</div>
</div>
@endsection
