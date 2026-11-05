@extends('layouts.app')
@section('content')
<div class="container-fluid">
    <h1>Supplier Account Statements</h1>
    <p class="text-muted">Invoice-level outstanding view based on recorded invoice payments and applied supplier credits.</p>
    <form method="GET" class="row g-2 mb-3"><div class="col-md-4"><select class="form-select" name="supplier_id"><option value="">All suppliers</option>@foreach($suppliers as $supplier)<option value="{{ $supplier->id }}" @selected(request('supplier_id')==$supplier->id)>{{ $supplier->name }}</option>@endforeach</select></div><div class="col-md-2"><button class="btn btn-primary">Filter</button></div></form>
    <div class="table-responsive"><table class="table table-striped align-middle"><thead><tr><th>Supplier</th><th>PO</th><th>Invoice</th><th>Date</th><th class="text-end">Invoice ₹</th><th class="text-end">Paid ₹</th><th class="text-end">Credit ₹</th><th class="text-end">Outstanding ₹</th></tr></thead><tbody>
    @forelse($rows as $row)
        @php($balance=max(0,(float)$row->invoice_total-(float)$row->paid_amount-(float)$row->credit_applied))
        <tr><td>{{ $row->supplier_name }}</td><td>{{ $row->po_number }}</td><td>{{ $row->supplier_invoice_number }}</td><td>{{ \Illuminate\Support\Carbon::parse($row->supplier_invoice_date)->format('d M Y') }}</td><td class="text-end">{{ number_format((float)$row->invoice_total,2) }}</td><td class="text-end">{{ number_format((float)$row->paid_amount,2) }}</td><td class="text-end">{{ number_format((float)$row->credit_applied,2) }}</td><td class="text-end fw-bold">{{ number_format($balance,2) }}</td></tr>
    @empty<tr><td colspan="8" class="text-center text-muted">No supplier invoices found.</td></tr>@endforelse
    </tbody></table></div>{{ $rows->links() }}
</div>
@endsection
