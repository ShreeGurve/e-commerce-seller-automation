@extends('layouts.app')

@section('title', 'Marketplace Financial Analytics')

@section('content')
<div class="container-fluid py-3">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h4 class="mb-1">Marketplace Financial Analytics</h4>
            <div class="text-muted small">Read-only view of order profitability, settlement coverage, fees, refunds and bank payouts.</div>
        </div>
        <a href="{{ route('finance.payout-reconciliation.index') }}" class="btn btn-outline-primary btn-sm">Payout reconciliation</a>
    </div>

    <form method="GET" class="card card-body mb-3">
        <div class="row g-2 align-items-end">
            <div class="col-md-3"><label class="form-label">From</label><input type="date" name="from" class="form-control" value="{{ $report['filters']['from'] }}" required></div>
            <div class="col-md-3"><label class="form-label">To</label><input type="date" name="to" class="form-control" value="{{ $report['filters']['to'] }}" required></div>
            <div class="col-md-4"><label class="form-label">Marketplace</label><select name="marketplace_id" class="form-select"><option value="">All marketplaces</option>@foreach($marketplaces as $marketplace)<option value="{{ $marketplace->id }}" @selected((string) $report['filters']['marketplace_id'] === (string) $marketplace->id)>{{ $marketplace->name }}</option>@endforeach</select></div>
            <div class="col-md-2"><button class="btn btn-primary w-100">Apply filters</button></div>
        </div>
    </form>

    @php($t = $report['totals'])
    <div class="row g-3 mb-3">
        @foreach([
            ['Orders', number_format((int)($t->orders ?? 0))],
            ['Revenue', '₹'.number_format((float)($t->revenue ?? 0), 2)],
            ['Net profit*', '₹'.number_format((float)($t->net_profit ?? 0), 2)],
            ['Marketplace fees', '₹'.number_format((float)($t->fees ?? 0), 2)],
            ['Refunds', '₹'.number_format($report['refund_total'], 2)],
            ['Claim recovery', '₹'.number_format($report['claim_recovery'], 2)],
            ['Bank credits', '₹'.number_format((float)($report['payouts']->bank_credits ?? 0), 2)],
            ['Payouts needing review', number_format((int)($report['payouts']->attention_count ?? 0))],
        ] as [$label, $value])
            <div class="col-6 col-xl-3"><div class="card h-100"><div class="card-body"><div class="text-muted small">{{ $label }}</div><div class="fs-5 fw-semibold">{{ $value }}</div></div></div></div>
        @endforeach
    </div>

    <div class="row g-3 mb-3">
        <div class="col-lg-6"><div class="card h-100"><div class="card-header fw-semibold">Profit & reconciliation coverage</div><div class="card-body">
            <div class="d-flex justify-content-between mb-1"><span>Actual-profit line coverage</span><strong>{{ number_format($report['actual_coverage'], 1) }}%</strong></div>
            <div class="progress mb-3" style="height:10px"><div class="progress-bar" role="progressbar" style="width: {{ $report['actual_coverage'] }}%" aria-valuenow="{{ $report['actual_coverage'] }}" aria-valuemin="0" aria-valuemax="100"></div></div>
            <div class="d-flex justify-content-between"><span>Settlements</span><strong>{{ number_format((int)($report['settlements']->count ?? 0)) }}</strong></div>
            <div class="d-flex justify-content-between"><span>Net settlement value</span><strong>₹{{ number_format((float)($report['settlements']->net_settlement ?? 0), 2) }}</strong></div>
            <div class="d-flex justify-content-between"><span>Matched bank credits</span><strong>₹{{ number_format((float)($report['payouts']->matched_credits ?? 0), 2) }}</strong></div>
            <div class="d-flex justify-content-between"><span>Recorded operating expenses</span><strong>₹{{ number_format($report['expenses'], 2) }}</strong></div>
            <hr><div class="d-flex justify-content-between"><span>Estimated operating profit*</span><strong>₹{{ number_format($report['estimated_operating_profit'], 2) }}</strong></div>
        </div></div></div>
        <div class="col-lg-6"><div class="card h-100"><div class="card-header fw-semibold">Settlement fee breakdown</div><div class="table-responsive"><table class="table table-sm mb-0"><thead><tr><th>Fee type</th><th class="text-end">Lines</th><th class="text-end">Amount</th></tr></thead><tbody>
            @forelse($report['fee_breakdown'] as $fee)<tr><td>{{ str_replace('_', ' ', $fee->kind) }}</td><td class="text-end">{{ number_format($fee->lines) }}</td><td class="text-end">₹{{ number_format((float)$fee->amount, 2) }}</td></tr>@empty<tr><td colspan="3" class="text-center text-muted py-3">No settlement fee lines in this period.</td></tr>@endforelse
        </tbody></table></div></div></div>
    </div>

    <div class="card"><div class="card-header fw-semibold">Marketplace performance</div><div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th>Marketplace</th><th class="text-end">Orders</th><th class="text-end">Revenue</th><th class="text-end">Net profit*</th><th class="text-end">Margin</th></tr></thead><tbody>
        @forelse($report['by_marketplace'] as $row)<tr><td>{{ $report['marketplace_names'][$row->marketplace_id] ?? ('Marketplace #'.$row->marketplace_id) }}</td><td class="text-end">{{ number_format((int)$row->orders) }}</td><td class="text-end">₹{{ number_format((float)$row->revenue, 2) }}</td><td class="text-end">₹{{ number_format((float)$row->net_profit, 2) }}</td><td class="text-end">{{ (float)$row->revenue > 0 ? number_format(((float)$row->net_profit / (float)$row->revenue) * 100, 1).'%' : '—' }}</td></tr>@empty<tr><td colspan="5" class="text-center text-muted py-3">No profitability records for this period.</td></tr>@endforelse
    </tbody></table></div></div>
    <p class="text-muted small mt-3 mb-0">* Net profit uses existing order-profit rows (actual where available, estimated otherwise). Estimated operating profit subtracts recorded expenses; it is a management estimate, not a statutory P&amp;L or bank-balance figure. Settlement and payout totals are period-based and are not added to order revenue.</p>
</div>
@endsection
