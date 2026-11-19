@extends('layouts.app')
@section('title', 'Dynamic Pricing Rules')
@section('subtitle', 'Build controlled pricing recommendations using your costs, fees and manually recorded competitor prices.')
@section('content')
<div class="d-flex flex-wrap gap-2 align-items-center mb-3">
    <a href="{{ route('pricing.index') }}" class="btn btn-outline-secondary btn-sm">Pricing intelligence</a>
    <a href="{{ route('pricing-recommendations.index') }}" class="btn btn-outline-secondary btn-sm">Approval queue</a>
    <form method="POST" action="{{ route('pricing-dynamic.run') }}" class="ms-auto">@csrf<button class="btn btn-primary btn-sm">Run rules now</button></form>
</div>
<div class="alert alert-info">Safety: this engine only creates pending internal recommendations. It does not update Amazon, Flipkart, or any marketplace listing. Competitor prices must be entered/imported from sources you are authorized to use.</div>
<div class="card mb-4"><div class="card-header fw-semibold">Create / replace a product-channel rule</div><div class="card-body">
<form method="POST" action="{{ route('pricing-dynamic.store') }}" class="row g-3">@csrf
<div class="col-md-4"><label class="form-label">Product</label><select name="product_id" class="form-select" required><option value="">Select product</option>@foreach($products as $p)<option value="{{ $p->id }}">{{ $p->sku }} — {{ $p->name }}</option>@endforeach</select></div>
<div class="col-md-3"><label class="form-label">Marketplace</label><select name="marketplace_id" class="form-select" required><option value="">Select channel</option>@foreach($marketplaces as $m)<option value="{{ $m->id }}">{{ $m->name }}</option>@endforeach</select></div>
<div class="col-md-5"><label class="form-label">Strategy</label><select name="strategy" class="form-select" required><option value="TARGET_MARGIN">Target margin (cost + fees)</option><option value="COMPETITOR_MATCH">Match lowest recorded competitor</option><option value="COMPETITOR_UNDERCUT">Undercut lowest competitor</option><option value="COMPETITOR_PREMIUM">Price above lowest competitor</option></select></div>
<div class="col-md-3"><label class="form-label">Minimum safe price (₹)</label><input name="floor_price" type="number" min="0.01" step="0.01" class="form-control" placeholder="Optional"></div>
<div class="col-md-3"><label class="form-label">Maximum price (₹)</label><input name="ceiling_price" type="number" min="0.01" step="0.01" class="form-control" placeholder="Optional"></div>
<div class="col-md-3"><label class="form-label">Minimum margin (%)</label><input name="minimum_margin_percent" type="number" min="0" max="95" step="0.01" value="5" class="form-control" required></div>
<div class="col-md-3"><label class="form-label">Competitor adjustment (%)</label><input name="competitor_adjustment_percent" type="number" min="0" max="20" step="0.01" value="1" class="form-control" required></div>
<div class="col-12"><div class="form-check"><input type="checkbox" name="enabled" value="1" class="form-check-input" id="rule-enabled"><label class="form-check-label" for="rule-enabled">Enable rule for scheduled/manual runs</label></div></div>
<div class="col-12"><button class="btn btn-primary">Save pricing rule</button></div>
</form></div></div>
<div class="card"><div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th>Product / Channel</th><th>Strategy</th><th>Price limits</th><th>Minimum margin</th><th>Status</th><th>Action</th></tr></thead><tbody>
@forelse($rules as $rule)<tr><td><strong>{{ $rule->product?->sku }} — {{ $rule->product?->name }}</strong><div class="small text-muted">{{ $rule->marketplace?->name }}</div></td><td>{{ str_replace('_',' ', $rule->strategy) }}</td><td class="small">Floor: {{ $rule->floor_price ? '₹'.number_format((float)$rule->floor_price,2) : 'Cost floor' }}<br>Ceiling: {{ $rule->ceiling_price ? '₹'.number_format((float)$rule->ceiling_price,2) : 'No ceiling' }}</td><td>{{ number_format((float)$rule->minimum_margin_percent,2) }}%</td><td><span class="badge text-bg-{{ $rule->enabled ? 'success' : 'secondary' }}">{{ $rule->enabled ? 'Enabled' : 'Disabled' }}</span></td><td>@if($rule->enabled)<form method="POST" action="{{ route('pricing-dynamic.destroy', $rule) }}">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger">Disable</button></form>@else <span class="text-muted small">Edit by saving rule again</span>@endif</td></tr>
@empty<tr><td colspan="6" class="text-center text-muted py-4">No dynamic pricing rules configured.</td></tr>@endforelse
</tbody></table></div><div class="card-footer">{{ $rules->links() }}</div></div>
@endsection
