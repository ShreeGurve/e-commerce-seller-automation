@extends('layouts.app')
@section('title','Approval Workflow Policies')
@section('subtitle','Configure approval levels by request category and amount range')
@section('content')
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif
<div class="card mb-4"><div class="card-header"><h5 class="mb-0">Add or update policy</h5></div><div class="card-body">
<form method="POST" action="{{ route('approval-workflows.store') }}" class="row g-3">@csrf
<div class="col-md-3"><label class="form-label">Request category</label><select class="form-select" name="request_type" required>@foreach(['expense'=>'Expense','purchase'=>'Purchase','price_change'=>'Price change','refund'=>'Refund','inventory_adjustment'=>'Inventory adjustment','other'=>'Other'] as $v=>$label)<option value="{{ $v }}">{{ $label }}</option>@endforeach</select></div>
<div class="col-md-2"><label class="form-label">Minimum amount</label><input class="form-control" type="number" min="0" step="0.01" name="min_amount"></div>
<div class="col-md-2"><label class="form-label">Maximum amount</label><input class="form-control" type="number" min="0" step="0.01" name="max_amount"></div>
<div class="col-md-2"><label class="form-label">Approval levels</label><select class="form-select" name="approval_levels">@for($i=1;$i<=5;$i++)<option value="{{ $i }}">{{ $i }}</option>@endfor</select></div>
<div class="col-md-2"><label class="form-label">Reviewer permission</label><select class="form-select" name="reviewer_permission"><option value="settings.manage">Settings manager</option><option value="users.manage">User manager</option></select></div>
<div class="col-md-1"><label class="form-label">Enabled</label><select class="form-select" name="is_enabled"><option value="1">Yes</option><option value="0">No</option></select></div>
<div class="col-12"><button class="btn btn-primary">Save policy</button></div>
</form></div></div>
<div class="card"><div class="card-header"><h5 class="mb-0">Configured policies</h5></div><div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Category</th><th>Amount range</th><th>Levels</th><th>Reviewer</th><th>Status</th><th></th></tr></thead><tbody>
@forelse($policies as $policy)<tr><td>{{ str($policy->request_type)->replace('_',' ')->title() }}</td><td>₹{{ $policy->min_amount===null?'0':number_format((float)$policy->min_amount,2) }} – {{ $policy->max_amount===null?'No limit':'₹'.number_format((float)$policy->max_amount,2) }}</td><td>{{ $policy->approval_levels }}</td><td>{{ $policy->reviewer_permission }}</td><td><span class="badge text-bg-{{ $policy->is_enabled?'success':'secondary' }}">{{ $policy->is_enabled?'Enabled':'Disabled' }}</span></td><td><form method="POST" action="{{ route('approval-workflows.destroy',$policy) }}" onsubmit="return confirm('Remove this policy? Existing requests will not change.')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger">Remove</button></form></td></tr>@empty<tr><td colspan="6" class="text-center text-muted py-3">No policies configured.</td></tr>@endforelse
</tbody></table></div></div>
<div class="alert alert-info mt-3">Policies apply to new approval requests only. Approval levels are sequential and each level uses the selected reviewer permission. Existing requests without workflow steps keep the original single-decision process.</div>
@endsection
