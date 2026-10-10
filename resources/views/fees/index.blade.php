@extends('layouts.app')
@section('title','Fee rules')
@section('subtitle','Marketplace fees live here, not in code. Change a rule, then recalculate profit.')
@section('content')
<div class="d-flex flex-wrap gap-2 mb-3">
  @can('finance.manage')<a href="{{ route('fees.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg"></i> New fee rule</a>@endcan
  <form method="GET" class="d-flex gap-2 ms-auto"><select name="marketplace_id" class="form-select" onchange="this.form.submit()"><option value="">All channels</option>@foreach($marketplaces as $m)<option value="{{ $m->id }}" @selected(request('marketplace_id')==$m->id)>{{ $m->name }}</option>@endforeach</select></form>
</div>
<div class="card mb-3"><div class="table-responsive"><table class="table table-hover align-middle mb-0">
  <thead><tr><th>Channel</th><th>Fee</th><th>Category</th><th class="text-end">%</th><th class="text-end">Fixed / unit</th><th>Weight slab</th><th>Valid</th><th>Status</th><th></th></tr></thead><tbody>
  @forelse($rules as $r)
    <tr><td>{{ $r->marketplace->name }}</td><td class="fw-semibold">{{ \App\Models\FeeRule::TYPES[$r->fee_type] }}<div class="small text-muted">{{ $r->name }} · on {{ $r->applies_on==='PRICE'?'price incl. GST':'price excl. GST' }}</div></td>
      <td>{{ $r->category->name ?? 'All' }}</td><td class="text-end">{{ rtrim(rtrim((string)$r->percentage,'0'),'.') ?: '0' }}%</td><td class="text-end">₹{{ number_format($r->fixed_amount,2) }}</td>
      <td>@if($r->weight_min_g!==null||$r->weight_max_g!==null){{ $r->weight_min_g ?? 0 }}–{{ $r->weight_max_g ?? '∞' }} g @else — @endif</td>
      <td class="text-nowrap small">{{ $r->effective_from->format('d M Y') }} → {{ $r->effective_to?->format('d M Y') ?? 'open' }}</td>
      <td><span class="badge text-bg-{{ $r->is_active?'success':'secondary' }}">{{ $r->is_active?'Active':'Off' }}</span></td>
      <td class="text-end text-nowrap">@can('finance.manage')<a href="{{ route('fees.edit',$r) }}" class="btn btn-sm btn-outline-primary">Edit</a>
        <form method="POST" action="{{ route('fees.destroy',$r) }}" class="d-inline" onsubmit="return confirm('Delete rule?')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger">Delete</button></form>@endcan</td></tr>
  @empty<tr><td colspan="9" class="text-center text-muted py-5">No fee rules yet. Add your referral %, closing fee and shipping fee from your marketplace rate card.<br>Without rules, estimated profit ignores fees.</td></tr>@endforelse
  </tbody></table></div></div>
<div class="card"><div class="card-header">Fee simulator <span class="text-muted fw-normal small">— test your rules on a real product</span></div><div class="card-body">
  <form method="GET" class="row g-2 align-items-end">
    <div class="col-md-3"><label class="form-label">Channel</label><select name="sim_marketplace" class="form-select">@foreach($marketplaces as $m)<option value="{{ $m->id }}" @selected(request('sim_marketplace')==$m->id)>{{ $m->name }}</option>@endforeach</select></div>
    <div class="col-md-4"><label class="form-label">Product</label><select name="sim_product" class="form-select">@foreach($products as $p)<option value="{{ $p->id }}" @selected(request('sim_product')==$p->id)>{{ $p->sku }} — {{ \Illuminate\Support\Str::limit($p->name,40) }}</option>@endforeach</select></div>
    <div class="col-6 col-md-2"><label class="form-label">Price ₹ (incl. GST)</label><input type="number" step="0.01" min="0" name="sim_price" value="{{ request('sim_price') }}" class="form-control"></div>
    <div class="col-6 col-md-1"><label class="form-label">Qty</label><input type="number" min="1" name="sim_qty" value="{{ request('sim_qty',1) }}" class="form-control"></div>
    <div class="col-md-2"><button class="btn btn-outline-primary w-100">Calculate</button></div>
  </form>
  @if($sim)<div class="mt-3"><div class="small text-muted mb-1">{{ $sim['product']->sku }} · line ₹{{ number_format($sim['line']/100,2) }} · GST inside ₹{{ number_format($sim['tax']/100,2) }}</div>
    <table class="table table-sm w-auto mb-0">@php $tot=0; @endphp
      @forelse($sim['lines'] as $l)@php $tot+=$l['amount']+$l['tax']; @endphp<tr><td>{{ \App\Models\FeeRule::TYPES[$l['type']] }}</td><td class="text-end">₹{{ number_format($l['amount']/100,2) }}</td><td class="text-end text-muted">+ GST ₹{{ number_format($l['tax']/100,2) }}</td></tr>
      @empty<tr><td class="text-muted">No rule matches this product / channel / today's date.</td></tr>@endforelse
      @if($sim['lines'])<tr class="fw-bold"><td>Total fees</td><td colspan="2" class="text-end">₹{{ number_format($tot/100,2) }} ({{ round($tot/$sim['line']*100,1) }}% of price)</td></tr>@endif</table></div>@endif
</div></div>
@endsection
