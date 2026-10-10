@extends('layouts.app')
@section('title','Settings')
@section('subtitle','Business details and system defaults')
@section('content')
<form method="POST" action="{{ route('settings.update') }}">@csrf @method('PUT')
<div class="row g-3">
  <div class="col-lg-6"><div class="card h-100"><div class="card-header">Business</div><div class="card-body">
    <div class="mb-3"><label class="form-label">Business / brand name *</label><input name="name" value="{{ old('name',$business->name) }}" class="form-control" required></div>
    <div class="mb-3"><label class="form-label">Legal name</label><input name="legal_name" value="{{ old('legal_name',$business->legal_name) }}" class="form-control"></div>
    <div class="mb-3"><label class="form-label">GSTIN</label><input name="gstin" value="{{ old('gstin',$business->gstin) }}" class="form-control text-uppercase" maxlength="15" placeholder="15-character GST number"></div>
    <div class="row g-2"><div class="col"><label class="form-label">Currency</label><input class="form-control" value="{{ $business->currency }} (₹)" disabled></div>
      <div class="col"><label class="form-label">Timezone</label><input class="form-control" value="{{ config('app.timezone') }}" disabled></div></div>
    <div class="form-text mt-2">Timezone comes from <code>APP_TIMEZONE</code> in <code>.env</code>. API keys and passwords also stay in <code>.env</code>, never here.</div>
  </div></div></div>
  <div class="col-lg-6"><div class="card h-100"><div class="card-header">Defaults &amp; thresholds</div><div class="card-body">
    @foreach($defaults as $key => [$label,$group,$type,$rule,$help])
      <div class="mb-3"><label class="form-label">{{ $label }}</label>
        @if($key==='default_marketplace')
          <select name="{{ $key }}" class="form-select"><option value="">—</option>@foreach($marketplaces as $m)<option value="{{ $m->code }}" @selected(old($key,$values[$key])===$m->code)>{{ $m->name }}</option>@endforeach</select>
        @else
          <input name="{{ $key }}" type="number" step="{{ $type==='int'?'1':'0.01' }}" min="0" value="{{ old($key,$values[$key]) }}" class="form-control" required>
        @endif
        <div class="form-text">{{ $help }}</div></div>
    @endforeach
  </div></div></div>
</div>
<div class="mt-3"><button class="btn btn-primary">Save settings</button></div>
</form>
@endsection
