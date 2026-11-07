@extends('layouts.app')
@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3"><div><h1 class="h3 mb-1">Inventory Ledger Explorer</h1><p class="text-muted mb-0">Search and audit immutable stock movement records.</p></div><a class="btn btn-outline-primary" href="{{ route('inventory.ledger.export', request()->query()) }}">Export CSV</a></div>
    <div class="row g-3 mb-3">
        <div class="col-md-4"><div class="card card-body"><small class="text-muted">Ledger Entries</small><strong class="h3">{{ number_format($summary['rows']) }}</strong></div></div>
        <div class="col-md-4"><div class="card card-body"><small class="text-muted">Units In</small><strong class="h3 text-success">{{ number_format($summary['units_in']) }}</strong></div></div>
        <div class="col-md-4"><div class="card card-body"><small class="text-muted">Units Out</small><strong class="h3 text-danger">{{ number_format($summary['units_out']) }}</strong></div></div>
    </div>
    <form class="row g-2 mb-3">
        <div class="col-md-3"><input name="q" value="{{ request('q') }}" class="form-control" placeholder="Product name or SKU"></div>
        <div class="col-md-2"><select name="type" class="form-select"><option value="">All movement types</option>@foreach(\App\Models\InventoryTransaction::TYPES as $key=>$type)<option value="{{ $key }}" @selected(request('type')===$key)>{{ $type['label'] }}</option>@endforeach</select></div>
        <div class="col-md-2"><input type="date" name="from" value="{{ request('from') }}" class="form-control"></div><div class="col-md-2"><input type="date" name="to" value="{{ request('to') }}" class="form-control"></div>
        <div class="col-md-3"><button class="btn btn-primary">Filter</button> <a class="btn btn-outline-secondary" href="{{ route('inventory.ledger') }}">Reset</a></div>
    </form>
    <div class="card"><div class="table-responsive"><table class="table table-hover mb-0"><thead><tr><th>Date</th><th>Product</th><th>Type</th><th>Available Δ</th><th>Damaged Δ</th><th>Balance</th><th>Unit Cost</th><th>User</th><th>Note</th></tr></thead><tbody>
    @forelse($transactions as $t)<tr><td>{{ $t->created_at?->format('d M Y H:i') }}</td><td>{{ $t->product?->name ?? 'Deleted product' }}<div class="small text-muted">{{ $t->product?->sku }}</div></td><td>{{ \App\Models\InventoryTransaction::TYPES[$t->type]['label'] ?? $t->type }}</td><td class="{{ $t->qty_change<0?'text-danger':'text-success' }}">{{ $t->qty_change>0?'+':'' }}{{ $t->qty_change }}</td><td>{{ $t->damaged_change }}</td><td>{{ $t->balance_after }}</td><td>{{ $t->unit_cost }}</td><td>{{ $t->user?->name ?? '—' }}</td><td>{{ $t->note }}</td></tr>
    @empty<tr><td colspan="9" class="text-center text-muted py-4">No ledger entries match these filters.</td></tr>@endforelse
    </tbody></table></div></div><div class="mt-3">{{ $transactions->links() }}</div>
</div>
@endsection
