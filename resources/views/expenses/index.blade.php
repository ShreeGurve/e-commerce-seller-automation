@extends('layouts.app')
@section('title','Expenses')
@section('subtitle','Business costs that are not part of a single order')
@section('content')
<div class="card mb-3"><div class="card-body"><form class="row g-2" method="GET">
  <div class="col-6 col-md-2"><input type="date" name="from" value="{{ $from }}" class="form-control"></div>
  <div class="col-6 col-md-2"><input type="date" name="to" value="{{ $to }}" class="form-control"></div>
  <div class="col-md-2"><select name="category_id" class="form-select"><option value="">All categories</option>@foreach($categories as $c)<option value="{{ $c->id }}" @selected(request('category_id')==$c->id)>{{ $c->name }}</option>@endforeach</select></div>
  <div class="col-md-2"><select name="marketplace_id" class="form-select"><option value="">All channels</option>@foreach($marketplaces as $m)<option value="{{ $m->id }}" @selected(request('marketplace_id')==$m->id)>{{ $m->name }}</option>@endforeach</select></div>
  <div class="col-md-2"><input name="q" value="{{ request('q') }}" class="form-control" placeholder="Invoice / notes"></div>
  <div class="col-md-2 d-flex gap-2"><button class="btn btn-outline-primary">Filter</button>@can('finance.manage')<a href="{{ route('expenses.create') }}" class="btn btn-primary ms-auto"><i class="bi bi-plus-lg"></i></a>@endcan</div>
</form></div></div>
<div class="row g-3">
  <div class="col-lg-9"><div class="card"><div class="table-responsive"><table class="table table-hover align-middle mb-0">
    <thead><tr><th>Date</th><th>Category</th><th>Details</th><th class="text-end">Amount</th><th></th></tr></thead><tbody>
    @forelse($expenses as $e)
      <tr><td class="text-nowrap">{{ $e->expense_date->format('d M Y') }}</td><td>{{ $e->category->name }}</td>
        <td>{{ $e->notes ?: '—' }}<div class="small text-muted">{{ collect([$e->marketplace?->name,$e->product?->sku,$e->invoice_number?'Inv '.$e->invoice_number:null])->filter()->join(' · ') }}
          @if($e->attachment_path) · <a href="{{ route('expenses.attachment',$e) }}" target="_blank" class="text-decoration-none"><i class="bi bi-paperclip"></i>{{ $e->attachment_name }}</a>@endif</div></td>
        <td class="text-end fw-semibold">₹{{ number_format($e->amount,2) }}</td>
        <td class="text-end text-nowrap">@can('finance.manage')<a href="{{ route('expenses.edit',$e) }}" class="btn btn-sm btn-outline-primary">Edit</a>
          <form method="POST" action="{{ route('expenses.destroy',$e) }}" class="d-inline" onsubmit="return confirm('Delete expense?')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger">Delete</button></form>@endcan</td></tr>
    @empty<tr><td colspan="5" class="text-center text-muted py-5">No expenses in this period.</td></tr>@endforelse</tbody></table></div>
    @if($expenses->hasPages())<div class="card-footer">{{ $expenses->links() }}</div>@endif</div></div>
  <div class="col-lg-3">
    <div class="card mb-3"><div class="card-body"><div class="small text-muted">Total in period</div><div class="sg-bignum">₹{{ number_format($total,2) }}</div></div></div>
    <div class="card mb-3"><div class="card-header">By category</div><ul class="list-group list-group-flush small">
      @forelse($byCategory as $cid=>$t)<li class="list-group-item d-flex justify-content-between"><span>{{ $categories->firstWhere('id',$cid)->name ?? '—' }}</span><b>₹{{ number_format($t,2) }}</b></li>@empty<li class="list-group-item text-muted">—</li>@endforelse</ul></div>
    @can('finance.manage')<div class="card"><div class="card-body"><form method="POST" action="{{ route('expenses.categories.store') }}" class="d-flex gap-2">@csrf<input name="name" class="form-control form-control-sm" placeholder="New category" required><button class="btn btn-sm btn-outline-primary">Add</button></form></div></div>@endcan
  </div>
</div>
@endsection
