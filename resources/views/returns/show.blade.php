@extends('layouts.app')
@section('title',$r->return_number)
@section('subtitle',\App\Models\ProductReturn::TYPES[$r->return_type].' · '.$r->marketplace->name.' · '.$r->return_date->format('d M Y'))
@section('content')
@php $open=$r->status==='REQUESTED'; @endphp
<div class="d-flex flex-wrap gap-2 align-items-center mb-3">
  <span class="badge text-bg-{{ ['REQUESTED'=>'warning','INSPECTED'=>'info','CLOSED'=>'dark'][$r->status] }} fs-6">{{ ucfirst(strtolower($r->status)) }}</span>
  @if($r->claim_eligible)<span class="badge text-bg-danger fs-6">Claim eligible</span>@endif
  <a class="ms-2 text-decoration-none" href="{{ route('orders.show',$r->order) }}">Order {{ $r->order->order_number }}</a>
  @foreach($r->claims as $cl)<a class="badge text-bg-{{ \App\Models\Claim::BADGES[$cl->status] }} text-decoration-none" href="{{ route('claims.show',$cl) }}">{{ $cl->claim_number }} · {{ \App\Models\Claim::LABELS[$cl->status] }}</a>@endforeach
  @can('claims.create')@if($r->status!=='REQUESTED' && $r->claims->isEmpty())<a href="{{ route('claims.create',['return_id'=>$r->id]) }}" class="btn btn-sm {{ $r->claim_eligible?'btn-danger':'btn-outline-danger' }}"><i class="bi bi-shield-check"></i> Create claim</a>@endif @endcan
  @can('returns.manage')@if($r->status==='INSPECTED')<form method="POST" action="{{ route('returns.close',$r) }}" class="ms-auto">@csrf<button class="btn btn-sm btn-outline-primary">Close return</button></form>@endif @endcan
</div>
<div class="row g-3">
  <div class="col-lg-8">
    <form method="POST" action="{{ route('returns.inspect',$r) }}">@csrf
    <div class="card mb-3"><div class="card-header">Inspection: expected vs received</div><div class="table-responsive"><table class="table align-middle mb-0">
      <thead><tr><th>Product</th><th class="text-end">Expected</th><th style="width:120px">Received</th><th style="width:150px">Condition</th><th>Note</th></tr></thead><tbody>
      @foreach($r->items as $i)
        <tr><td><b>{{ $i->product->sku }}</b><div class="small text-muted">{{ $i->product->name }}</div></td><td class="text-end">{{ $i->quantity }}</td>
        @if($open && auth()->user()->can('returns.manage'))
          <td><input type="number" min="0" max="{{ $i->quantity }}" name="lines[{{ $i->id }}][received_qty]" value="{{ old('lines.'.$i->id.'.received_qty',$i->quantity) }}" class="form-control form-control-sm" required></td>
          <td><select name="lines[{{ $i->id }}][condition]" class="form-select form-select-sm">@foreach(\App\Models\ReturnItem::CONDITIONS as $k=>$l)@continue($k==='MISSING')<option value="{{ $k }}" @selected(old('lines.'.$i->id.'.condition')===$k)>{{ $l }}</option>@endforeach</select></td>
          <td><input name="lines[{{ $i->id }}][note]" class="form-control form-control-sm"></td>
        @else
          <td>{{ $i->received_qty }}@if($i->received_qty<$i->quantity)<span class="badge text-bg-danger ms-1">short {{ $i->quantity-$i->received_qty }}</span>@endif</td>
          <td>{{ \App\Models\ReturnItem::CONDITIONS[$i->condition] ?? '—' }}@if($i->restocked_qty)<div class="small text-success">+{{ $i->restocked_qty }} restocked</div>@endif</td><td>{{ $i->note }}</td>
        @endif</tr>
      @endforeach</tbody></table></div>
      @if($open) @can('returns.manage')
      <div class="card-body border-top"><div class="form-text mb-2">Sellable &rarr; back to available stock. Used / Damaged / Scrap &rarr; damaged stock. Received 0 &rarr; Missing. Can only be saved once.</div>
        <div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="claim_eligible" value="1" id="ce"><label class="form-check-label" for="ce">Eligible for a claim (SAFE-T / reimbursement)</label></div>
        <textarea name="notes" rows="2" class="form-control mb-3" placeholder="Inspection notes">{{ old('notes',$r->notes) }}</textarea>
        <button class="btn btn-primary" onclick="return confirm('Save inspection and update stock? This cannot be repeated.')">Save inspection</button></div>
      @endcan @elseif($r->notes)<div class="card-footer">{{ $r->notes }}</div>@endif
    </div></form>
    @if($r->refunds->count())<div class="card"><div class="card-header">Refunds linked</div><ul class="list-group list-group-flush">@foreach($r->refunds as $f)<li class="list-group-item d-flex justify-content-between"><span>{{ $f->refund_date->format('d M Y') }}</span><b>₹{{ number_format($f->amount,2) }}</b></li>@endforeach</ul></div>@endif
  </div>
  <div class="col-lg-4"><div class="card"><div class="card-header">Evidence (photos / videos)</div><div class="card-body">
    <div class="row g-2 mb-3">@forelse($r->media as $m)
      <div class="col-6"><div class="border rounded p-1 small text-center">
        <a href="{{ route('returns.media',[$r,$m]) }}" target="_blank">@if($m->isImage())<img src="{{ route('returns.media',[$r,$m]) }}" class="img-fluid rounded" alt="">@else<i class="bi bi-camera-video fs-1"></i>@endif<div class="text-truncate">{{ $m->original_name }}</div></a>
        @can('returns.manage')<form method="POST" action="{{ route('returns.media.delete',[$r,$m]) }}" onsubmit="return confirm('Remove file?')">@csrf @method('DELETE')<button class="btn btn-link btn-sm text-danger p-0">Remove</button></form>@endcan</div></div>
    @empty<div class="col-12 text-muted">No files yet.</div>@endforelse</div>
    @can('returns.manage')<form method="POST" action="{{ route('returns.upload',$r) }}" enctype="multipart/form-data">@csrf
      <input type="file" name="files[]" multiple accept="image/*,video/mp4,video/quicktime" class="form-control mb-2" required><div class="form-text mb-2">jpg, png, webp, mp4, mov. Max 20 MB each.</div>
      <button class="btn btn-outline-primary w-100">Upload</button></form>@endcan
  </div></div></div>
</div>
@endsection
