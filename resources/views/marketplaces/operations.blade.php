@extends('layouts.app')
@section('title', 'Marketplace Operations')
@section('subtitle', 'Manage channel SKU mappings and listing readiness · Local workspace only')
@section('content')
    <div class="alert alert-info">
        <strong>Safe operating mode:</strong> This workspace manages your internal marketplace catalogue.
        It does not publish listings, change marketplace prices, or sync inventory to Amazon or Flipkart.
        Account verification can be completed later.
    </div>
    <div class="row g-3 mb-3">
        @foreach ([['Mapped SKUs', $mappedCount, 'bi-link-45deg', 't-blue'], ['Active products', $activeProducts, 'bi-box-seam', 't-green'], ['Active products not mapped', $unmappedProducts, 'bi-exclamation-circle', 't-orange'], ['Draft / Live listings', $draftListings . ' / ' . $liveListings, 'bi-card-checklist', 't-blue']] as [$label, $value, $icon, $tone])
            <div class="col-6 col-xl-3"><div class="card h-100"><div class="card-body d-flex align-items-center gap-3">
                <div class="sg-tile {{ $tone }}"><i class="bi {{ $icon }}"></i></div>
                <div><div class="small text-muted">{{ $label }}</div><div class="fw-bold fs-5">{{ $value }}</div></div>
            </div></div></div>
        @endforeach
    </div>
    @can('products.edit')
        <div class="card mb-3">
            <div class="card-header bg-white fw-semibold">Add marketplace SKU mapping</div>
            <div class="card-body">
                <form method="POST" action="{{ route('marketplaces.operations.mappings.store') }}" class="row g-3 align-items-end">@csrf
                    <div class="col-lg-3"><label class="form-label">Internal product *</label><select name="product_id" class="form-select" required><option value="">Select product</option>@foreach ($products as $product)<option value="{{ $product->id }}" @selected(old('product_id') == $product->id)>{{ $product->sku }} — {{ $product->name }}</option>@endforeach</select></div>
                    <div class="col-lg-2"><label class="form-label">Marketplace *</label><select name="marketplace_id" class="form-select" required><option value="">Select platform</option>@foreach ($marketplaces as $marketplace)<option value="{{ $marketplace->id }}" @selected(old('marketplace_id', request('marketplace_id')) == $marketplace->id)>{{ $marketplace->name }}</option>@endforeach</select></div>
                    <div class="col-lg-2"><label class="form-label">Marketplace SKU *</label><input name="marketplace_sku" value="{{ old('marketplace_sku') }}" class="form-control" maxlength="120" required></div>
                    <div class="col-lg-2"><label class="form-label">ASIN / FSN / External ID</label><input name="external_product_id" value="{{ old('external_product_id') }}" class="form-control" maxlength="60"></div>
                    <div class="col-lg-1"><label class="form-label">FNSKU</label><input name="fulfillment_sku" value="{{ old('fulfillment_sku') }}" class="form-control" maxlength="60"></div>
                    <div class="col-lg-1"><label class="form-label">Status</label><select name="listing_status" class="form-select">@foreach (['draft','active','inactive','suppressed'] as $status)<option value="{{ $status }}" @selected(old('listing_status', 'draft') === $status)>{{ ucfirst($status) }}</option>@endforeach</select></div>
                    <div class="col-lg-1"><button class="btn btn-primary w-100">Add</button></div>
                </form>
                @if ($errors->any())<div class="alert alert-danger mt-3 mb-0"><ul class="mb-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
            </div>
        </div>
    @endcan
    <div class="card">
        <div class="card-header bg-white"><form method="GET" class="row g-2 align-items-end">
            <div class="col-md-4"><label class="form-label small">Search</label><input name="q" value="{{ request('q') }}" class="form-control" placeholder="Internal SKU, name or marketplace SKU"></div>
            <div class="col-md-3"><label class="form-label small">Marketplace</label><select name="marketplace_id" class="form-select"><option value="">All platforms</option>@foreach ($marketplaces as $marketplace)<option value="{{ $marketplace->id }}" @selected(request('marketplace_id') == $marketplace->id)>{{ $marketplace->name }}</option>@endforeach</select></div>
            <div class="col-md-3"><label class="form-label small">Mapping status</label><select name="listing_status" class="form-select"><option value="">All statuses</option>@foreach (['draft','active','inactive','suppressed'] as $status)<option value="{{ $status }}" @selected(request('listing_status') === $status)>{{ ucfirst($status) }}</option>@endforeach</select></div>
            <div class="col-md-2 d-flex gap-2"><button class="btn btn-outline-primary flex-fill">Filter</button><a href="{{ route('marketplaces.operations') }}" class="btn btn-outline-secondary">Reset</a></div>
        </form></div>
        <div class="table-responsive"><table class="table table-hover align-middle mb-0">
            <thead class="table-light"><tr><th>Internal product</th><th>Marketplace</th><th>Marketplace SKU</th><th>External ID</th><th>Fulfillment SKU</th><th>Status</th><th class="text-end">Action</th></tr></thead>
            <tbody>
            @forelse ($mappings as $mapping)
                <tr><td><a href="{{ route('products.show', $mapping->product_id) }}" class="text-decoration-none fw-semibold">{{ $mapping->product->sku }}</a><div class="small text-muted">{{ $mapping->product->name }}</div></td>
                    <td>{{ $mapping->marketplace->name }}</td><td>{{ $mapping->marketplace_sku }}</td><td>{{ $mapping->external_product_id ?: '—' }}</td><td>{{ $mapping->fulfillment_sku ?: '—' }}</td>
                    <td><span class="badge text-bg-{{ $mapping->listing_status === 'active' ? 'success' : ($mapping->listing_status === 'suppressed' ? 'danger' : 'secondary') }}">{{ ucfirst($mapping->listing_status) }}</span></td>
                    <td class="text-end"><a class="btn btn-sm btn-outline-primary" href="{{ route('products.show', [$mapping->product_id, 'tab' => 'marketplace']) }}">Open product</a>
                    @can('products.edit')<form class="d-inline" method="POST" action="{{ route('marketplaces.operations.mappings.destroy', $mapping) }}" onsubmit="return confirm('Remove this local marketplace mapping?')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger">Remove</button></form>@endcan</td>
                </tr>
            @empty<tr><td colspan="7" class="text-center text-muted py-4">No marketplace mappings match your filters. Add the first mapping above.</td></tr>@endforelse
            </tbody></table></div>
        @if ($mappings->hasPages())<div class="card-footer">{{ $mappings->links() }}</div>@endif
    </div>
    <div class="d-flex flex-wrap gap-2 mt-3"><a href="{{ route('listings.index') }}" class="btn btn-outline-primary">Manage listing drafts</a><a href="{{ route('pricing.index') }}" class="btn btn-outline-primary">Channel pricing</a><a href="{{ route('imports.index') }}" class="btn btn-outline-primary">Import marketplace reports</a></div>
@endsection
