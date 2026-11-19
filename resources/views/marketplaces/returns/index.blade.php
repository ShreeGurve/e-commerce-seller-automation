@extends('layouts.app')
@section('title', 'Marketplace Returns & Refunds')
@section('content')
<div class="container-fluid py-3">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
        <div><h1 class="h3 mb-1">Marketplace Returns & Refunds</h1><p class="text-muted mb-0">Review imported return and refund records before adding them to your operational ledgers.</p></div>
    </div>
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <div class="row g-3 mb-4">
        <div class="col-12 col-xl-6"><div class="card h-100"><div class="card-header fw-semibold">Stage marketplace return</div><div class="card-body">
            <form method="POST" action="{{ route('marketplaces.returns.stage') }}">@csrf
                <div class="row g-2">
                    <div class="col-md-6"><label class="form-label">Marketplace</label><select name="marketplace_id" class="form-select" required>@foreach($marketplaces as $marketplace)<option value="{{ $marketplace->id }}">{{ $marketplace->name }}</option>@endforeach</select></div>
                    <div class="col-md-6"><label class="form-label">External return ID</label><input name="external_return_id" class="form-control" required></div>
                    <div class="col-md-6"><label class="form-label">External order ID</label><input name="external_order_id" class="form-control" required></div>
                    <div class="col-md-6"><label class="form-label">Return date</label><input type="date" name="return_date" class="form-control" required value="{{ now()->toDateString() }}"></div>
                    <div class="col-md-6"><label class="form-label">Reason type</label><select name="return_type" class="form-select">@foreach(\App\Models\ProductReturn::TYPES as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach</select></div>
                    <div class="col-md-6"><label class="form-label">Reason details</label><input name="reason_text" class="form-control" maxlength="500"></div>
                    <div class="col-md-6"><label class="form-label">Order line ID</label><input name="items[0][external_line_id]" class="form-control" placeholder="Existing order line ID"></div>
                    <div class="col-md-4"><label class="form-label">Marketplace SKU</label><input name="items[0][marketplace_sku]" class="form-control"></div>
                    <div class="col-md-2"><label class="form-label">Qty</label><input name="items[0][quantity]" type="number" min="1" value="1" class="form-control" required></div>
                </div><button class="btn btn-primary mt-3">Stage return</button>
            </form>
        </div></div></div>
        <div class="col-12 col-xl-6"><div class="card h-100"><div class="card-header fw-semibold">Stage marketplace refund</div><div class="card-body">
            <form method="POST" action="{{ route('marketplaces.returns.refunds.stage') }}">@csrf
                <div class="row g-2">
                    <div class="col-md-6"><label class="form-label">Marketplace</label><select name="marketplace_id" class="form-select" required>@foreach($marketplaces as $marketplace)<option value="{{ $marketplace->id }}">{{ $marketplace->name }}</option>@endforeach</select></div>
                    <div class="col-md-6"><label class="form-label">External refund ID</label><input name="external_refund_id" class="form-control" required></div>
                    <div class="col-md-6"><label class="form-label">External order ID</label><input name="external_order_id" class="form-control" required></div>
                    <div class="col-md-6"><label class="form-label">External return ID (optional)</label><input name="external_return_id" class="form-control"></div>
                    <div class="col-md-4"><label class="form-label">Amount (INR)</label><input name="amount" type="number" step="0.01" min="0.01" class="form-control" required></div>
                    <div class="col-md-4"><label class="form-label">Refund date</label><input name="refund_date" type="date" class="form-control" required value="{{ now()->toDateString() }}"></div>
                    <div class="col-md-4"><label class="form-label">Currency</label><input name="currency" class="form-control" value="INR" maxlength="3"></div>
                    <div class="col-12"><label class="form-label">Reason</label><input name="reason" class="form-control" maxlength="180"></div>
                </div><button class="btn btn-primary mt-3">Stage refund</button>
            </form>
        </div></div></div>
    </div>
    <div class="card mb-4"><div class="card-header fw-semibold">Return imports awaiting review</div><div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th>External return</th><th>Marketplace</th><th>Order</th><th>Return date</th><th>Status</th><th class="text-end">Action</th></tr></thead><tbody>
        @forelse($returnImports as $row)<tr><td>{{ $row->external_return_id }}</td><td>{{ $row->marketplace?->name }}</td><td>{{ $row->external_order_id }}</td><td>{{ data_get($row->payload, 'return_date') }}</td><td><span class="badge bg-secondary">{{ $row->status }}</span></td><td class="text-end">@if($row->status === 'PENDING')<form class="d-inline" method="POST" action="{{ route('marketplaces.returns.approve', $row) }}">@csrf<button class="btn btn-sm btn-success">Import</button></form><form class="d-inline" method="POST" action="{{ route('marketplaces.returns.reject', $row) }}">@csrf<input type="hidden" name="note" value="Rejected during manual review"><button class="btn btn-sm btn-outline-danger">Reject</button></form>@elseif($row->return_id)<span class="text-muted">Return #{{ $row->return_id }}</span>@endif</td></tr>@empty<tr><td colspan="6" class="text-center text-muted py-4">No marketplace return imports yet.</td></tr>@endforelse
    </tbody></table></div></div>
    <div class="card mb-4"><div class="card-header fw-semibold">Refund imports awaiting reconciliation</div><div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th>External refund</th><th>Marketplace</th><th>Order</th><th>Amount</th><th>Date</th><th>Status</th><th class="text-end">Action</th></tr></thead><tbody>
        @forelse($refundImports as $row)<tr><td>{{ $row->external_refund_id }}</td><td>{{ $row->marketplace?->name }}</td><td>{{ $row->external_order_id }}</td><td>₹{{ number_format((float)$row->amount, 2) }}</td><td>{{ $row->refund_date?->format('d M Y') }}</td><td><span class="badge bg-secondary">{{ $row->status }}</span></td><td class="text-end">@if($row->status === 'PENDING')<form method="POST" action="{{ route('marketplaces.returns.refunds.reconcile', $row) }}">@csrf<button class="btn btn-sm btn-primary">Reconcile</button></form>@elseif($row->refund_id)<span class="text-muted">Refund #{{ $row->refund_id }}</span>@endif</td></tr>@empty<tr><td colspan="7" class="text-center text-muted py-4">No marketplace refund imports yet.</td></tr>@endforelse
    </tbody></table></div></div>
    <div class="card"><div class="card-header fw-semibold">Recent returns and inspection status</div><div class="table-responsive"><table class="table table-hover mb-0"><thead><tr><th>Return</th><th>Order</th><th>Marketplace</th><th>Reason</th><th>Status</th><th>Claim eligible</th></tr></thead><tbody>
        @forelse($returns as $return)<tr><td>{{ $return->return_number }}</td><td>{{ $return->order?->order_number }}</td><td>{{ $return->marketplace?->name }}</td><td>{{ $return->reason_text ?: (\App\Models\ProductReturn::TYPES[$return->return_type] ?? $return->return_type) }}</td><td>{{ $return->status }}</td><td>{{ $return->claim_eligible ? 'Yes' : 'No' }}</td></tr>@empty<tr><td colspan="6" class="text-center text-muted py-4">No returns recorded yet.</td></tr>@endforelse
    </tbody></table></div></div>
</div>
@endsection
