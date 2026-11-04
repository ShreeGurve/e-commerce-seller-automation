@extends('layouts.app')
@section('title', 'Reconciliation')
@section('subtitle', 'Orders vs payouts vs fees vs refunds: find money that is missing or wrong')
@section('content')
    @php $T=\App\Models\ReconciliationIssue::TYPES; @endphp
    <div class="row g-3 mb-3">
        <div class="col-6 col-xl-3"><div class="card h-100"><div class="card-body"><div class="small text-muted">Open financial issues</div><div class="sg-bignum">{{ number_format($risk['openCount']) }}</div></div></div></div>
        <div class="col-6 col-xl-3"><div class="card h-100"><div class="card-body"><div class="small text-muted">Potential exposure</div><div class="sg-bignum text-danger">₹{{ number_format($risk['exposure'], 2) }}</div><div class="small text-muted">Absolute value of open differences</div></div></div></div>
        <div class="col-6 col-xl-3"><div class="card h-100"><div class="card-body"><div class="small text-muted">High-risk / aged issues</div><div class="sg-bignum text-warning">{{ number_format($risk['criticalCount']) }}</div><div class="small text-muted">Difference ≥ ₹1,000 in magnitude or open 7+ days</div></div></div></div>
        <div class="col-6 col-xl-3"><div class="card h-100"><div class="card-body"><div class="small text-muted">Open 7+ days</div><div class="sg-bignum">{{ number_format($risk['agedCount']) }}</div></div></div></div>
    </div>
    @if($marketplaceRisk->isNotEmpty())
        <div class="card mb-3"><div class="card-header fw-semibold">Marketplace financial risk</div><div class="table-responsive"><table class="table table-sm mb-0"><thead><tr><th>Marketplace</th><th class="text-end">Open issues</th><th class="text-end">Potential exposure</th></tr></thead><tbody>@foreach($marketplaceRisk as $mr)<tr><td>{{ $mr->marketplace }}</td><td class="text-end">{{ number_format($mr->issue_count) }}</td><td class="text-end">₹{{ number_format((float)$mr->exposure, 2) }}</td></tr>@endforeach</tbody></table></div></div>
    @endif
    <div class="row g-3 mb-3">
        @foreach ($T as $k => $l)
            @php $b=$byType[$k] ?? null; @endphp
            <div class="col-6 col-xl"><a class="text-decoration-none" href="{{ route('reconciliation.index', ['type' => $k]) }}">
                    <div class="card h-100">
                        <div class="card-body">
                            <div class="small text-muted">{{ $l }}</div>
                            <div class="sg-bignum {{ $b ? 'text-danger' : '' }}">{{ $b->c ?? 0 }}</div>
                            @if ($b && $b->d !== null)
                                <div class="small text-muted mt-1">₹{{ number_format((float) $b->d, 2) }} difference</div>
                            @endif
                        </div>
                    </div>
                </a></div>
        @endforeach
    </div>
    <div class="card mb-3">
        <div class="card-body d-flex flex-wrap align-items-center gap-3">
            <div class="small"><span class="badge text-bg-success">Matched {{ $orderStatus['MATCHED'] ?? 0 }}</span>
                <span class="badge text-bg-danger">Mismatch {{ $orderStatus['MISMATCH'] ?? 0 }}</span> <span
                    class="badge text-bg-secondary">Waiting for payout {{ $orderStatus['PENDING'] ?? 0 }}</span>
            </div>
            <div class="ms-auto d-flex gap-2">
                <a class="btn btn-sm btn-outline-secondary" href="{{ route('reconciliation.export', request()->query()) }}"><i class="bi bi-download"></i> Export CSV</a>
                @foreach (['OPEN' => 'Open', 'IGNORED' => 'Ignored', 'RESOLVED' => 'Closed', 'all' => 'All'] as $k => $l)
                    <a href="{{ route('reconciliation.index', ['status' => $k]) }}"
                        class="btn btn-sm btn-{{ $status === $k ? 'primary' : 'outline-secondary' }}">{{ $l }}</a>
                @endforeach
                @can('finance.manage')
                    <form method="POST" action="{{ route('reconciliation.run') }}">@csrf<button
                            class="btn btn-sm btn-primary"><i class="bi bi-arrow-repeat"></i> Run reconciliation</button></form>
                @endcan
            </div>
        </div>
    </div>
    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Issue</th>
                        <th>Order</th>
                        <th class="text-end">Expected</th>
                        <th class="text-end">Actual</th>
                        <th class="text-end">Difference</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($issues as $i)
                        <tr>
                            <td><span class="fw-semibold">{{ $T[$i->issue_type] ?? $i->issue_type }}</span>
                                <div class="small text-muted">{{ $i->message }}</div>
                                @if($i->status === 'OPEN' && $i->detected_at)
                                    <div class="small mt-1"><span class="badge text-bg-{{ $i->detected_at->lte(now()->subDays(7)) ? 'danger' : 'light' }}">{{ $i->detected_at->diffForHumans() }}</span></div>
                                @endif
                                @if ($i->note)
                                    <div class="small fst-italic">Note: {{ $i->note }}</div>
                                @endif
                            </td>
                            <td>
                                @if ($i->order)
                                    <a class="text-decoration-none"
                                        href="{{ route('orders.show', $i->order) }}">{{ $i->order->external_order_id }}</a>
                                @else
                                    {{ $i->external_order_id ?? '—' }}
                                @endif
                                @if ($i->settlement)
                                    <div class="small"><a class="text-decoration-none"
                                            href="{{ route('settlements.show', $i->settlement_id) }}">{{ $i->settlement->external_settlement_id }}</a>
                                    </div>
                                @endif
                            </td>
                            <td class="text-end">
                                {{ $i->expected_amount !== null ? '₹' . number_format($i->expected_amount, 2) : '—' }}</td>
                            <td class="text-end">{{ $i->actual_amount !== null ? '₹' . number_format($i->actual_amount, 2) : '—' }}
                            </td>
                            <td class="text-end fw-bold {{ (float) $i->difference < 0 ? 'text-danger' : '' }}">
                                {{ $i->difference !== null ? '₹' . number_format($i->difference, 2) : '—' }}</td>
                            <td><span
                                    class="badge text-bg-{{ ['OPEN' => 'danger', 'IGNORED' => 'secondary', 'RESOLVED' => 'success'][$i->status] }}">{{ ucfirst(strtolower($i->status === 'RESOLVED' ? 'closed' : $i->status)) }}</span>
                            </td>
                            <td class="text-end" style="min-width:230px">
                                @can('finance.manage')
                                    @if ($i->status === 'OPEN')
                                        <form method="POST" action="{{ route('reconciliation.ignore', $i) }}"
                                            class="d-flex gap-1">@csrf<input name="note" class="form-control form-control-sm"
                                                placeholder="Reason to ignore"><button
                                                class="btn btn-sm btn-outline-secondary">Ignore</button></form>
                                    @elseif($i->status === 'IGNORED')
                                        <form method="POST" action="{{ route('reconciliation.reopen', $i) }}">@csrf<button
                                                class="btn btn-sm btn-outline-primary">Reopen</button></form>
                                    @endif
                                @endcan
                            </td>
                        </tr>
                    @empty<tr>
                            <td colspan="7" class="text-center text-muted py-5">Nothing here. Import settlements, then
                                press "Run reconciliation".</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($issues->hasPages())
            <div class="card-footer">{{ $issues->links() }}</div>
        @endif
        <div class="card-footer small text-muted">Issues close themselves when the problem is fixed. "Ignored" issues stay
            ignored. Fee checks need fee rules; payout checks wait the number of days set in Settings.</div>
    </div>
@endsection
