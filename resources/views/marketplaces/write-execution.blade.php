@extends('layouts.app')

@section('content')
<div class="container-fluid py-3">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1">Marketplace Write Control Center</h1>
            <div class="text-muted small">Prepare, independently approve and submit controlled price or inventory updates.</div>
        </div>
        <span class="badge text-bg-warning">Live writes OFF by default</span>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger"><strong>Unable to continue.</strong><ul class="mb-0 mt-1">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif
    @if (session('success')) <div class="alert alert-success">{{ session('success') }}</div> @endif
    @if (session('warning')) <div class="alert alert-warning">{{ session('warning') }}</div> @endif

    <div class="alert alert-info small">
        Every submission requires an approved Package 18 task, a local preview, a second user’s approval, a connection-specific policy, and all server-side environment gates. Amazon acceptance is asynchronous; Flipkart may report per-SKU errors even on HTTP 200.
    </div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white"><strong>Connection operation policies</strong></div>
        <div class="card-body">
            @forelse ($connections as $connection)
                <div class="border rounded p-3 mb-3">
                    <div class="d-flex flex-wrap justify-content-between gap-2 mb-2">
                        <div><strong>{{ $connection->marketplace->name ?? 'Marketplace' }}</strong> — {{ $connection->account_label }} <span class="text-muted small">({{ strtoupper($connection->environment ?? 'production') }})</span></div>
                        <span class="badge {{ $connection->status === 'connected' ? 'text-bg-success' : 'text-bg-secondary' }}">{{ $connection->status }}</span>
                    </div>
                    <div class="row g-3">
                        @foreach (['PRICE_UPDATE', 'INVENTORY_UPDATE'] as $operation)
                            @php $policy = $policies->get($connection->id.'_'.$operation); $enabled = (bool) ($policy->is_enabled ?? false); @endphp
                            <div class="col-md-6">
                                <div class="d-flex align-items-center justify-content-between border rounded p-2">
                                    <div><div class="fw-semibold">{{ str_replace('_', ' ', $operation) }}</div><div class="small text-muted">Policy: {{ $enabled ? 'Enabled' : 'Disabled' }}</div></div>
                                    <form method="POST" action="{{ route('marketplaces.writes.policy', [$connection->id, $operation]) }}">
                                        @csrf @method('PATCH')
                                        <input type="hidden" name="is_enabled" value="{{ $enabled ? 0 : 1 }}">
                                        <button class="btn btn-sm {{ $enabled ? 'btn-outline-danger' : 'btn-outline-primary' }}" type="submit">{{ $enabled ? 'Disable' : 'Enable' }}</button>
                                    </form>
                                </div>
                            </div>
                        @endforeach
                    </div>
                    <div class="small text-muted mt-2">Enabling a policy requires Super Admin. It does not override the global environment flags.</div>
                </div>
            @empty
                <div class="text-muted">No marketplace connections are configured for this business.</div>
            @endforelse
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white"><strong>Prepare a write preview</strong></div>
        <div class="card-body">
            <form method="POST" action="{{ route('marketplaces.writes.prepare') }}">
                @csrf
                <div class="row g-3">
                    <div class="col-lg-3 col-md-6"><label class="form-label">Marketplace connection</label><select name="connection_id" class="form-select" required><option value="">Select connection</option>@foreach ($connections as $connection)<option value="{{ $connection->id }}">{{ $connection->marketplace->name ?? 'Marketplace' }} — {{ $connection->account_label }}</option>@endforeach</select></div>
                    <div class="col-lg-3 col-md-6"><label class="form-label">Approved automation task</label><select name="task_id" class="form-select" required><option value="">Select approved task</option>@foreach ($tasks as $task)<option value="{{ $task->id }}">#{{ $task->id }} · {{ $task->product_name }} · {{ $task->recommendation_type }}</option>@endforeach</select></div>
                    <div class="col-lg-3 col-md-6"><label class="form-label">Operation</label><select name="operation" class="form-select" required><option value="PRICE_UPDATE">Price update</option><option value="INVENTORY_UPDATE">Inventory update</option></select></div>
                    <div class="col-lg-3 col-md-6"><label class="form-label">Idempotency key (optional)</label><input name="idempotency_key" class="form-control" maxlength="100" placeholder="Generated automatically"></div>
                    <div class="col-md-3"><label class="form-label">Selling price (INR)</label><input type="number" step="0.01" min="0.01" name="selling_price" class="form-control" placeholder="Price update only"></div>
                    <div class="col-md-3"><label class="form-label">MRP (INR)</label><input type="number" step="1" min="1" name="mrp" class="form-control" placeholder="Required for Flipkart price"></div>
                    <div class="col-md-3"><label class="form-label">Available quantity</label><input type="number" step="1" min="0" name="quantity" class="form-control" placeholder="Inventory update only"></div>
                    <div class="col-md-3"><label class="form-label">Flipkart location ID</label><input name="location_id" class="form-control" maxlength="100" placeholder="Required for Flipkart inventory"></div>
                </div>
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mt-3">
                    <div class="small text-muted">Preview is local-only. It does not call Amazon or Flipkart and expires after the configured window.</div>
                    <button class="btn btn-primary" type="submit">Prepare preview</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white"><strong>Recent write attempts</strong></div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead><tr><th>ID</th><th>Marketplace</th><th>Operation</th><th>SKU</th><th>Status</th><th>Prepared by / time</th><th>Expires</th><th>Payload / approval</th></tr></thead>
                <tbody>
                @forelse ($attempts as $attempt)
                    <tr>
                        <td>#{{ $attempt->id }}</td><td>{{ strtoupper($attempt->marketplace_code) }}</td><td>{{ str_replace('_', ' ', $attempt->operation) }}</td><td>{{ $attempt->marketplace_sku }}</td>
                        <td><span class="badge {{ in_array($attempt->status, ['ACCEPTED'] , true) ? 'text-bg-success' : (in_array($attempt->status, ['FAILED','BLOCKED','EXPIRED'], true) ? 'text-bg-danger' : (in_array($attempt->status, ['REVIEW_REQUIRED'], true) ? 'text-bg-warning' : 'text-bg-secondary')) }}">{{ $attempt->status }}</span>@if($attempt->error_message)<div class="small text-danger mt-1">{{ \Illuminate\Support\Str::limit($attempt->error_message, 100) }}</div>@endif</td>
                        <td>{{ $attempt->prepared_by_name ?? '—' }}<div class="small text-muted">{{ $attempt->created_at }}</div></td><td>{{ $attempt->expires_at }}</td>
                        <td><details><summary class="small">Inspect exact payload</summary><pre class="small bg-light p-2 mt-2 rounded">{{ json_encode(json_decode($attempt->request_payload, true), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre><div class="small text-muted">SHA-256: {{ $attempt->payload_hash }}</div></details>@if($attempt->status === 'PREPARED')<form method="POST" action="{{ route('marketplaces.writes.approve-execute', $attempt->id) }}" onsubmit="return confirm('Submit this exact marketplace payload? This can change a live listing.');">@csrf<label class="form-check small mb-2"><input class="form-check-input" type="checkbox" name="confirm_submit" value="1" required> <span class="form-check-label">I reviewed this exact payload and approve the live marketplace update.</span></label><button class="btn btn-sm btn-danger" type="submit">Approve & submit</button></form><div class="small text-muted mt-1">Must be a different user</div>@else — @endif</td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-center text-muted py-4">No marketplace write attempts yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
