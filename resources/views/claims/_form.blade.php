@php
  // Resolve old input lazily and avoid casting an empty decimal attribute.
  $f = function ($k, $d = null) use ($claim) {
      $old = old($k);
      if ($old !== null) return $old;
      $raw = $claim->getAttributes()[$k] ?? null;
      if ($raw === '') return $d;
      return $claim->getAttribute($k) ?? $d;
  };
  $draft = !$claim->exists || $claim->status === 'DRAFT';
@endphp
<div class="row g-3">
  @if(!$claim->exists)
  <div class="col-md-3"><label class="form-label">Channel *</label><select name="marketplace_id" class="form-select" required>@foreach($marketplaces as $m)<option value="{{ $m->id }}" @selected($f('marketplace_id')==$m->id)>{{ $m->name }}</option>@endforeach</select></div>
  <div class="col-md-3"><label class="form-label">Order</label>
    @if($order)<input type="hidden" name="order_id" value="{{ $order->id }}"><input class="form-control" value="{{ $order->external_order_id }}" disabled>
    @else<input name="order_ref" value="{{ old('order_ref') }}" class="form-control" placeholder="Marketplace order id (optional)">@endif</div>
  @if($return)<input type="hidden" name="return_id" value="{{ $return->id }}">@endif
  @endif
  <div class="col-md-3"><label class="form-label">Claim type *</label><select name="claim_type" class="form-select" {{ $draft?'':'disabled' }}>@foreach(\App\Models\Claim::TYPES as $k=>$l)<option value="{{ $k }}" @selected($f('claim_type')===$k)>{{ $l }}</option>@endforeach</select></div>
  <div class="col-md-3"><label class="form-label">Claim amount ₹ *</label><input type="number" step="0.01" min="0.01" name="claim_amount" value="{{ $f('claim_amount') }}" class="form-control" {{ $draft?'required':'disabled' }}></div>
  <div class="col-md-4"><label class="form-label">Product</label><select name="product_id" class="form-select" {{ $draft?'':'disabled' }}><option value="">—</option>@foreach($products as $p)<option value="{{ $p->id }}" @selected($f('product_id')==$p->id)>{{ $p->sku }} — {{ \Illuminate\Support\Str::limit($p->name,40) }}</option>@endforeach</select></div>
  <div class="col-md-4"><label class="form-label">Deadline</label><input type="date" name="deadline" value="{{ old('deadline', optional($claim->deadline)->toDateString()) }}" class="form-control"><div class="form-text">Last day to file or appeal. Empty = filing window from Settings.</div></div>
  <div class="col-md-4"><label class="form-label">Marketplace claim / case id</label><input name="external_claim_id" value="{{ $f('external_claim_id') }}" class="form-control"><div class="form-text">Fill after you file it on the portal.</div></div>
  <div class="col-12"><label class="form-label">Notes</label><textarea name="notes" rows="3" class="form-control">{{ $f('notes') }}</textarea></div>
</div>
