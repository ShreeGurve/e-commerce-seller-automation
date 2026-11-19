@extends('layouts.app')
@section('title', 'Marketplace Bank Payout Reconciliation')
@section('subtitle', 'Compare marketplace settlement totals with credits received in your bank account')
@section('content')
<div class="row g-3 mb-3">
    @foreach ([['All records', $summary['total']], ['Matched', $summary['matched']], ['Unmatched', $summary['unmatched']], ['Amount mismatch', $summary['mismatch']]] as [$label, $value])
        <div class="col-6 col-xl-3"><div class="card h-100"><div class="card-body"><div class="small text-muted">{{ $label }}</div><div class="fs-3 fw-semibold">{{ number_format($value) }}</div></div></div></div>
    @endforeach
</div>
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
<div class="card mb-3">
    <div class="card-header fw-semibold">Record a bank payout</div>
    <div class="card-body">
        <form method="POST" action="{{ route('finance.payout-reconciliation.store') }}" class="row g-3">@csrf
            <div class="col-md-3"><label class="form-label">Marketplace</label><select name="marketplace_id" class="form-select" required><option value="">Select marketplace</option>@foreach($marketplaces as $m)<option value="{{ $m->id }}" @selected(old('marketplace_id')==$m->id)>{{ $m->name }}</option>@endforeach</select></div>
            <div class="col-md-3"><label class="form-label">Settlement (optional)</label><select name="settlement_id" class="form-select"><option value="">Link later</option>@foreach($settlements as $s)<option value="{{ $s->id }}" @selected(old('settlement_id')==$s->id)>{{ $s->marketplace->name }} · {{ $s->external_settlement_id }} · ₹{{ number_format($s->total_amount,2) }}</option>@endforeach</select></div>
            <div class="col-md-3"><label class="form-label">Bank reference / UTR</label><input name="bank_reference" class="form-control" maxlength="100" value="{{ old('bank_reference') }}" required></div>
            <div class="col-md-3"><label class="form-label">Bank credit amount (₹)</label><input name="bank_amount" type="number" step="0.01" class="form-control" value="{{ old('bank_amount') }}" required></div>
            <div class="col-md-3"><label class="form-label">Payout date</label><input name="payout_date" type="date" class="form-control" value="{{ old('payout_date') }}"></div>
            <div class="col-md-3"><label class="form-label">External settlement ID (optional)</label><input name="external_settlement_id" class="form-control" maxlength="60" value="{{ old('external_settlement_id') }}"></div>
            <input type="hidden" name="currency" value="INR">
            <div class="col-md-4"><label class="form-label">Review note</label><input name="note" class="form-control" maxlength="500" value="{{ old('note') }}"></div>
            <div class="col-md-2 d-flex align-items-end"><button class="btn btn-primary w-100">Save payout</button></div>
        </form>
        <div class="small text-muted mt-2">This screen records a bank credit for reconciliation only. It does not initiate or alter a marketplace payout.</div>
    </div>
</div>
<div class="d-flex flex-wrap gap-2 align-items-center mb-2">
    <form method="GET" class="d-flex gap-2"><select name="status" class="form-select form-select-sm" onchange="this.form.submit()"><option value="all">All statuses</option>@foreach(['UNMATCHED','MATCHED','MISMATCH','IGNORED'] as $st)<option value="{{ $st }}" @selected(request('status','all')===$st)>{{ $st }}</option>@endforeach</select><select name="marketplace_id" class="form-select form-select-sm" onchange="this.form.submit()"><option value="">All marketplaces</option>@foreach($marketplaces as $m)<option value="{{ $m->id }}" @selected(request('marketplace_id')==$m->id)>{{ $m->name }}</option>@endforeach</select></form>
</div>
<div class="card"><div class="table-responsive"><table class="table table-hover align-middle mb-0">
    <thead><tr><th>Date / Reference</th><th>Marketplace</th><th>Settlement</th><th class="text-end">Settlement total</th><th class="text-end">Bank credit</th><th class="text-end">Difference</th><th>Status</th><th>Action</th></tr></thead>
    <tbody>
    @forelse($payouts as $p)
        <tr>
            <td><div class="fw-semibold">{{ $p->bank_reference }}</div><div class="small text-muted">{{ $p->payout_date?->format('d M Y') ?? 'Date not set' }}</div></td>
            <td>{{ $p->marketplace->name }}</td>
            <td>{{ $p->settlement?->external_settlement_id ?? $p->external_settlement_id ?? 'Not linked' }}</td>
            <td class="text-end">{{ $p->settlement_amount !== null ? '₹'.number_format($p->settlement_amount,2) : '—' }}</td>
            <td class="text-end">₹{{ number_format($p->bank_amount,2) }}</td>
            <td class="text-end {{ abs((float)($p->difference ?? 0)) > 1 ? 'text-danger fw-semibold' : '' }}">{{ $p->difference !== null ? '₹'.number_format($p->difference,2) : '—' }}</td>
            <td><span class="badge text-bg-{{ $p->status==='MATCHED'?'success':($p->status==='MISMATCH'?'danger':($p->status==='IGNORED'?'secondary':'warning')) }}">{{ $p->status }}</span></td>
            <td class="text-nowrap">
                @if(in_array($p->status,['UNMATCHED','MISMATCH']))
                    <form method="POST" action="{{ route('finance.payout-reconciliation.link',$p) }}" class="d-flex gap-1">@csrf
                        <select name="settlement_id" class="form-select form-select-sm" required><option value="">Link settlement</option>@foreach($settlements->where('marketplace_id',$p->marketplace_id) as $s)<option value="{{ $s->id }}">{{ $s->external_settlement_id }} · ₹{{ number_format($s->total_amount,2) }}</option>@endforeach</select><button class="btn btn-sm btn-outline-primary">Link</button>
                    </form>
                @elseif($p->status==='IGNORED')<form method="POST" action="{{ route('finance.payout-reconciliation.reopen',$p) }}">@csrf<button class="btn btn-sm btn-outline-secondary">Reopen</button></form>@endif
                @if($p->status!=='MATCHED' && $p->status!=='IGNORED')<form method="POST" action="{{ route('finance.payout-reconciliation.ignore',$p) }}" class="mt-1">@csrf<input type="hidden" name="note" value="Reviewed and intentionally excluded"><button class="btn btn-sm btn-link text-muted p-0">Ignore</button></form>@endif
            </td>
        </tr>
    @empty<tr><td colspan="8" class="text-center text-muted py-5">No bank payout records yet. Record a bank credit above to start matching.</td></tr>@endforelse
    </tbody>
</table></div><div class="card-body">{{ $payouts->links() }}</div></div>
@endsection
