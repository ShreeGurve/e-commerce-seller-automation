@php $f = fn($k,$d=null) => old($k, $product->{$k} ?? $d); @endphp
<div class="row g-3">
  <div class="col-md-4"><label class="form-label">SKU *</label><input name="sku" value="{{ $f('sku') }}" class="form-control" required></div>
  <div class="col-md-4"><label class="form-label">Parent SKU</label><input name="parent_sku" value="{{ $f('parent_sku') }}" class="form-control"></div>
  <div class="col-md-4"><label class="form-label">Status *</label><select name="status" class="form-select">
    @foreach(\App\Models\Product::STATUSES as $s)<option value="{{ $s }}" @selected($f('status','active')==$s)>{{ ucfirst($s) }}</option>@endforeach</select></div>
  <div class="col-12"><label class="form-label">Product name *</label><input name="name" value="{{ $f('name') }}" class="form-control" required></div>
  <div class="col-md-4"><label class="form-label">Brand</label><select name="brand_id" class="form-select"><option value="">—</option>
    @foreach($brands as $b)<option value="{{ $b->id }}" @selected($f('brand_id', $brands->first()->id ?? null)==$b->id)>{{ $b->name }}</option>@endforeach</select></div>
  <div class="col-md-4"><label class="form-label">Category</label><select name="category_id" class="form-select"><option value="">—</option>
    @foreach($categories as $c)<option value="{{ $c->id }}" @selected($f('category_id')==$c->id)>{{ $c->name }}</option>@endforeach</select></div>
  <div class="col-md-4"><label class="form-label">Subcategory</label><input name="subcategory" value="{{ $f('subcategory') }}" class="form-control"></div>
  <div class="col-md-4"><label class="form-label">Product type</label><input name="product_type" value="{{ $f('product_type') }}" class="form-control"></div>
  <div class="col-md-4"><label class="form-label">HSN</label><input name="hsn" value="{{ $f('hsn') }}" class="form-control"></div>
  <div class="col-md-4"><label class="form-label">GST %</label><input name="gst_rate" type="number" step="0.01" value="{{ $f('gst_rate') }}" class="form-control"></div>
  <div class="col-12"><h6 class="mt-2 border-bottom pb-1">Pricing (₹)</h6></div>
  @foreach(['mrp'=>'MRP','default_selling_price'=>'Default selling price','min_selling_price'=>'Minimum selling price','target_selling_price'=>'Target selling price'] as $k=>$l)
    <div class="col-md-3"><label class="form-label">{{ $l }}</label><input name="{{ $k }}" type="number" step="0.01" min="0" value="{{ $f($k) }}" class="form-control"></div>
  @endforeach
  @if(!$product->exists)
    <div class="col-12"><h6 class="mt-2 border-bottom pb-1">Initial costs (₹) <small class="text-muted">— later changes are tracked in cost history</small></h6></div>
    @foreach(\App\Models\ProductCostHistory::TYPES as $k=>$l)
      <div class="col-md"><label class="form-label">{{ $l }}</label><input name="costs[{{ $k }}]" type="number" step="0.01" min="0" value="{{ old('costs.'.$k) }}" class="form-control"></div>
    @endforeach
  @endif
  <div class="col-12"><h6 class="mt-2 border-bottom pb-1">Dimensions</h6></div>
  @foreach(['weight_g'=>'Weight (g)','length_cm'=>'Length (cm)','width_cm'=>'Width (cm)','height_cm'=>'Height (cm)'] as $k=>$l)
    <div class="col-md-3"><label class="form-label">{{ $l }}</label><input name="{{ $k }}" type="number" step="0.01" min="0" value="{{ $f($k) }}" class="form-control"></div>
  @endforeach
  <div class="col-md-6"><label class="form-label">Description</label><textarea name="description" rows="3" class="form-control">{{ $f('description') }}</textarea></div>
  <div class="col-md-6"><label class="form-label">Notes</label><textarea name="notes" rows="3" class="form-control">{{ $f('notes') }}</textarea></div>
</div>
