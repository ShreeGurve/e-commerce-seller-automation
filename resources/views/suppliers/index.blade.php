@extends('layouts.app')
@section('title','Suppliers')
@section('subtitle','People you buy stock from')
@section('content')
<div class="card mb-3"><div class="card-body">
  <form class="row g-2" method="GET">
    <div class="col-md-5"><input name="q" value="{{ request('q') }}" class="form-control" placeholder="Search name, phone, GSTIN"></div>
    <div class="col-md-7 d-flex gap-2"><button class="btn btn-outline-primary">Search</button>
      @can('purchases.create')<a href="{{ route('suppliers.create') }}" class="btn btn-primary ms-auto"><i class="bi bi-plus-lg"></i> New supplier</a>@endcan</div>
  </form></div></div>
<div class="card"><div class="table-responsive"><table class="table table-hover align-middle mb-0">
  <thead><tr><th>Supplier</th><th>Contact</th><th>GSTIN</th><th>City</th><th class="text-end">Purchases</th><th>Status</th><th></th></tr></thead><tbody>
  @forelse($suppliers as $s)
    <tr><td class="fw-semibold">{{ $s->name }}</td>
      <td>{{ $s->contact_person ?? '—' }}<div class="small text-muted">{{ $s->phone }}</div></td>
      <td>{{ $s->gstin ?? '—' }}</td><td>{{ $s->city ?? '—' }}</td><td class="text-end">{{ $s->purchases_count }}</td>
      <td><span class="badge text-bg-{{ $s->is_active ? 'success' : 'secondary' }}">{{ $s->is_active ? 'Active' : 'Inactive' }}</span></td>
      <td class="text-end text-nowrap">
        @can('purchases.edit')<a href="{{ route('suppliers.edit',$s) }}" class="btn btn-sm btn-outline-primary">Edit</a>
        <form method="POST" action="{{ route('suppliers.destroy',$s) }}" class="d-inline" onsubmit="return confirm('Delete supplier?')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger">Delete</button></form>@endcan
      </td></tr>
  @empty<tr><td colspan="7" class="text-center text-muted py-5">No suppliers yet.</td></tr>@endforelse
  </tbody></table></div>
  @if($suppliers->hasPages())<div class="card-footer">{{ $suppliers->links() }}</div>@endif
</div>
@endsection
