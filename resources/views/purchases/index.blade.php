@extends('layouts.app')
@section('title','Purchases')
@section('subtitle','Purchase orders and stock receiving')
@section('content')
@php $badge=['DRAFT'=>'secondary','ORDERED'=>'info','RECEIVED'=>'success','CANCELLED'=>'dark']; $pay=['UNPAID'=>'danger','PARTIAL'=>'warning','PAID'=>'success']; @endphp
<div class="card mb-3"><div class="card-body"><form class="row g-2" method="GET">
  <div class="col-md-3"><input name="q" value="{{ request('q') }}" class="form-control" placeholder="PO or invoice no."></div>
  <div class="col-md-3"><select name="supplier_id" class="form-select"><option value="">All suppliers</option>@foreach($suppliers as $s)<option value="{{ $s->id }}" @selected(request('supplier_id')==$s->id)>{{ $s->name }}</option>@endforeach</select></div>
  <div class="col-md-2"><select name="status" class="form-select"><option value="">Any status</option>@foreach(\App\Models\Purchase::STATUSES as $s)<option value="{{ $s }}" @selected(request('status')==$s)>{{ ucfirst(strtolower($s)) }}</option>@endforeach</select></div>
  <div class="col-md-4 d-flex gap-2"><button class="btn btn-outline-primary">Filter</button>
    @can('purchases.create')<a href="{{ route('purchases.create') }}" class="btn btn-primary ms-auto"><i class="bi bi-plus-lg"></i> New purchase</a>@endcan</div>
</form></div></div>
<div class="card"><div class="table-responsive"><table class="table table-hover align-middle mb-0">
  <thead><tr><th>PO</th><th>Date</th><th>Supplier</th><th>Invoice</th><th class="text-end">Total</th><th class="text-end">Due</th><th>Status</th><th>Payment</th></tr></thead><tbody>
  @forelse($purchases as $p)
    <tr><td><a class="fw-semibold text-decoration-none" href="{{ route('purchases.show',$p) }}">{{ $p->po_number }}</a></td>
      <td class="text-nowrap">{{ $p->purchase_date->format('d M Y') }}</td><td>{{ $p->supplier->name }}</td><td>{{ $p->invoice_number ?? '—' }}</td>
      <td class="text-end">₹{{ number_format($p->total_amount,2) }}</td><td class="text-end">₹{{ number_format((float)$p->balance(),2) }}</td>
      <td><span class="badge text-bg-{{ $badge[$p->status] }}">{{ ucfirst(strtolower($p->status)) }}</span></td>
      <td><span class="badge text-bg-{{ $pay[$p->payment_status] }}">{{ ucfirst(strtolower($p->payment_status)) }}</span></td></tr>
  @empty<tr><td colspan="8" class="text-center text-muted py-5">No purchases yet.</td></tr>@endforelse
  </tbody></table></div>
  @if($purchases->hasPages())<div class="card-footer">{{ $purchases->links() }}</div>@endif
</div>
@endsection
