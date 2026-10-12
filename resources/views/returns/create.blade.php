@extends('layouts.app')
@section('title','New return')
@section('subtitle',$order->order_number.' · '.$order->marketplace->name.' · '.$order->external_order_id)
@section('content')
<div class="card"><div class="card-body"><form method="POST" action="{{ route('orders.returns.store',$order) }}">@csrf
  <div class="row g-3 mb-3">
    <div class="col-md-3"><label class="form-label">Reason *</label><select name="return_type" class="form-select" required>@foreach(\App\Models\ProductReturn::TYPES as $k=>$l)<option value="{{ $k }}" @selected(old('return_type','UNKNOWN')===$k)>{{ $l }}</option>@endforeach</select></div>
    <div class="col-md-3"><label class="form-label">Return date *</label><input type="date" name="return_date" value="{{ old('return_date',now()->toDateString()) }}" max="{{ now()->toDateString() }}" class="form-control" required></div>
    <div class="col-md-3"><label class="form-label">Marketplace return id</label><input name="external_return_id" value="{{ old('external_return_id') }}" class="form-control"></div>
    <div class="col-md-3"><label class="form-label">Customer's reason</label><input name="reason_text" value="{{ old('reason_text') }}" class="form-control"></div>
  </div>
  <div class="table-responsive"><table class="table align-middle"><thead><tr><th>Product</th><th class="text-end">Sold</th><th class="text-end">Already returned</th><th style="width:140px">Return qty</th></tr></thead><tbody>
    @foreach($order->items as $i) @php $ret=(int)($returned[$i->id] ?? 0); $left=$i->quantity-$ret; @endphp
      <tr><td><b>{{ $i->product->sku }}</b><div class="small text-muted">{{ $i->product->name }}</div></td><td class="text-end">{{ $i->quantity }}</td><td class="text-end">{{ $ret }}</td>
        <td><input type="number" min="0" max="{{ $left }}" name="qty[{{ $i->id }}]" value="{{ old('qty.'.$i->id,0) }}" class="form-control" {{ $left<=0?'disabled':'' }}></td></tr>
    @endforeach</tbody></table></div>
  <button class="btn btn-primary">Create return</button> <a href="{{ route('orders.show',$order) }}" class="btn btn-link">Cancel</a>
</form></div></div>
@endsection
