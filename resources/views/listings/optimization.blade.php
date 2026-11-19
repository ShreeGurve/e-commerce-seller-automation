@extends('layouts.app')
@section('title', 'Listing Optimization')
@section('content')
<div class="container-fluid py-3">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div><h1 class="h4 mb-1">Listing Optimization</h1><p class="text-muted mb-0">Content quality checks and improvement suggestions. These are advisory checks, not official marketplace validation.</p></div>
        <a href="{{ route('listings.index') }}" class="btn btn-outline-secondary">All listings</a>
    </div>
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif

    <div class="card mb-4"><div class="card-header fw-semibold">Run an optimization scan</div><div class="card-body">
        @if($listings->isEmpty())<p class="text-muted mb-0">No listings with saved versions were found.</p>@else
        <div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Product</th><th>Marketplace</th><th>Version</th><th class="text-end">Action</th></tr></thead><tbody>
        @foreach($listings as $listing)<tr><td><div class="fw-semibold">{{ $listing->product?->name ?? 'Product' }}</div><small class="text-muted">{{ $listing->product?->sku }}</small></td><td>{{ $listing->marketplace?->name }}</td><td>{{ $listing->current?->version_no }}</td><td class="text-end"><form method="POST" action="{{ route('listings.optimization.run', $listing) }}">@csrf<button class="btn btn-sm btn-primary">Scan listing</button></form></td></tr>@endforeach
        </tbody></table></div>@endif
    </div></div>

    <div class="card"><div class="card-header fw-semibold">Optimization history</div><div class="card-body p-0"><div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th>Product</th><th>Marketplace</th><th>Version</th><th>Score</th><th>Status</th><th>Issues</th><th>Date</th></tr></thead><tbody>
    @forelse($audits as $audit)<tr><td>{{ $audit->listing?->product?->name }} <small class="text-muted d-block">{{ $audit->listing?->product?->sku }}</small></td><td>{{ $audit->listing?->marketplace?->name }}</td><td>{{ $audit->version?->version_no }}</td><td class="fw-semibold">{{ number_format((float)$audit->score, 1) }}/100</td><td><span class="badge text-bg-{{ $audit->status === 'GOOD' ? 'success' : ($audit->status === 'BLOCKED' ? 'danger' : 'warning') }}">{{ $audit->status }}</span></td><td>{{ $audit->issue_count }} <small class="text-muted">({{ $audit->blocker_count }} blockers / {{ $audit->warning_count }} warnings)</small><details class="mt-1"><summary>View suggestions</summary><ul class="small mt-2 mb-0">@foreach((array)$audit->results as $issue)<li class="mb-2"><strong>{{ $issue['field'] ?? '' }} — {{ $issue['message'] ?? '' }}</strong><br>{{ $issue['recommendation'] ?? '' }}</li>@endforeach</ul></details></td><td>{{ $audit->created_at?->format('d M Y, h:i A') }}</td></tr>
    @empty<tr><td colspan="7" class="text-center text-muted py-4">No optimization scans yet.</td></tr>@endforelse
    </tbody></table></div></div><div class="card-footer">{{ $audits->links() }}</div></div>
</div>
@endsection
