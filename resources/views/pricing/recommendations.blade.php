@extends('layouts.app')
@section('title', 'Pricing Recommendations')
@section('subtitle', 'Review proposed channel prices. Approval records an internal decision only; nothing is published.')
@section('content')
    <div class="d-flex flex-wrap gap-2 align-items-center mb-3">
        @foreach (['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected', 'applied' => 'Recorded'] as $key => $label)
            <a class="btn btn-sm {{ request('status') === $key ? 'btn-primary' : 'btn-outline-secondary' }}"
                href="{{ route('pricing-recommendations.index', ['status' => $key]) }}">{{ $label }}</a>
        @endforeach
        <a class="btn btn-sm btn-outline-secondary" href="{{ route('pricing-recommendations.index') }}">All</a>
        <a class="btn btn-sm btn-outline-primary ms-auto" href="{{ route('pricing.index') }}">Pricing engine</a>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead><tr>
                    <th>Product / Channel</th><th class="text-end">Current</th><th class="text-end">Proposed</th>
                    <th class="text-end">Minimum</th><th class="text-end">Expected profit</th>
                    <th>Status</th><th>Requested</th><th style="min-width:230px">Review / Action</th>
                </tr></thead>
                <tbody>
                @forelse ($recommendations as $r)
                    <tr>
                        <td>
                            <div class="fw-semibold">{{ $r->product?->sku }} · {{ $r->product?->name }}</div>
                            <div class="small text-muted">{{ $r->marketplace?->name }} · {{ $r->reason }}</div>
                        </td>
                        <td class="text-end">{{ $r->current_price !== null ? '₹'.number_format((float)$r->current_price,2) : '—' }}</td>
                        <td class="text-end fw-semibold">₹{{ number_format((float)$r->proposed_price,2) }}</td>
                        <td class="text-end">{{ $r->minimum_price !== null ? '₹'.number_format((float)$r->minimum_price,2) : '—' }}</td>
                        <td class="text-end">
                            {{ $r->expected_profit !== null ? '₹'.number_format((float)$r->expected_profit,2) : '—' }}
                            <div class="small text-muted">{{ $r->expected_margin !== null ? number_format((float)$r->expected_margin,1).'%' : '' }}</div>
                        </td>
                        <td><span class="badge text-bg-{{ $r->status === 'approved' ? 'success' : ($r->status === 'rejected' ? 'danger' : ($r->status === 'applied' ? 'primary' : 'warning')) }}">{{ ucfirst($r->status) }}</span></td>
                        <td class="small">{{ $r->requester?->name }}<div class="text-muted">{{ $r->created_at?->format('d M Y') }}</div></td>
                        <td>
                            @if ($r->status === 'pending' && (auth()->user()->isSuperAdmin() || auth()->user()->hasPermission('settings.manage') || auth()->user()->hasPermission('users.manage')))
                                <form method="POST" action="{{ route('pricing-recommendations.approve', $r) }}" class="mb-2">@csrf
                                    <input name="review_note" class="form-control form-control-sm mb-1" placeholder="Review note (optional)">
                                    <button class="btn btn-sm btn-success">Approve</button>
                                </form>
                                <form method="POST" action="{{ route('pricing-recommendations.reject', $r) }}">@csrf
                                    <input name="review_note" class="form-control form-control-sm mb-1" placeholder="Reason for rejection" required>
                                    <button class="btn btn-sm btn-outline-danger">Reject</button>
                                </form>
                            @elseif ($r->status === 'approved')
                                <form method="POST" action="{{ route('pricing-recommendations.apply', $r) }}">@csrf
                                    <button class="btn btn-sm btn-primary">Record internal price</button>
                                </form>
                                <div class="small text-muted mt-1">Manual marketplace update still required.</div>
                            @else
                                <div class="small text-muted">{{ $r->review_note ?: ($r->applied_at ? 'Recorded '.$r->applied_at->format('d M Y H:i') : '—') }}</div>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-center text-muted py-5">No pricing recommendations found.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer">{{ $recommendations->links() }}</div>
    </div>
@endsection
