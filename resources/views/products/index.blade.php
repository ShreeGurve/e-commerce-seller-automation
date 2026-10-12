@extends('layouts.app')
@section('title','Products')
@section('content')
<div class="card mb-3"><div class="card-body">
  <form class="row g-2" method="GET">
    <div class="col-md-4"><input name="q" value="{{ request('q') }}" class="form-control" placeholder="Search SKU or name"></div>
    <div class="col-md-3"><select name="category_id" class="form-select"><option value="">All categories</option>
      @foreach($categories as $c)<option value="{{ $c->id }}" @selected(request('category_id')==$c->id)>{{ $c->name }}</option>@endforeach</select></div>
    <div class="col-md-2"><select name="status" class="form-select"><option value="">Any status</option>
      @foreach(\App\Models\Product::STATUSES as $s)<option value="{{ $s }}" @selected(request('status')==$s)>{{ ucfirst($s) }}</option>@endforeach</select></div>
    <div class="col-md-3 d-flex gap-2"><button class="btn btn-outline-secondary">Filter</button>
      @can('products.create')<a href="{{ route('products.create') }}" class="btn btn-primary ms-auto"><i class="bi bi-plus-lg"></i> New product</a>@endcan</div>
  </form></div></div>
<div class="card"><div class="table-responsive"><table class="table table-hover align-middle mb-0">
  <thead class="table-light"><tr><th>SKU</th><th>Name</th><th>Category</th><th class="text-end">MRP</th><th class="text-end">Unit cost</th><th class="text-end">Target margin</th><th>Status</th></tr></thead><tbody>
  @forelse($products as $p)
    @php $price=(float)($p->target_selling_price ?: $p->default_selling_price); $cost=(float)$p->totalUnitCost();
         $m = $price>0 ? round(($price-$cost)/$price*100,1) : null; @endphp
    <tr>
      <td><a href="{{ route('products.show',$p) }}" class="fw-semibold text-decoration-none">{{ $p->sku }}</a></td>
      <td>{{ $p->name }}</td><td>{{ $p->category->name ?? '—' }}</td>
      <td class="text-end">{{ $p->mrp !== null ? '₹'.number_format($p->mrp,2) : '—' }}</td>
      <td class="text-end">₹{{ number_format($cost,2) }}</td>
      <td class="text-end">{!! $m === null ? '—' : '<span class="text-'.($m<0?'danger':'success').'">'.$m.'%</span>' !!}</td>
      <td><span class="badge text-bg-{{ ['active'=>'success','inactive'=>'secondary','draft'=>'info','discontinued'=>'dark'][$p->status] ?? 'secondary' }}">{{ ucfirst($p->status) }}</span></td>
    </tr>
  @empty<tr><td colspan="7" class="text-center text-muted py-5">No products yet.</td></tr>@endforelse
  </tbody></table></div>
  @if($products->hasPages())<div class="card-footer bg-white">{{ $products->links() }}</div>@endif
</div>
@endsection
