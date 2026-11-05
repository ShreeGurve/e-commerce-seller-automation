@extends('layouts.app')
@section('content')
<div class="container-fluid py-3" style="max-width:1000px">
    <div class="mb-4"><a href="{{ route('supplier-returns.index') }}" class="text-decoration-none">← Supplier returns</a><h2 class="mt-2 mb-1">Create supplier return</h2><p class="text-muted">Create a return from a recorded quality inspection. This does not alter inventory.</p></div>
    <div class="card border-0 shadow-sm"><div class="card-body p-4">
        @if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
        @if($inspections->isEmpty())<div class="alert alert-info mb-0">There are no unallocated rejected quantities. Record a quality inspection with rejected units first.</div>@else
        <form method="POST" action="{{ route('supplier-returns.store') }}">@csrf
            <div class="mb-3"><label class="form-label">Rejected inspection *</label><select name="quality_inspection_id" class="form-select" required><option value="">Select inspection</option>@foreach($inspections as $i)@php($allocated=\App\Models\SupplierReturn::where('quality_inspection_id',$i->id)->whereNotIn('status',['CANCELLED'])->sum('quantity'))<option value="{{ $i->id }}" @selected(old('quality_inspection_id')==$i->id)>{{ $i->purchase?->supplier?->name }} · {{ $i->purchase?->po_number }} · {{ $i->item?->product?->name }} · rejected {{ $i->rejected_qty }} / available {{ $i->rejected_qty-$allocated }}</option>@endforeach</select></div>
            <div class="row g-3"><div class="col-md-4"><label class="form-label">Return date *</label><input type="date" name="return_date" class="form-control" value="{{ old('return_date',now()->toDateString()) }}" required></div><div class="col-md-4"><label class="form-label">Quantity *</label><input type="number" name="quantity" min="1" max="1000000" class="form-control" value="{{ old('quantity',1) }}" required></div><div class="col-md-4"><label class="form-label">Expected resolution *</label><select name="resolution" class="form-select" required>@foreach(['REPLACEMENT','REFUND','CREDIT'] as $v)<option value="{{ $v }}" @selected(old('resolution','REPLACEMENT')===$v)>{{ $v }}</option>@endforeach</select></div></div>
            <div class="row g-3 mt-1"><div class="col-md-6"><label class="form-label">Return reason *</label><input name="reason" class="form-control" maxlength="120" value="{{ old('reason') }}" placeholder="Damaged, defective, wrong item..." required></div><div class="col-md-6"><label class="form-label">Supplier reference</label><input name="supplier_reference" class="form-control" maxlength="100" value="{{ old('supplier_reference') }}" placeholder="RMA / email / ticket reference"></div></div>
            <div class="mt-3"><label class="form-label">Notes</label><textarea name="notes" rows="3" class="form-control" maxlength="3000">{{ old('notes') }}</textarea></div>
            <div class="d-flex justify-content-end gap-2 mt-4"><a href="{{ route('supplier-returns.index') }}" class="btn btn-light">Cancel</a><button class="btn btn-primary">Save draft return</button></div>
        </form>@endif
    </div></div>
</div>
@endsection
