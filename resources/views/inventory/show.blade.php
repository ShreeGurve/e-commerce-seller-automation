@extends('layouts.app')
@section('title',$product->sku)
@section('subtitle',$product->name)
@section('content')
<div class="row g-3 mb-3">
  @foreach([['Available',$inv->available_qty],['Damaged',$inv->damaged_qty],['Reserved',$inv->reserved_qty],['In transit',$inv->in_transit_qty]] as [$l,$v])
  <div class="col-6 col-lg-3"><div class="card"><div class="card-body"><div class="small text-muted">{{ $l }}</div><div class="sg-bignum">{{ $v }}</div></div></div></div>
  @endforeach
</div>
<div class="row g-3">
  @can('inventory.manage')
  <div class="col-lg-4">
    <div class="card mb-3"><div class="card-header">Adjust stock</div><div class="card-body">
      <form method="POST" action="{{ route('inventory.adjust',$product) }}">@csrf
        <div class="mb-2"><label class="form-label">Type</label><select name="type" class="form-select">
          @foreach(\App\Models\InventoryTransaction::MANUAL as $t)<option value="{{ $t }}">{{ \App\Models\InventoryTransaction::TYPES[$t]['label'] }}</option>@endforeach</select></div>
        <div class="mb-2"><label class="form-label">Quantity <span class="text-muted small">(Adjustment may be negative)</span></label><input type="number" name="quantity" step="1" class="form-control" required></div>
        <div class="mb-3"><label class="form-label">Note</label><input name="note" class="form-control" placeholder="Reason"></div>
        <button class="btn btn-primary w-100">Apply</button></form></div></div>
    <div class="card"><div class="card-header">Stock levels</div><div class="card-body">
      <form method="POST" action="{{ route('inventory.levels',$product) }}">@csrf @method('PUT')
        <div class="row g-2 mb-3"><div class="col"><label class="form-label">Minimum</label><input type="number" min="0" name="minimum_stock" value="{{ $inv->minimum_stock }}" class="form-control"></div>
          <div class="col"><label class="form-label">Reorder at</label><input type="number" min="0" name="reorder_level" value="{{ $inv->reorder_level }}" class="form-control"></div></div>
        <button class="btn btn-outline-primary w-100">Save levels</button></form></div></div>
  </div>
  @endcan
  <div class="@can('inventory.manage') col-lg-8 @else col-12 @endcan">
    <div class="card"><div class="card-header">Ledger</div><div class="table-responsive"><table class="table table-sm align-middle mb-0">
      <thead><tr><th>Date</th><th>Type</th><th class="text-end">Change</th><th class="text-end">Balance</th><th>Note</th><th>By</th></tr></thead><tbody>
      @forelse($ledger as $t)
        <tr><td class="text-nowrap">{{ $t->created_at->format('d M Y H:i') }}</td><td>{{ \App\Models\InventoryTransaction::TYPES[$t->type]['label'] ?? $t->type }}</td>
          <td class="text-end fw-bold {{ $t->qty_change<0?'text-danger':($t->qty_change>0?'text-success':'') }}">{{ $t->qty_change>0?'+':'' }}{{ $t->qty_change }}@if($t->damaged_change)<span class="small text-muted"> (dmg {{ $t->damaged_change>0?'+':'' }}{{ $t->damaged_change }})</span>@endif</td>
          <td class="text-end">{{ $t->balance_after }}</td><td>{{ $t->note }}</td><td class="text-nowrap">{{ $t->user->name ?? '—' }}</td></tr>
      @empty<tr><td colspan="6" class="text-center text-muted py-4">No stock movement yet.</td></tr>@endforelse
      </tbody></table></div>
      @if($ledger->hasPages())<div class="card-footer">{{ $ledger->links() }}</div>@endif
    </div>
  </div>
</div>
@endsection
