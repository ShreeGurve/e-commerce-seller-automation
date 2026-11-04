@php($f = $order->fulfillment)
<div class="card mb-3">
 <div class="card-header d-flex justify-content-between align-items-center"><span>Fulfilment &amp; dispatch</span><a href="{{ route('fulfilment.index') }}" class="btn btn-sm btn-outline-secondary">Fulfilment queue</a></div>
 <div class="card-body">
  @if($order->fulfillment_type === 'MARKETPLACE')<div class="alert alert-info small">Marketplace-fulfilled order. Use this section to record references only; do not create a self-ship dispatch unless the marketplace instructs you to.</div>@endif
  @can('orders.edit')
  <form method="POST" action="{{ route('fulfilment.update',$order) }}" enctype="multipart/form-data" class="row g-2">@csrf @method('PUT')
   <div class="col-md-4"><label class="form-label">Package reference</label><input class="form-control" name="package_reference" value="{{ old('package_reference',$f?->package_reference) }}"></div>
   <div class="col-md-4"><label class="form-label">Weight (kg)</label><input class="form-control" type="number" step="0.001" min="0.001" name="weight_kg" value="{{ old('weight_kg',$f?->weight_kg) }}"></div>
   <div class="col-md-4"><label class="form-label">Dimensions (cm) L × W × H</label><div class="input-group"><input class="form-control" type="number" step="0.1" name="length_cm" placeholder="L" value="{{ old('length_cm',$f?->length_cm) }}"><input class="form-control" type="number" step="0.1" name="width_cm" placeholder="W" value="{{ old('width_cm',$f?->width_cm) }}"><input class="form-control" type="number" step="0.1" name="height_cm" placeholder="H" value="{{ old('height_cm',$f?->height_cm) }}"></div></div>
   <div class="col-md-4"><label class="form-label">Carrier</label><input class="form-control" name="carrier" value="{{ old('carrier',$f?->carrier) }}"></div>
   <div class="col-md-4"><label class="form-label">Service level</label><input class="form-control" name="service_level" value="{{ old('service_level',$f?->service_level) }}"></div>
   <div class="col-md-4"><label class="form-label">Tracking / AWB number</label><input class="form-control" name="tracking_number" value="{{ old('tracking_number',$f?->tracking_number) }}"></div>
   <div class="col-md-6"><label class="form-label">Tracking URL</label><input class="form-control" type="url" name="tracking_url" value="{{ old('tracking_url',$f?->tracking_url) }}"></div>
   <div class="col-md-6"><label class="form-label">Invoice number</label><input class="form-control" name="invoice_number" value="{{ old('invoice_number',$f?->invoice_number) }}"></div>
   <div class="col-md-6"><label class="form-label">Pickup reference</label><input class="form-control" name="pickup_reference" value="{{ old('pickup_reference',$f?->pickup_reference) }}"></div>
   <div class="col-md-6"><label class="form-label">Pickup scheduled at</label><input class="form-control" type="datetime-local" name="pickup_scheduled_at" value="{{ $f?->pickup_scheduled_at?->format('Y-m-d\TH:i') }}"></div>
   <div class="col-md-6"><label class="form-label">Shipping label (PDF/JPG/PNG, max 5 MB)</label><input class="form-control" type="file" name="label" accept=".pdf,.jpg,.jpeg,.png">@if($f?->label_path)<a class="small" href="{{ route('fulfilment.file',[$order,'label']) }}">View saved label</a>@endif</div>
   <div class="col-md-6"><label class="form-label">Invoice file (PDF/JPG/PNG, max 5 MB)</label><input class="form-control" type="file" name="invoice" accept=".pdf,.jpg,.jpeg,.png">@if($f?->invoice_path)<a class="small" href="{{ route('fulfilment.file',[$order,'invoice']) }}">View saved invoice</a>@endif</div>
   <div class="col-12"><label class="form-label">Packing notes</label><textarea class="form-control" name="packing_notes" rows="2">{{ old('packing_notes',$f?->packing_notes) }}</textarea></div>
   <div class="col-12"><button class="btn btn-outline-primary">Save fulfilment details</button></div>
  </form>
  @else
   <div class="row small"><div class="col-md-4">Package: {{ $f?->package_reference ?: '—' }}</div><div class="col-md-4">Weight: {{ $f?->weight_kg ? $f->weight_kg.' kg' : '—' }}</div><div class="col-md-4">Carrier: {{ $f?->carrier ?: '—' }}</div><div class="col-md-6">Tracking: {{ $f?->tracking_number ?: '—' }}</div><div class="col-md-6">Pickup: {{ $f?->pickup_scheduled_at?->format('d M Y H:i') ?: '—' }}</div></div>
  @endcan
  @if($order->status === 'CONFIRMED' && $order->fulfillment_type === 'SELF' && auth()->user()->can('orders.edit'))
   <form method="POST" action="{{ route('fulfilment.pack',$order) }}" class="mt-3">@csrf<button class="btn btn-primary">Mark as packed</button><span class="small text-muted ms-2">Requires package weight and L/W/H.</span></form>
  @endif
  @if($order->status === 'PACKED' && $order->fulfillment_type === 'SELF' && auth()->user()->can('orders.edit'))
   <form method="POST" action="{{ route('fulfilment.dispatch',$order) }}" class="row g-2 mt-3">@csrf
    <div class="col-md-4"><input class="form-control" name="carrier" required placeholder="Carrier name"></div><div class="col-md-4"><input class="form-control" name="tracking_number" required placeholder="AWB / tracking number"></div><div class="col-md-4"><input class="form-control" type="url" name="tracking_url" placeholder="Tracking URL (optional)"></div><div class="col-12"><button class="btn btn-success">Confirm dispatch</button></div>
   </form>
  @endif
  @if(in_array($order->status,['PACKED','SHIPPED'],true) && auth()->user()->can('orders.edit'))
   <form method="POST" action="{{ route('fulfilment.pickup',$order) }}" class="row g-2 mt-3">@csrf<div class="col-md-5"><label class="form-label">Actual pickup time</label><input type="datetime-local" class="form-control" name="picked_up_at" required value="{{ $f?->picked_up_at?->format('Y-m-d\TH:i') }}"></div><div class="col-md-5"><label class="form-label">Pickup reference</label><input class="form-control" name="pickup_reference" value="{{ $f?->pickup_reference }}"></div><div class="col-md-2 d-flex align-items-end"><button class="btn btn-outline-success w-100">Record pickup</button></div></form>
  @endif
 </div>
</div>
