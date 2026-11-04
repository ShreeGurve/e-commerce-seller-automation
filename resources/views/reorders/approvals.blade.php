@extends('layouts.app')
@section('title', 'Reorder Approvals')
@section('subtitle', 'Review approved purchase plans and convert them into draft purchase orders')
@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div class="text-muted small">Only approved plans can be converted. Conversion creates a DRAFT PO; it does not send an order to a supplier.</div>
    <a href="{{ route('reorders.index') }}" class="btn btn-outline-secondary btn-sm">Back to reorder planning</a>
</div>
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
<div class="card"><div class="table-responsive"><table class="table table-hover align-middle mb-0">
<thead><tr><th>Request</th><th>Supplier</th><th class="text-end">Estimate</th><th>Status</th><th>Requested</th><th>Decision</th><th>Action</th></tr></thead>
<tbody>
@forelse($requests as $approval)
@php($payload = $approval->proposed_value ?? [])
<tr>
<td><div class="fw-semibold">#{{ $approval->id }} · {{ $approval->title }}</div><div class="small text-muted">{{ $approval->reference }}</div></td>
<td>{{ $payload['supplier_name'] ?? 'Supplier unavailable' }}<div class="small text-muted">{{ count($payload['items'] ?? []) }} product line(s)</div></td>
<td class="text-end">₹{{ number_format((float) $approval->amount, 2) }}</td>
<td><span class="badge {{ $approval->status === 'approved' ? 'text-bg-success' : ($approval->status === 'pending' ? 'text-bg-warning' : 'text-bg-secondary') }}">{{ ucfirst($approval->status) }}</span></td>
<td>{{ $approval->created_at?->format('d M Y, h:i A') }}</td>
<td>{{ $approval->reviewer?->name ?? '—' }}<div class="small text-muted">{{ $approval->reviewed_at?->format('d M Y') }}</div></td>
<td>
@if($approval->status === 'approved' && !$approval->executed_at)
<form method="POST" action="{{ route('reorders.approvals.draft', $approval) }}" onsubmit="return confirm('Create a draft purchase order from this approved plan? No supplier order will be sent.')">@csrf<button class="btn btn-sm btn-primary">Create draft PO</button></form>
@elseif($approval->executed_at)<span class="small text-success">Draft PO created</span>
@else<span class="small text-muted">Awaiting approval</span>@endif
</td>
</tr>
@empty
<tr><td colspan="7" class="text-center text-muted py-5">No automated reorder approval requests yet.</td></tr>
@endforelse
</tbody></table></div><div class="card-footer">{{ $requests->links() }}</div></div>
@endsection
