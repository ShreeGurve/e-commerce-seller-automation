@extends('layouts.app')
@section('content')
<div class="container-fluid py-3">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
        <div><h2 class="mb-1">Supplier Returns & Replacements</h2><p class="text-muted mb-0">Track rejected goods, supplier dispatches and financial resolutions.</p></div>
        <a href="{{ route('supplier-returns.create') }}" class="btn btn-primary">Create supplier return</a>
    </div>
    <div class="row g-3 mb-4">
        @foreach(['Open'=>'open','Dispatched'=>'dispatched','Resolved'=>'resolved','Units returned'=>'units'] as $label=>$key)
        <div class="col-6 col-xl-3"><div class="card border-0 shadow-sm h-100"><div class="card-body"><div class="text-muted small">{{ $label }}</div><div class="fs-3 fw-semibold">{{ number_format($summary[$key]) }}</div></div></div></div>
        @endforeach
    </div>
    <div class="card border-0 shadow-sm mb-3"><div class="card-body">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-5"><label class="form-label">Search</label><input name="q" value="{{ request('q') }}" class="form-control" placeholder="Return no., PO, supplier or reference"></div>
            <div class="col-md-3"><label class="form-label">Status</label><select name="status" class="form-select"><option value="">All statuses</option>@foreach(['DRAFT','APPROVED','DISPATCHED','REPLACED','REFUNDED','CREDITED','CLOSED','CANCELLED'] as $v)<option value="{{ $v }}" @selected(request('status')===$v)>{{ $v }}</option>@endforeach</select></div>
            <div class="col-md-2"><label class="form-label">Resolution</label><select name="resolution" class="form-select"><option value="">All</option>@foreach(['REPLACEMENT','REFUND','CREDIT'] as $v)<option value="{{ $v }}" @selected(request('resolution')===$v)>{{ $v }}</option>@endforeach</select></div>
            <div class="col-md-2 d-flex gap-2"><button class="btn btn-outline-primary">Filter</button><a href="{{ route('supplier-returns.index') }}" class="btn btn-light">Reset</a></div>
        </form>
    </div></div>
    <div class="card border-0 shadow-sm"><div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead class="table-light"><tr><th>Return</th><th>Supplier / PO</th><th>Product</th><th>Qty</th><th>Reason</th><th>Resolution</th><th>Status</th><th>Update</th></tr></thead><tbody>
    @forelse($returns as $r)
    <tr><td><div class="fw-semibold">{{ $r->return_number }}</div><small class="text-muted">{{ optional($r->return_date)->format('d M Y') }}</small></td><td>{{ $r->purchase?->supplier?->name ?? '—' }}<br><small class="text-muted">{{ $r->purchase?->po_number }}</small></td><td>{{ $r->item?->product?->name ?? 'Product unavailable' }}<br><small class="text-muted">{{ $r->item?->product?->sku }}</small></td><td>{{ number_format($r->quantity) }}</td><td>{{ $r->reason }}</td><td>{{ $r->resolution }}</td><td><span class="badge bg-{{ in_array($r->status,['CLOSED','REPLACED','REFUNDED','CREDITED'])?'success':($r->status==='CANCELLED'?'secondary':($r->status==='DISPATCHED'?'info':'warning')) }}">{{ $r->status }}</span></td><td>
    @if(!in_array($r->status,['CLOSED','CANCELLED']))<form method="POST" action="{{ route('supplier-returns.status',$r) }}" class="d-flex flex-column gap-1">@csrf @method('PATCH')<select name="status" class="form-select form-select-sm" required><option value="">Change status</option>@foreach(['DRAFT'=>['APPROVED','CANCELLED'],'APPROVED'=>['DISPATCHED','CANCELLED'],'DISPATCHED'=>['REPLACED','REFUNDED','CREDITED','CLOSED'],'REPLACED'=>['CLOSED'],'REFUNDED'=>['CLOSED'],'CREDITED'=>['CLOSED']][$r->status] ?? [] as $next)<option value="{{ $next }}">{{ $next }}</option>@endforeach</select><input name="supplier_reference" class="form-control form-control-sm" placeholder="Supplier reference" value="{{ $r->supplier_reference }}"><button class="btn btn-sm btn-outline-primary">Save</button></form>@else<small class="text-muted">Final</small>@endif
    </td></tr>
    @empty<tr><td colspan="8" class="text-center py-5 text-muted">No supplier returns recorded yet.</td></tr>@endforelse
    </tbody></table></div><div class="card-body">{{ $returns->links() }}</div></div>
</div>
@endsection
