@extends('layouts.app')
@section('title', 'Reports')
@section('subtitle', 'Every report can be filtered, exported to Excel or CSV, and printed')
@section('content')
    <div class="card border-primary mb-4">
        <div class="card-body d-flex flex-wrap align-items-center gap-3">
            <div class="sg-tile t-blue"><i class="bi bi-sunrise"></i></div>
            <div class="flex-grow-1">
                <div class="fw-bold fs-5">Daily business report</div>
                <div class="text-muted small">One page for any day: sales, costs, profit, top and bottom products, low stock,
                    high ACOS, high returns, claims, settlement issues and alerts.</div>
            </div>
            <a href="{{ route('reports.daily') }}" class="btn btn-primary">Open yesterday's report</a>
        </div>
    </div>
    @foreach ($groups as $group => $keys)
        <h6 class="fw-bold text-muted text-uppercase small mt-4 mb-2">{{ $group }}</h6>
        <div class="row g-3">
            @foreach ($keys as $k)
                <div class="col-md-6 col-xl-4"><a href="{{ route('reports.show', $k) }}" class="text-decoration-none">
                        <div class="card h-100">
                            <div class="card-body">
                                <div class="fw-bold">{{ $catalog[$k]['title'] }}</div>
                                <div class="small text-muted mt-1">{{ $catalog[$k]['desc'] }}</div>
                            </div>
                        </div>
                    </a></div>
            @endforeach
        </div>
    @endforeach
@endsection
