@extends('layouts.app')
@section('title', 'Purchase Quality Control')
@section('content')
<div class="container-fluid py-3">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
        <div><h1 class="h3 mb-1">Purchase Quality Control</h1><p class="text-muted mb-0">Inspect received goods and manage supplier follow-ups.</p></div>
        <a href="{{ route('purchase-quality.create') }}" class="btn btn-primary">Record Inspection</a>
    </div>
    <div class="row g-3 mb-4">
        @foreach([['Inspection records',$summary['records']],['Rejected units',$summary['rejected']],['Open follow-ups',$summary['open']],['Resolved',$summary['resolved']]] as [$label,$value])
        <div class="col-6 col-xl-3"><div class="card h-100"><div class="card-body"><div class="text-muted small">{{ $label }}</div><div class="fs-3 fw-semibold">{{ number_format($value) }}</div></div></div></div>
        @endforeach
    </div>
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    <div class="card"><div class="card-body">
        <form method="GET" class="row g-2 mb-3">
            <div class="col-md-4"><select name="supplier_id" class="form-select"><option value="">All suppliers</option>@foreach($suppliers as $supplier)<option value="{{ $supplier->id }}" @selected(request('supplier_id')==$supplier->id)>{{ $supplier->name }}</option>@endforeach</select></div>
            <div class="col-md-3"><select name="status" class="form-select"><option value="">All statuses</option><option value="OPEN" @selected(request('status')==='OPEN')>Open</option><option value="PASSED" @selected(request('status')==='PASSED')>Passed</option><option value="RESOLVED" @selected(request('status')==='RESOLVED')>Resolved</option></select></div>
            <div class="col-md-2"><button class="btn btn-outline-primary w-100">Filter</button></div>
            <div class="col-md-2"><a href="{{ route('purchase-quality.index') }}" class="btn btn-light w-100">Reset</a></div>
        </form>
        <div class="table-responsive"><table class="table align-middle">
            <thead><tr><th>Date</th><th>Purchase / Supplier</th><th>Product</th><th class="text-end">Inspected</th><th class="text-end">Accepted</th><th class="text-end">Rejected</th><th>Defect</th><th>Status / Follow-up</th></tr></thead>
            <tbody>
            @forelse($inspections as $inspection)
                <tr>
                    <td>{{ $inspection->inspection_date?->format('d M Y') }}</td>
                    <td><a href="{{ route('purchases.show',$inspection->purchase_id) }}">{{ $inspection->purchase?->po_number }}</a><div class="small text-muted">{{ $inspection->purchase?->supplier?->name }}</div></td>
                    <td>{{ $inspection->item?->product?->name ?? $inspection->item?->product?->sku }}</td>
                    <td class="text-end">{{ number_format($inspection->inspected_qty) }}</td><td class="text-end">{{ number_format($inspection->accepted_qty) }}</td><td class="text-end text-danger">{{ number_format($inspection->rejected_qty) }}</td>
                    <td>{{ $inspection->defect_type ?: '—' }}</td>
                    <td>
                        <span class="badge text-bg-{{ $inspection->status==='RESOLVED' || $inspection->status==='PASSED' ? 'success' : 'warning' }}">{{ $inspection->status }}</span>
                        @if($inspection->rejected_qty>0)
                        <form method="POST" action="{{ route('purchase-quality.update',$inspection) }}" class="mt-2">
                            @csrf @method('PATCH')
                            <select name="supplier_action" class="form-select form-select-sm mb-1">
                                @foreach(['PENDING'=>'Pending','REPLACEMENT'=>'Replacement','REFUND'=>'Refund','CREDIT'=>'Credit','NO_ACTION'=>'No action'] as $key=>$label)<option value="{{ $key }}" @selected($inspection->supplier_action===$key)>{{ $label }}</option>@endforeach
                            </select>
                            <select name="status" class="form-select form-select-sm mb-1"><option value="OPEN" @selected($inspection->status==='OPEN')>Open</option><option value="RESOLVED" @selected($inspection->status==='RESOLVED')>Resolved</option></select>
                            <input name="notes" class="form-control form-control-sm mb-1" value="{{ $inspection->notes }}" placeholder="Follow-up note">
                            <button class="btn btn-sm btn-outline-primary">Update</button>
                        </form>
                        @else <div class="small text-muted">{{ $inspection->supplier_action }}</div>@endif
                    </td>
                </tr>
            @empty<tr><td colspan="8" class="text-center text-muted py-4">No quality inspections recorded yet.</td></tr>@endforelse
            </tbody>
        </table></div>
        {{ $inspections->links() }}
    </div></div>
</div>
@endsection
