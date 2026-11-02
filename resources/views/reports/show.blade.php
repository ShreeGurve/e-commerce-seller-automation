@extends('layouts.app')
@section('title', $data['title'])
@section('subtitle', $data['desc'])
@section('content')
    @php
        $has = fn($x) => in_array($x, $data['filters'], true);
        $q = fn($a) => route('reports.show', [$data['key']]) .
            '?' .
            http_build_query(array_filter(array_merge(request()->query(), $a), fn($v) => $v !== null && $v !== ''));
        $presets = [
            '7 days' => [now()->subDays(6)->toDateString(), now()->toDateString()],
            '30 days' => [now()->subDays(29)->toDateString(), now()->toDateString()],
            'This month' => [now()->startOfMonth()->toDateString(), now()->toDateString()],
            'Last month' => [
                now()->subMonthNoOverflow()->startOfMonth()->toDateString(),
                now()->subMonthNoOverflow()->endOfMonth()->toDateString(),
            ],
            'This year' => [now()->startOfYear()->toDateString(), now()->toDateString()],
        ];
    @endphp
    <div class="d-flex flex-wrap gap-2 mb-3 no-print">
        <a href="{{ route('reports.index') }}" class="btn btn-sm btn-outline-secondary">&larr; All reports</a>
        <div class="ms-auto d-flex flex-wrap gap-2">
            @can('reports.export')
                <a href="{{ route('reports.export', [$data['key'], 'xlsx']) }}?{{ http_build_query(request()->query()) }}"
                    class="btn btn-sm btn-success"><i class="bi bi-file-earmark-excel"></i> Excel</a>
                <a href="{{ route('reports.export', [$data['key'], 'csv']) }}?{{ http_build_query(request()->query()) }}"
                    class="btn btn-sm btn-outline-success"><i class="bi bi-filetype-csv"></i> CSV</a>
            @endcan
            <a href="{{ route('reports.print', $data['key']) }}?{{ http_build_query(request()->query()) }}" target="_blank"
                class="btn btn-sm btn-outline-primary"><i class="bi bi-printer"></i> Print / PDF</a>
        </div>
    </div>
    <div class="card mb-3 no-print">
        <div class="card-body">
            @if ($has('date'))
                <div class="d-flex flex-wrap gap-1 mb-2">
                    @foreach ($presets as $l => [$a, $b])
                        <a href="{{ $q(['from' => $a, 'to' => $b]) }}"
                            class="btn btn-sm btn-{{ $f['from'] === $a && $f['to'] === $b ? 'primary' : 'outline-secondary' }}">{{ $l }}</a>
                    @endforeach
                </div>
            @endif
            <form class="row g-2" method="GET">
                @if ($has('date'))
                    <div class="col-6 col-md-2"><input type="date" name="from" value="{{ $f['from'] }}"
                            class="form-control"></div>
                    <div class="col-6 col-md-2"><input type="date" name="to" value="{{ $f['to'] }}"
                            class="form-control"></div>
                @endif
                @if ($has('marketplace'))
                    <div class="col-md-2"><select name="marketplace_id" class="form-select">
                            <option value="">All channels</option>
                            @foreach ($marketplaces as $m)
                                <option value="{{ $m->id }}" @selected($f['marketplace_id'] === $m->id)>{{ $m->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                @endif
                @if ($has('product'))
                    <div class="col-md-3"><select name="product_id" class="form-select">
                            <option value="">All products</option>
                            @foreach ($products as $p)
                                <option value="{{ $p->id }}" @selected($f['product_id'] === $p->id)>{{ $p->sku }} —
                                    {{ \Illuminate\Support\Str::limit($p->name, 28) }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif
                @if ($has('category'))
                    <div class="col-md-2"><select name="category_id" class="form-select">
                            <option value="">All categories</option>
                            @foreach ($categories as $c)
                                <option value="{{ $c->id }}" @selected($f['category_id'] === $c->id)>{{ $c->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                @endif
                @if ($has('sort'))
                    <div class="col-md-2"><select name="sort" class="form-select">
                            @foreach ($sorts as $k => $l)
                                <option value="{{ $k }}" @selected($f['sort'] === $k)>{{ $l }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                @endif
                <div class="col-md-1"><button class="btn btn-outline-primary">Apply</button></div>
            </form>
        </div>
    </div>
    <div class="card">
        <div class="table-responsive">@include('reports._table', ['rows' => $shown])</div>
        <div class="card-footer small text-muted">{{ number_format($total) }} row(s).@if ($total > $limit)
                Showing the first {{ $limit }} here; Excel / CSV contain all of them.
                @endif @if ($data['note'])
                    <div class="mt-1">{{ $data['note'] }}</div>
                @endif
        </div>
    </div>
@endsection
