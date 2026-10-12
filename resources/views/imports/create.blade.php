@extends('layouts.app')
@section('title','New import')
@section('subtitle','Step 1 of 4: choose channel, data type and file')
@section('content')
@include('imports._steps',['step'=>1])
<div class="card"><div class="card-body"><form method="POST" action="{{ route('imports.store') }}" enctype="multipart/form-data">@csrf
  <div class="row g-3">
    <div class="col-md-4"><label class="form-label">Channel *</label><select name="marketplace_id" class="form-select" required>@foreach($marketplaces as $m)<option value="{{ $m->id }}" @selected(old('marketplace_id',$pickMp)==$m->id)>{{ $m->name }}</option>@endforeach</select></div>
    <div class="col-md-4"><label class="form-label">What are you importing? *</label><select name="data_type" id="dt" class="form-select" required>
      @foreach(\App\Models\DataImport::TYPES as $k=>$t)<option value="{{ $k }}" @selected(old('data_type',$pickType)===$k)>{{ $t['label'] }}</option>@endforeach</select></div>
    <div class="col-md-4"><label class="form-label">File * <span class="text-muted small">(.csv .txt .tsv .xlsx, max 20,000 rows)</span></label><input type="file" name="file" class="form-control" accept=".csv,.txt,.tsv,.xlsx,.xls" required></div>
    <div class="col-md-4"><label class="form-label">Date format in file</label><select name="date_format" class="form-select">@foreach(\App\Models\DataImport::DATE_FORMATS as $k=>$l)<option value="{{ $k }}" @selected(old('date_format','auto')===$k)>{{ $l }}</option>@endforeach</select>
      <div class="form-text">Use DD/MM/YYYY if your dates look like 05/10/2026.</div></div>
    <div class="col-md-4 opt-orders"><label class="form-label">Price column means</label><select name="price_mode" class="form-select">
      <option value="unit" @selected(old('price_mode')==='unit')>Price of ONE unit</option><option value="line" @selected(old('price_mode')==='line')>Total for the whole line (qty × price)</option></select>
      <div class="form-text">Amazon "item-price" is usually the line total.</div></div>
    <div class="col-md-4 opt-orders"><label class="form-label d-block">Stock</label>
      <div class="form-check form-switch mt-2"><input class="form-check-input" type="checkbox" name="deduct_stock" value="1" id="ds" @checked(old('deduct_stock',true))><label class="form-check-label" for="ds">Deduct stock for these orders</label></div>
      <div class="form-text">Turn OFF for old history that happened before your opening stock, or stock will go negative.</div></div>
    <div class="col-md-8 opt-settle"><label class="form-label d-block">Fee &amp; refund numbers</label>
      <div class="form-check form-switch mt-2"><input class="form-check-input" type="checkbox" name="deductions_positive" value="1" id="dp" @checked(old('deductions_positive',true))><label class="form-check-label" for="dp">Shown as POSITIVE numbers in my file (e.g. commission 25.00 means ₹25 was deducted)</label></div>
      <div class="form-text">Applies to files with one column per fee (Flipkart style). Files with a single signed "Amount" column (Amazon style) are used as they are.</div></div>
  </div>
  <div class="alert alert-info mt-3 mb-0 small">Importing the same file twice is safe: orders, returns and SKUs that already exist are skipped, never duplicated. Unknown SKUs are reported, not guessed. Import <b>SKU mappings</b> first, then <b>Orders</b>, then <b>Returns</b>, then <b>Settlements</b>.</div>
  <div class="mt-4"><button class="btn btn-primary">Upload &amp; continue</button> <a href="{{ route('imports.index') }}" class="btn btn-link">Cancel</a></div>
</form></div></div>
@push('scripts')<script>const dt=document.getElementById('dt'),t=()=>document.querySelectorAll('.opt-orders').forEach(e=>e.style.display=dt.value==='ORDERS'?'':'none');document.querySelectorAll('.opt-settle').forEach(e=>e.style.display=dt.value==='SETTLEMENTS'?'':'none');dt.onchange=t;t();</script>@endpush
@endsection
