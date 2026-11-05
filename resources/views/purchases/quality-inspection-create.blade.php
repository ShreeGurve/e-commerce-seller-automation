@extends('layouts.app')
@section('title', 'Record Quality Inspection')
@section('content')
<div class="container-fluid py-3">
    <div class="mb-4"><a href="{{ route('purchase-quality.index') }}" class="text-decoration-none">← Quality Control</a><h1 class="h3 mt-2">Record Quality Inspection</h1><p class="text-muted">Only quantities already received against a purchase order can be inspected.</p></div>
    @if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <div class="card"><div class="card-body">
        @if($items->isEmpty())
            <div class="text-center py-5"><h5>No received quantities available for inspection</h5><p class="text-muted">Receive items on a purchase order first, or check whether all received units have already been inspected.</p></div>
        @else
        <form method="POST" action="{{ route('purchase-quality.store') }}" class="row g-3">@csrf
            <div class="col-12"><label class="form-label">Purchase item *</label><select name="purchase_item_id" class="form-select" required><option value="">Select received product</option>@foreach($items as $item)@php($remaining=(int)$item->received_qty-(int)\App\Models\PurchaseQualityInspection::where('purchase_item_id',$item->id)->sum('inspected_qty'))<option value="{{ $item->id }}" @selected(old('purchase_item_id')==$item->id)>{{ $item->purchase?->po_number }} — {{ $item->purchase?->supplier?->name }} — {{ $item->product?->name ?? $item->product?->sku }} ({{ $remaining }} remaining)</option>@endforeach</select></div>
            <div class="col-md-4"><label class="form-label">Inspection date *</label><input type="date" name="inspection_date" class="form-control" value="{{ old('inspection_date',now()->toDateString()) }}" required></div>
            <div class="col-md-4"><label class="form-label">Inspected quantity *</label><input type="number" min="1" name="inspected_qty" class="form-control" value="{{ old('inspected_qty') }}" required></div>
            <div class="col-md-4"><label class="form-label">Defect type</label><input name="defect_type" class="form-control" maxlength="100" value="{{ old('defect_type') }}" placeholder="Damaged, wrong item, quality..."></div>
            <div class="col-md-4"><label class="form-label">Accepted quantity *</label><input type="number" min="0" name="accepted_qty" class="form-control" value="{{ old('accepted_qty',0) }}" required></div>
            <div class="col-md-4"><label class="form-label">Rejected quantity *</label><input type="number" min="0" name="rejected_qty" class="form-control" value="{{ old('rejected_qty',0) }}" required></div>
            <div class="col-md-4"><label class="form-label">Supplier action *</label><select name="supplier_action" class="form-select">@foreach(['PENDING'=>'Pending','REPLACEMENT'=>'Replacement','REFUND'=>'Refund','CREDIT'=>'Credit','NO_ACTION'=>'No action'] as $key=>$label)<option value="{{ $key }}" @selected(old('supplier_action','PENDING')===$key)>{{ $label }}</option>@endforeach</select></div>
            <div class="col-12"><label class="form-label">Inspection notes</label><textarea name="notes" rows="3" class="form-control">{{ old('notes') }}</textarea></div>
            <div class="col-12 d-flex gap-2"><button class="btn btn-primary">Save Inspection</button><a href="{{ route('purchase-quality.index') }}" class="btn btn-light">Cancel</a></div>
        </form>
        @endif
    </div></div>
</div>
@endsection
