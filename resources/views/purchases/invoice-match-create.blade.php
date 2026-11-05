@extends('layouts.app')
@section('title', 'Record Supplier Invoice')
@section('subtitle', 'Capture invoice values and compare with the selected purchase order')
@section('content')
<div class="d-flex justify-content-between align-items-center mb-3"><div><h3>Record Supplier Invoice</h3><p class="text-muted mb-0">Enter the supplier invoice totals exactly as printed on the invoice.</p></div><a href="{{ route('purchase-invoice-matching.index') }}" class="btn btn-outline-secondary">Back to matching</a></div>
@if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
<div class="card"><div class="card-body"><form method="POST" action="{{ route('purchase-invoice-matching.store') }}">@csrf
<div class="row g-3"><div class="col-md-6"><label class="form-label">Purchase order</label><select name="purchase_id" class="form-select" required><option value="">Select an eligible PO</option>@foreach($purchases as $p)<option value="{{ $p->id }}" @selected(old('purchase_id',request('purchase_id'))==$p->id)>{{ $p->po_number }} · {{ $p->supplier->name ?? 'Supplier' }} · ₹{{ number_format((float)$p->total_amount,2) }}</option>@endforeach</select><div class="form-text">Only ordered/received POs without an existing invoice match are shown.</div></div>
<div class="col-md-3"><label class="form-label">Supplier invoice number</label><input name="supplier_invoice_number" class="form-control" maxlength="100" value="{{ old('supplier_invoice_number') }}" required></div><div class="col-md-3"><label class="form-label">Invoice date</label><input type="date" name="supplier_invoice_date" class="form-control" value="{{ old('supplier_invoice_date',date('Y-m-d')) }}" required></div>
@foreach(['invoice_subtotal'=>'Invoice subtotal','invoice_tax'=>'Tax / GST','invoice_shipping'=>'Shipping','invoice_other'=>'Other charges'] as $field=>$label)<div class="col-md-3"><label class="form-label">{{ $label }} (₹)</label><input type="number" step="0.01" min="0" name="{{ $field }}" class="form-control" value="{{ old($field,0) }}" required></div>@endforeach
<div class="col-md-3"><label class="form-label">Allowed variance (₹)</label><input type="number" step="0.01" min="0" name="tolerance_amount" class="form-control" value="{{ old('tolerance_amount','0.01') }}"><div class="form-text">Default ₹0.01</div></div>
<div class="col-12"><label class="form-label">Notes</label><textarea name="review_notes" class="form-control" rows="3">{{ old('review_notes') }}</textarea></div>
<div class="col-12"><button class="btn btn-primary">Save and compare invoice</button></div></div></form></div></div>
@endsection
