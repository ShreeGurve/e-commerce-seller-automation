@extends('layouts.app')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div><h3 class="mb-1">Supplier Return Settlements</h3><p class="text-muted mb-0">Record and confirm supplier refunds, credits and replacement receipts after a return is resolved.</p></div>
    </div>
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger">@foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>@endif

    <div class="row g-3 mb-4">
        <div class="col-xl col-md-4"><div class="card border-0 shadow-sm h-100"><div class="card-body"><div class="text-muted small">Pending confirmation</div><div class="fs-3 fw-semibold">{{ number_format($summary['pending']) }}</div></div></div></div>
        <div class="col-xl col-md-4"><div class="card border-0 shadow-sm h-100"><div class="card-body"><div class="text-muted small">Confirmed records</div><div class="fs-3 fw-semibold">{{ number_format($summary['confirmed']) }}</div></div></div></div>
        <div class="col-xl col-md-4"><div class="card border-0 shadow-sm h-100"><div class="card-body"><div class="text-muted small">Confirmed refunds</div><div class="fs-3 fw-semibold">{{ number_format($summary['refunds'], 2) }}</div></div></div></div>
        <div class="col-xl col-md-4"><div class="card border-0 shadow-sm h-100"><div class="card-body"><div class="text-muted small">Confirmed supplier credits</div><div class="fs-3 fw-semibold">{{ number_format($summary['credits'], 2) }}</div></div></div></div>
        <div class="col-xl col-md-4"><div class="card border-0 shadow-sm h-100"><div class="card-body"><div class="text-muted small">Replacement units received</div><div class="fs-3 fw-semibold">{{ number_format($summary['replacement_units']) }}</div></div></div></div>
    </div>

    <div class="card border-0 shadow-sm mb-4"><div class="card-body">
        <h5 class="mb-3">Record a supplier settlement</h5>
        <form method="POST" action="{{ route('supplier-return-settlements.store') }}" class="row g-3">
            @csrf
            <div class="col-lg-4"><label class="form-label">Resolved supplier return</label><select name="supplier_return_id" class="form-select" required><option value="">Select return</option>@foreach($eligibleReturns as $return)<option value="{{ $return->id }}" @selected(old('supplier_return_id') == $return->id)>{{ $return->return_number }} — {{ $return->purchase?->supplier?->name ?? 'Supplier' }} — {{ $return->item?->product?->name ?? 'Product' }} ({{ $return->quantity }} units)</option>@endforeach</select><div class="form-text">Only returns already resolved in Phase 26 are listed.</div></div>
            <div class="col-lg-2"><label class="form-label">Settlement type</label><select name="settlement_type" id="settlement_type" class="form-select" required><option value="REFUND">Refund</option><option value="CREDIT">Supplier credit</option><option value="REPLACEMENT">Replacement</option></select></div>
            <div class="col-lg-2"><label class="form-label">Amount</label><input type="number" name="amount" min="0" step="0.01" class="form-control" placeholder="0.00"></div>
            <div class="col-lg-2"><label class="form-label">Replacement qty</label><input type="number" name="replacement_quantity" min="0" step="1" class="form-control" placeholder="0"></div>
            <div class="col-lg-2"><label class="form-label">Settlement date</label><input type="date" name="settlement_date" class="form-control" value="{{ old('settlement_date', now()->toDateString()) }}"></div>
            <div class="col-lg-4"><label class="form-label">Reference number</label><input name="reference_number" maxlength="120" class="form-control" value="{{ old('reference_number') }}" placeholder="Credit note / refund / delivery reference"></div>
            <div class="col-lg-6"><label class="form-label">Notes</label><input name="notes" maxlength="3000" class="form-control" value="{{ old('notes') }}" placeholder="Settlement details"></div>
            <div class="col-lg-2 d-flex align-items-end"><button class="btn btn-primary w-100">Save pending record</button></div>
        </form>
    </div></div>

    <div class="card border-0 shadow-sm"><div class="card-body">
        <form method="GET" action="{{ route('supplier-return-settlements.index') }}" class="row g-2 mb-3">
            <div class="col-md-4"><input name="q" class="form-control" value="{{ request('q') }}" placeholder="Search return, supplier or reference"></div>
            <div class="col-md-3"><select name="status" class="form-select"><option value="">All statuses</option>@foreach(['PENDING','CONFIRMED','CANCELLED'] as $status)<option value="{{ $status }}" @selected(request('status') === $status)>{{ $status }}</option>@endforeach</select></div>
            <div class="col-md-3"><select name="type" class="form-select"><option value="">All types</option>@foreach(['REFUND','CREDIT','REPLACEMENT'] as $type)<option value="{{ $type }}" @selected(request('type') === $type)>{{ $type }}</option>@endforeach</select></div>
            <div class="col-md-2 d-flex gap-2"><button class="btn btn-outline-primary">Filter</button><a href="{{ route('supplier-return-settlements.index') }}" class="btn btn-outline-secondary">Reset</a></div>
        </form>
        <div class="table-responsive"><table class="table table-hover align-middle"><thead><tr><th>Return</th><th>Supplier / Product</th><th>Type</th><th>Amount</th><th>Replacement qty</th><th>Reference</th><th>Date</th><th>Status</th><th>Action</th></tr></thead><tbody>
        @forelse($settlements as $settlement)
            @php($return = $settlement->supplierReturn)
            <tr><td><strong>{{ $return?->return_number ?? '—' }}</strong><div class="small text-muted">{{ $return?->status }}</div></td><td>{{ $return?->purchase?->supplier?->name ?? '—' }}<div class="small text-muted">{{ $return?->item?->product?->name ?? '—' }}</div></td><td>{{ $settlement->settlement_type }}</td><td>{{ number_format((float)$settlement->amount, 2) }}</td><td>{{ $settlement->replacement_quantity ?: '—' }}</td><td>{{ $settlement->reference_number ?: '—' }}</td><td>{{ $settlement->settlement_date?->format('d M Y') ?? '—' }}</td><td><span class="badge text-bg-{{ $settlement->status === 'CONFIRMED' ? 'success' : ($settlement->status === 'PENDING' ? 'warning' : 'secondary') }}">{{ $settlement->status }}</span></td><td>@if($settlement->status === 'PENDING')<div class="d-flex gap-1"><form method="POST" action="{{ route('supplier-return-settlements.confirm', $settlement) }}">@csrf @method('PATCH')<button class="btn btn-sm btn-success" onclick="return confirm('Confirm this settlement record?')">Confirm</button></form><form method="POST" action="{{ route('supplier-return-settlements.cancel', $settlement) }}">@csrf @method('PATCH')<button class="btn btn-sm btn-outline-danger" onclick="return confirm('Cancel this pending record?')">Cancel</button></form></div>@else<span class="small text-muted">—</span>@endif</td></tr>
        @empty<tr><td colspan="9" class="text-center text-muted py-4">No supplier settlement records found.</td></tr>@endforelse
        </tbody></table></div>
        {{ $settlements->links() }}
    </div></div>
    <div class="alert alert-info mt-3 mb-0"><strong>Posting safeguard:</strong> This module records settlement evidence only. It does not post accounting entries, change supplier balances, or receive replacement stock into inventory. Record actual stock receipt through your normal purchase receiving workflow.</div>
</div>
@endsection
