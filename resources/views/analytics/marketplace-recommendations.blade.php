@extends('layouts.app')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <h1 class="h3 mb-1">Marketplace Recommendations</h1>
            <p class="text-muted mb-0">Explainable operational insights based on your existing sales, stock and returns data.</p>
        </div>
        <span class="badge bg-info-subtle text-info">Read-only insights</span>
    </div>

    <form method="GET" class="card card-body mb-4">
        <div class="row g-3 align-items-end">
            <div class="col-md-3"><label class="form-label">Analysis period</label><select name="days" class="form-select">
                @foreach([7,14,30,60,90] as $days)<option value="{{ $days }}" @selected((int)request('days',30)===$days)>{{ $days }} days</option>@endforeach
            </select></div>
            <div class="col-md-3"><label class="form-label">Low stock cover (days)</label><input type="number" min="1" max="60" name="low_stock_cover" class="form-control" value="{{ request('low_stock_cover',7) }}"></div>
            <div class="col-md-3"><label class="form-label">High return rate threshold (%)</label><input type="number" min="0" max="100" step="0.1" name="return_threshold" class="form-control" value="{{ request('return_threshold',5) }}"></div>
            <div class="col-md-3"><button class="btn btn-primary w-100">Refresh recommendations</button></div>
        </div>
    </form>

    <div class="row g-3 mb-4">
        @foreach([['Total recommendations',$report['counts']['total'],'primary'],['High priority',$report['counts']['high'],'danger'],['Medium priority',$report['counts']['medium'],'warning'],['Stock actions',$report['counts']['restock'],'info']] as [$label,$value,$color])
            <div class="col-6 col-xl-3"><div class="card h-100 border-0 shadow-sm"><div class="card-body"><div class="text-muted small">{{ $label }}</div><div class="display-6 fw-semibold text-{{ $color }}">{{ $value }}</div></div></div></div>
        @endforeach
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white py-3"><strong>Recommended actions</strong><span class="text-muted small ms-2">{{ $report['period']['from'] }} to {{ $report['period']['to'] }}</span></div>
        <div class="card-body p-0">
            @forelse($report['recommendations'] as $item)
                <div class="p-3 border-bottom">
                    <div class="d-flex flex-wrap justify-content-between gap-2 mb-2">
                        <div><strong>{{ $item['product_name'] }}</strong> <span class="text-muted small">· {{ $item['sku'] }}</span></div>
                        <div class="d-flex gap-2"><span class="badge bg-{{ $item['priority']==='HIGH'?'danger':'warning' }}-subtle text-{{ $item['priority']==='HIGH'?'danger':'warning' }}">{{ $item['priority'] }}</span><span class="badge bg-secondary-subtle text-secondary">{{ str_replace('_',' ',$item['type']) }}</span></div>
                    </div>
                    <p class="mb-1">{{ $item['reason'] }}</p>
                    <div class="small text-primary"><strong>Suggested action:</strong> {{ $item['suggested_action'] }}</div>
                </div>
            @empty
                <div class="text-center text-muted py-5"><div class="h5">No recommendations found</div><p class="mb-0">There are no matching risk signals for the selected period and thresholds.</p></div>
            @endforelse
        </div>
    </div>
    <p class="text-muted small mt-3 mb-0">Recommendations are informational only. They do not change stock, prices, listings, orders or financial records. Sales-based signals require available order-profit data.</p>
</div>
@endsection
