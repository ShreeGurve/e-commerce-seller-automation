@extends('layouts.app')
@section('title',$claim->claim_number)
@section('subtitle',\App\Models\Claim::TYPES[$claim->claim_type].' · '.$claim->marketplace->name.($claim->external_claim_id?' · '.$claim->external_claim_id:''))
@section('content')
@php $st=$claim->deadlineState(); $canManage=auth()->user()->can('claims.manage'); @endphp
@if(in_array($st,['URGENT','EXPIRED']))<div class="alert alert-danger"><b>{{ $st==='EXPIRED'?'Deadline passed':'Deadline is close' }}.</b>
  {{ $claim->status==='DRAFT' ? 'File this claim on the marketplace portal and mark it Submitted.' : 'Appeal it now if you want to contest the decision.' }}</div>
@elseif($st==='WARNING')<div class="alert alert-warning">Deadline in {{ $claim->daysLeft() }} days. Prepare the evidence.</div>@endif
<div class="d-flex flex-wrap gap-2 align-items-center mb-3">
  <span class="badge text-bg-{{ \App\Models\Claim::BADGES[$claim->status] }} fs-6">{{ \App\Models\Claim::LABELS[$claim->status] }}</span>@if($claim->appeal_count)<span class="badge text-bg-light border">appeal ×{{ $claim->appeal_count }}</span>@endif
  <span>@include('claims._deadline',['claim'=>$claim])</span>
  @if($canManage && $claim->status!=='CLOSED')<a href="{{ route('claims.edit',$claim) }}" class="btn btn-sm btn-outline-primary ms-auto">Edit</a>@endif
</div>
<div class="row g-3">
  <div class="col-lg-8">
    <div class="card mb-3"><div class="card-body"><div class="row g-3">
      <div class="col-6 col-md-3"><div class="small text-muted">Claimed</div><div class="fw-bold fs-5">₹{{ number_format($claim->claim_amount,2) }}</div></div>
      <div class="col-6 col-md-3"><div class="small text-muted">Approved</div><div class="fw-bold fs-5 text-success">₹{{ number_format($claim->approved_amount,2) }}</div></div>
      <div class="col-6 col-md-3"><div class="small text-muted">Rejected</div><div class="fw-bold fs-5 {{ (float)$claim->rejected_amount>0?'text-danger':'' }}">₹{{ number_format($claim->rejected_amount,2) }}</div></div>
      <div class="col-6 col-md-3"><div class="small text-muted">Decision</div><div class="fw-semibold">{{ $claim->decision_date?->format('d M Y') ?? '—' }}</div></div>
      <div class="col-md-6 small"><span class="text-muted">Order:</span> @if($claim->order)<a class="text-decoration-none" href="{{ route('orders.show',$claim->order) }}">{{ $claim->order->external_order_id }}</a>@else — @endif
        · <span class="text-muted">Return:</span> @if($claim->productReturn)<a class="text-decoration-none" href="{{ route('returns.show',$claim->productReturn) }}">{{ $claim->productReturn->return_number }}</a>@else — @endif</div>
      <div class="col-md-6 small"><span class="text-muted">Product:</span> {{ $claim->product->sku ?? '—' }} · <span class="text-muted">Filed:</span> {{ $claim->submitted_at?->format('d M Y') ?? '—' }}</div>
      @if($claim->notes)<div class="col-12 small border-top pt-2">{{ $claim->notes }}</div>@endif
    </div></div></div>

    @if($canManage && $claim->nextStatuses())
    <div class="card mb-3"><div class="card-header">Next step</div><div class="card-body"><div class="row g-3">
      @foreach($claim->nextStatuses() as $to)
      <div class="col-md-6"><form method="POST" action="{{ route('claims.status',$claim) }}" class="border rounded p-2 h-100" @if($to==='CLOSED') onsubmit="return confirm('Close this claim? It cannot be reopened.')" @endif>@csrf<input type="hidden" name="to" value="{{ $to }}">
        <div class="fw-semibold mb-2">{{ ['SUBMITTED'=>'Mark as filed on portal','UNDER_REVIEW'=>'Marketplace is reviewing','APPROVED'=>'Approved','PARTIALLY_APPROVED'=>'Partially approved','REJECTED'=>'Rejected','APPEAL'=>'Appeal the decision','CLOSED'=>'Close claim'][$to] }}</div>
        @if($to==='SUBMITTED')<div class="row g-2 mb-2"><div class="col"><input type="date" name="submitted_at" value="{{ now()->toDateString() }}" max="{{ now()->toDateString() }}" class="form-control form-control-sm"></div><div class="col"><input name="external_claim_id" value="{{ $claim->external_claim_id }}" class="form-control form-control-sm" placeholder="Portal claim id"></div></div>
        @elseif(in_array($to,['APPROVED','PARTIALLY_APPROVED']))<div class="row g-2 mb-2"><div class="col"><input type="number" step="0.01" min="0.01" max="{{ $claim->claim_amount }}" name="approved_amount" value="{{ $to==='APPROVED'?$claim->claim_amount:'' }}" class="form-control form-control-sm" placeholder="Approved ₹" required></div><div class="col"><input type="date" name="decision_date" value="{{ now()->toDateString() }}" max="{{ now()->toDateString() }}" class="form-control form-control-sm"></div></div>
        @elseif($to==='REJECTED')<div class="mb-2"><input type="date" name="decision_date" value="{{ now()->toDateString() }}" max="{{ now()->toDateString() }}" class="form-control form-control-sm"></div>
        @elseif($to==='APPEAL')<div class="mb-2"><input type="date" name="deadline" class="form-control form-control-sm" title="New appeal deadline (optional)"><div class="form-text">New appeal deadline (optional)</div></div>@endif
        <input name="note" class="form-control form-control-sm mb-2" placeholder="Note (optional)"><button class="btn btn-sm btn-{{ in_array($to,['REJECTED','CLOSED'])?'outline-secondary':'primary' }}">Apply</button></form></div>
      @endforeach</div>
      <div class="small text-muted mt-2">Claims are filed by you on the marketplace portal. This system tracks them; nothing is ever sent to Amazon or Flipkart automatically.</div></div></div>
    @endif

    <div class="card"><div class="card-header">Timeline</div><ul class="list-group list-group-flush small">
      @foreach($claim->events as $e)<li class="list-group-item"><div class="d-flex justify-content-between"><span><b>{{ $e->event==='STATUS' ? (\App\Models\Claim::LABELS[$e->from_status] ?? '—').' → '.\App\Models\Claim::LABELS[$e->to_status] : ucfirst(strtolower($e->event)) }}</b>
        @if($e->amount!==null) <span class="text-success">₹{{ number_format($e->amount,2) }}</span>@endif</span><span class="text-muted">{{ $e->created_at->format('d M Y H:i') }} · {{ $e->user->name ?? 'system' }}</span></div>@if($e->note)<div class="text-muted">{{ $e->note }}</div>@endif</li>@endforeach</ul></div>
  </div>
  <div class="col-lg-4"><div class="card"><div class="card-header">Evidence</div><div class="card-body">
    <div class="row g-2 mb-3">@forelse($claim->documents as $d)
      <div class="col-6"><div class="border rounded p-1 small text-center"><a href="{{ route('claims.document',[$claim,$d]) }}" target="_blank" class="text-decoration-none">
        @if($d->isImage())<img src="{{ route('claims.document',[$claim,$d]) }}" class="img-fluid rounded" alt="">@else<i class="bi {{ $d->mime==='application/pdf'?'bi-file-earmark-pdf':'bi-camera-video' }} fs-1"></i>@endif<div class="text-truncate">{{ $d->original_name }}</div></a>
        @if($canManage)<form method="POST" action="{{ route('claims.document.delete',[$claim,$d]) }}" onsubmit="return confirm('Remove file?')">@csrf @method('DELETE')<button class="btn btn-link btn-sm text-danger p-0">Remove</button></form>@endif</div></div>
    @empty<div class="col-12 text-muted">No files yet. Photos of the damaged / wrong item and the packaging win claims.</div>@endforelse</div>
    @can('claims.create')@if($claim->status!=='CLOSED')
    <form method="POST" action="{{ route('claims.upload',$claim) }}" enctype="multipart/form-data">@csrf<input type="file" name="files[]" multiple accept="image/*,video/mp4,video/quicktime,application/pdf" class="form-control mb-2" required>
      <div class="form-text mb-2">jpg, png, webp, mp4, mov, pdf. Max 20 MB each.</div><button class="btn btn-outline-primary w-100">Upload</button></form>
    @if($claim->productReturn && $claim->productReturn->media->count())<form method="POST" action="{{ route('claims.attach',$claim) }}" class="mt-2">@csrf<button class="btn btn-sm btn-outline-secondary w-100">Copy {{ $claim->productReturn->media->count() }} file(s) from the return inspection</button></form>@endif
    @endif @endcan
  </div></div></div>
</div>
@endsection
