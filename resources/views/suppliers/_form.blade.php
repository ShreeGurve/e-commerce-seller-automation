@php $f = fn($k,$d=null) => old($k, $supplier->{$k} ?? $d); @endphp
<div class="row g-3">
  <div class="col-md-6"><label class="form-label">Supplier name *</label><input name="name" value="{{ $f('name') }}" class="form-control" required></div>
  <div class="col-md-3"><label class="form-label">Contact person</label><input name="contact_person" value="{{ $f('contact_person') }}" class="form-control"></div>
  <div class="col-md-3"><label class="form-label">Phone</label><input name="phone" value="{{ $f('phone') }}" class="form-control"></div>
  <div class="col-md-4"><label class="form-label">Email</label><input type="email" name="email" value="{{ $f('email') }}" class="form-control"></div>
  <div class="col-md-4"><label class="form-label">GSTIN</label><input name="gstin" value="{{ $f('gstin') }}" class="form-control text-uppercase"></div>
  <div class="col-md-4"><label class="form-label">Payment terms</label><input name="payment_terms" value="{{ $f('payment_terms') }}" class="form-control" placeholder="e.g. 15 days credit"></div>
  <div class="col-md-6"><label class="form-label">Address</label><input name="address" value="{{ $f('address') }}" class="form-control"></div>
  <div class="col-md-3"><label class="form-label">City</label><input name="city" value="{{ $f('city') }}" class="form-control"></div>
  <div class="col-md-3"><label class="form-label">State</label><input name="state" value="{{ $f('state') }}" class="form-control"></div>
  <div class="col-12"><label class="form-label">Notes</label><textarea name="notes" rows="2" class="form-control">{{ $f('notes') }}</textarea></div>
  <div class="col-12"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="is_active" value="1" id="act" @checked(old('is_active', $supplier->is_active ?? true))><label class="form-check-label" for="act">Active</label></div></div>
</div>
