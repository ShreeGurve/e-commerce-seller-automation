@extends('layouts.app')
@section('title', 'Marketplace Attribute Mapping')
@section('content')
<div class="container-fluid py-3">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
        <div><h3 class="mb-1">Marketplace Attribute Mapping</h3><p class="text-muted mb-0">Configure category requirements, map product data and validate listing readiness.</p></div>
    </div>
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

    <div class="card mb-4"><div class="card-header"><strong>Create or update a category profile</strong></div><div class="card-body">
        <form method="POST" action="{{ route('listings.attributes.profiles.store') }}">@csrf
            <div class="row g-3">
                <div class="col-md-3"><label class="form-label">Marketplace</label><select name="marketplace_id" class="form-select" required><option value="">Select</option>@foreach($marketplaces as $marketplace)<option value="{{ $marketplace->id }}">{{ $marketplace->name }}</option>@endforeach</select></div>
                <div class="col-md-3"><label class="form-label">Category key</label><input name="category_key" class="form-control" placeholder="e.g. Home.Kitchen.Bottles" required></div>
                <div class="col-md-3"><label class="form-label">Category label</label><input name="category_label" class="form-control" required></div>
                <div class="col-md-3"><label class="form-label">Schema version / reference</label><input name="schema_version" class="form-control" placeholder="Optional reference"></div>
            </div>
            <div class="mt-3"><label class="form-label">Attribute definitions (JSON)</label><textarea name="attributes_json" id="attributes-json" class="form-control font-monospace" rows="7">[
  {"attribute_key":"material","label":"Material","data_type":"TEXT","is_required":true},
  {"attribute_key":"capacity_ml","label":"Capacity (ml)","data_type":"NUMBER","is_required":true},
  {"attribute_key":"colour","label":"Colour","data_type":"SELECT","is_required":false,"allowed_values":["Black","Blue","Silver"]},
  {"attribute_key":"size","label":"Size","data_type":"TEXT","is_required":false,"is_variant_attribute":true}
]</textarea><div class="form-text">Use a category schema obtained from an authorized marketplace source. These definitions are manual configuration, not official marketplace requirements.</div></div>
            <input type="hidden" name="attributes" id="attributes-field">
            <button class="btn btn-primary mt-3" type="submit" onclick="try{document.getElementById('attributes-field').value=JSON.stringify(JSON.parse(document.getElementById('attributes-json').value));}catch(e){alert('Please enter valid JSON for attributes.');return false;}">Save profile</button>
        </form>
    </div></div>

    <div class="card mb-4"><div class="card-header"><strong>Configured profiles</strong></div><div class="table-responsive"><table class="table table-hover mb-0"><thead><tr><th>Marketplace</th><th>Category</th><th>Key</th><th>Attributes</th><th>Schema source</th><th>Status</th></tr></thead><tbody>
    @forelse($profiles as $profile)<tr><td>{{ $profile->marketplace->name }}</td><td>{{ $profile->category_label }}</td><td><code>{{ $profile->category_key }}</code></td><td>{{ $profile->definitions->count() }}</td><td>{{ $profile->schema_source }} {{ $profile->schema_version }}</td><td>{{ $profile->is_active ? 'Active' : 'Inactive' }}</td></tr>@empty<tr><td colspan="6" class="text-center text-muted py-4">No category profiles configured.</td></tr>@endforelse
    </tbody></table></div><div class="card-footer">{{ $profiles->links() }}</div></div>

    <div class="card mb-4"><div class="card-header"><strong>Validate listing attributes</strong></div><div class="card-body"><div class="table-responsive"><table class="table align-middle"><thead><tr><th>Product</th><th>Marketplace</th><th>Category path</th><th>Current version</th><th>Actions</th></tr></thead><tbody>
    @forelse($listings as $listing)<tr><td>{{ $listing->product?->name }} <small class="text-muted">({{ $listing->product?->sku }})</small></td><td>{{ $listing->marketplace?->name }}</td><td>{{ $listing->category_path ?: '—' }}</td><td>{{ $listing->current?->version_no ?? '—' }}</td><td class="text-end"><a class="btn btn-sm btn-outline-secondary" href="{{ route('listings.attributes.workspace', $listing) }}">Map attributes</a> <form class="d-inline" method="POST" action="{{ route('listings.attributes.validate', $listing) }}">@csrf<button class="btn btn-sm btn-outline-primary">Validate</button></form></td></tr>@empty<tr><td colspan="5" class="text-center text-muted">No versioned listings found.</td></tr>@endforelse
    </tbody></table></div></div></div>

    <div class="card mb-4"><div class="card-header"><strong>Pending mapping approvals</strong></div><div class="table-responsive"><table class="table mb-0"><thead><tr><th>Product</th><th>Marketplace</th><th>Attribute</th><th>Mapped value / source</th><th>Review</th></tr></thead><tbody>
    @forelse($pendingMappings as $mapping)<tr><td>{{ $mapping->listing?->product?->sku }}</td><td>{{ $mapping->definition?->profile?->marketplace?->name }}</td><td>{{ $mapping->definition?->label }}</td><td>{{ $mapping->source_field ?: json_encode($mapping->mapped_value['value'] ?? null) }}</td><td><div class="d-flex gap-1"><form method="POST" action="{{ route('listings.attributes.review', $mapping) }}">@csrf<input type="hidden" name="decision" value="APPROVED"><button class="btn btn-sm btn-success">Approve</button></form><form method="POST" action="{{ route('listings.attributes.review', $mapping) }}">@csrf<input type="hidden" name="decision" value="REJECTED"><button class="btn btn-sm btn-outline-danger">Reject</button></form></div></td></tr>@empty<tr><td colspan="5" class="text-center text-muted py-3">No pending mapping approvals.</td></tr>@endforelse
    </tbody></table></div></div>

    <div class="card"><div class="card-header"><strong>Recent validation history</strong></div><div class="table-responsive"><table class="table mb-0"><thead><tr><th>Listing</th><th>Status</th><th>Total</th><th>Valid</th><th>Missing required</th><th>Invalid</th><th>Date</th></tr></thead><tbody>
    @forelse($audits as $audit)<tr><td>{{ $audit->listing?->product?->sku }} — {{ $audit->listing?->marketplace?->name }}</td><td><span class="badge {{ $audit->status === 'VALID' ? 'bg-success' : 'bg-warning text-dark' }}">{{ $audit->status }}</span></td><td>{{ $audit->total_attributes }}</td><td>{{ $audit->valid_attributes }}</td><td>{{ $audit->missing_required }}</td><td>{{ $audit->invalid_attributes }}</td><td>{{ $audit->created_at?->format('d M Y H:i') }}</td></tr>@empty<tr><td colspan="7" class="text-center text-muted py-4">No validation runs yet.</td></tr>@endforelse
    </tbody></table></div></div>
</div>
@endsection
