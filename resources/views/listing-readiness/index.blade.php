@extends('layouts.app')
@section('title', 'Marketplace Listing Readiness')
@section('subtitle', 'Internal preflight checks before you manually publish listing content')

@section('content')
    @php
        $statusMeta = [
            'BLOCKED' => ['danger', 'Blocked'],
            'READY_WITH_WARNINGS' => ['warning', 'Ready with warnings'],
            'READY' => ['success', 'Ready'],
        ];
    @endphp
    <div class="alert alert-info">
        <strong>Preflight only:</strong> These are internal content checks, not live Amazon/Flipkart schema validation.
        This page does not publish listings or call marketplace APIs. Exported CSV is a review worksheet, not an upload feed.
    </div>
    <div class="card mb-3"><div class="card-body">
        <form method="GET" action="{{ route('listing-readiness.index') }}" class="row g-2 align-items-end">
            <div class="col-md-4"><label class="form-label">Search SKU / product</label>
                <input class="form-control" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Enter SKU or product name"></div>
            <div class="col-md-3"><label class="form-label">Marketplace</label><select class="form-select" name="marketplace_id">
                <option value="">All marketplaces</option>
                @foreach ($marketplaces as $marketplace)
                    <option value="{{ $marketplace->id }}" @selected(($filters['marketplace_id'] ?? '') == $marketplace->id)>{{ $marketplace->name }}</option>
                @endforeach
            </select></div>
            <div class="col-md-3"><label class="form-label">Listing status</label><select class="form-select" name="status">
                <option value="">All statuses</option>
                @foreach (\App\Models\Listing::STATUSES as $key => $label)
                    <option value="{{ $key }}" @selected(($filters['status'] ?? '') === $key)>{{ $label }}</option>
                @endforeach
            </select></div>
            <div class="col-md-2 d-flex gap-2"><button class="btn btn-primary flex-grow-1">Filter</button>
                <a class="btn btn-outline-secondary" href="{{ route('listing-readiness.index') }}">Reset</a></div>
        </form>
    </div></div>
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div class="text-muted small">{{ $listings->total() }} listing(s) found · checks use the current saved version</div>
        <a class="btn btn-outline-success btn-sm" href="{{ route('listing-readiness.export', request()->query()) }}"><i class="bi bi-file-earmark-spreadsheet"></i> Export preflight CSV</a>
    </div>
    <div class="card"><div class="table-responsive"><table class="table table-hover align-middle mb-0">
        <thead><tr><th>Product / SKU</th><th>Marketplace</th><th>Listing</th><th>Current version</th><th>Score</th><th>Preflight</th><th>Issues</th><th></th></tr></thead>
        <tbody>
        @forelse ($listings as $listing)
            @php [$badge, $label] = $statusMeta[$listing->preflight['status']]; @endphp
            <tr>
                <td><div class="fw-semibold">{{ $listing->product?->name ?? 'Deleted product' }}</div><div class="small text-muted">{{ $listing->product?->sku ?? 'No SKU' }}</div></td>
                <td>{{ $listing->marketplace?->name ?? '—' }}</td>
                <td><span class="badge text-bg-{{ \App\Models\Listing::BADGES[$listing->status] ?? 'secondary' }}">{{ \App\Models\Listing::STATUSES[$listing->status] ?? $listing->status }}</span></td>
                <td>{{ $listing->current ? 'v' . $listing->current->version_no : '—' }}</td>
                <td>{{ number_format((float) ($listing->current?->score ?? 0), 1) }}/100</td>
                <td><span class="badge text-bg-{{ $badge }}">{{ $label }}</span></td>
                <td><span class="text-danger">{{ $listing->preflight['blockers'] }} blocker(s)</span><span class="text-muted"> · </span><span class="text-warning-emphasis">{{ $listing->preflight['warnings'] }} warning(s)</span></td>
                <td class="text-end"><a class="btn btn-sm btn-outline-primary" href="{{ route('listings.show', $listing) }}">Open listing</a></td>
            </tr>
        @empty
            <tr><td colspan="8" class="text-center text-muted py-4">No listings match these filters.</td></tr>
        @endforelse
        </tbody>
    </table></div><div class="card-footer">{{ $listings->links() }}</div></div>
@endsection
