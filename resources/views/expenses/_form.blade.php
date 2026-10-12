@php $f = fn($k,$d=null) => old($k, $expense->{$k} ?? $d); @endphp
<div class="row g-3">
  <div class="col-md-3"><label class="form-label">Date *</label><input type="date" name="expense_date" value="{{ old('expense_date', optional($expense->expense_date)->toDateString() ?? now()->toDateString()) }}" max="{{ now()->toDateString() }}" class="form-control" required></div>
  <div class="col-md-3"><label class="form-label">Category *</label><select name="expense_category_id" class="form-select" required>@foreach($categories as $c)<option value="{{ $c->id }}" @selected($f('expense_category_id')==$c->id)>{{ $c->name }}</option>@endforeach</select></div>
  <div class="col-md-3"><label class="form-label">Amount ₹ *</label><input type="number" step="0.01" min="0.01" name="amount" value="{{ $f('amount') }}" class="form-control" required></div>
  <div class="col-md-3"><label class="form-label">Invoice no.</label><input name="invoice_number" value="{{ $f('invoice_number') }}" class="form-control"></div>
  <div class="col-md-3"><label class="form-label">Channel <span class="text-muted small">(optional)</span></label><select name="marketplace_id" class="form-select"><option value="">General business</option>@foreach($marketplaces as $m)<option value="{{ $m->id }}" @selected($f('marketplace_id')==$m->id)>{{ $m->name }}</option>@endforeach</select></div>
  <div class="col-md-5"><label class="form-label">Product <span class="text-muted small">(optional)</span></label><select name="product_id" class="form-select"><option value="">—</option>@foreach($products as $p)<option value="{{ $p->id }}" @selected($f('product_id')==$p->id)>{{ $p->sku }} — {{ \Illuminate\Support\Str::limit($p->name,40) }}</option>@endforeach</select></div>
  <div class="col-md-4"><label class="form-label">Invoice / receipt file</label><input type="file" name="attachment" accept=".pdf,.jpg,.jpeg,.png,.webp" class="form-control">@if($expense->attachment_name)<div class="form-text">Current: {{ $expense->attachment_name }} (upload to replace)</div>@endif</div>
  <div class="col-12"><label class="form-label">Notes</label><textarea name="notes" rows="2" class="form-control">{{ $f('notes') }}</textarea></div>
</div>
