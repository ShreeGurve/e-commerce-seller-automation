@extends('layouts.app')
@section('title', 'Inventory & Cash Flow Planning')
@section('subtitle', 'Estimate upcoming purchase commitments and replenishment investment using internal sales and stock
    data')
@section('content')
    <div class="alert alert-info py-2 small">Planning estimates only. Revenue projections are based on historical non-test
        orders; collection rate is an assumption you control. This page does not represent bank balances or confirmed cash
        receipts and does not create purchase orders.</div>
    <form method="GET" action="{{ route('cash-planning.index') }}" class="card mb-3">
        <div class="card-body">
            <div class="row g-2 align-items-end">
                <div class="col-md-3"><label class="form-label">Sales lookback</label><select name="lookback_days"
                        class="form-select">
                        <option value="30" @selected($lookback === 30)>30 days</option>
                        <option value="60" @selected($lookback === 60)>60 days</option>
                        <option value="90" @selected($lookback === 90)>90 days</option>
                    </select></div>
                <div class="col-md-3"><label class="form-label">Planning horizon</label><select name="horizon_days"
                        class="form-select">
                        <option value="30" @selected($horizon === 30)>30 days</option>
                        <option value="60" @selected($horizon === 60)>60 days</option>
                        <option value="90" @selected($horizon === 90)>90 days</option>
                    </select></div>
                <div class="col-md-3"><label class="form-label">Expected collection rate (%)</label><input
                        class="form-control" type="number" name="collection_rate" min="0" max="100"
                        step="1" value="{{ $collectionRate }}"></div>
                <div class="col-md-3"><button class="btn btn-primary w-100">Recalculate plan</button></div>
            </div>
        </div>
    </form>
    <div class="row g-3 mb-3">
        <div class="col-md-6 col-xl-3">
            <div class="card h-100">
                <div class="card-body">
                    <div class="small text-muted">Projected order revenue</div>
                    <div class="fs-4 fw-bold">₹{{ number_format($forecastRevenue, 2) }}</div>
                    <div class="small text-muted">{{ $horizon }}-day run-rate estimate</div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="card h-100">
                <div class="card-body">
                    <div class="small text-muted">Estimated collections</div>
                    <div class="fs-4 fw-bold text-success">₹{{ number_format($expectedCollections, 2) }}</div>
                    <div class="small text-muted">At {{ number_format($collectionRate, 0) }}% assumed collection rate</div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="card h-100">
                <div class="card-body">
                    <div class="small text-muted">Open PO payable balance</div>
                    <div class="fs-4 fw-bold">₹{{ number_format($purchaseCommitments, 2) }}</div>
                    <div class="small text-muted">All ORDERED purchase orders</div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="card h-100">
                <div class="card-body">
                    <div class="small text-muted">Replenishment investment</div>
                    <div class="fs-4 fw-bold">₹{{ number_format($reorderInvestment, 2) }}</div>
                    <div class="small text-muted">Suggested quantities × current unit costs</div>
                </div>
            </div>
        </div>
    </div>
    <div class="card mb-3">
        <div class="card-header fw-semibold">Cash planning overview</div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <div class="small text-muted">PO balances due within horizon</div>
                    <div class="fs-5 fw-bold">₹{{ number_format($dueInHorizon, 2) }}</div>
                </div>
                <div class="col-md-4">
                    <div class="small text-muted">Overdue PO balances</div>
                    <div class="fs-5 fw-bold {{ $overdueCommitments > 0 ? 'text-danger' : '' }}">
                        ₹{{ number_format($overdueCommitments, 2) }}</div>
                </div>
                <div class="col-md-4">
                    <div class="small text-muted">Indicative planning position</div>
                    <div class="fs-5 fw-bold {{ $netPlanningPosition < 0 ? 'text-danger' : 'text-success' }}">
                        ₹{{ number_format($netPlanningPosition, 2) }}</div>
                    <div class="small text-muted">Estimated collections − due PO balance − suggested replenishment</div>
                </div>
            </div>
        </div>
    </div>
    <div class="card mb-3">
        <div class="card-header d-flex justify-content-between"><span class="fw-semibold">Suggested replenishment
                investment</span><a class="btn btn-outline-secondary btn-sm"
                href="{{ route('inventory-forecast.index') }}">Open detailed forecast</a></div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th class="text-end">Available</th>
                        <th class="text-end">Reserved</th>
                        <th class="text-end">On order</th>
                        <th class="text-end">Suggested qty</th>
                        <th class="text-end">Unit cost</th>
                        <th class="text-end">Est. investment</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($replenishment as $row)
                        <tr>
                            <td><a class="fw-semibold text-decoration-none"
                                    href="{{ route('inventory.show', $row->product) }}">{{ $row->product->sku }}</a>
                                <div class="small text-muted">{{ $row->product->name }}</div>
                            </td>
                            <td class="text-end">{{ number_format($row->available) }}</td>
                            <td class="text-end">{{ number_format($row->reserved) }}</td>
                            <td class="text-end">{{ number_format($row->on_order) }}</td>
                            <td class="text-end fw-semibold">{{ number_format($row->suggested) }}</td>
                            <td class="text-end">₹{{ number_format($row->unit_cost, 2) }}</td>
                            <td class="text-end fw-semibold">₹{{ number_format($row->investment, 2) }}</td>
                        </tr>
                    @empty<tr>
                            <td colspan="7" class="text-center text-muted py-4">No replenishment investment suggested for
                                the selected period.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer small text-muted">Suggested quantity covers up to 30 days of average historical demand, less
            available stock (after reservations) and quantities on ORDERED purchase orders. Products without recorded
            current costs show zero investment until costs are entered.</div>
    </div>
    <div class="card">
        <div class="card-header fw-semibold">Open purchase commitments</div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>PO number</th>
                        <th>Supplier</th>
                        <th>Expected delivery</th>
                        <th>Status</th>
                        <th class="text-end">Total</th>
                        <th class="text-end">Paid</th>
                        <th class="text-end">Balance</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($openPurchases as $po)
                        <tr>
                            <td><a href="{{ route('purchases.show', $po) }}">{{ $po->po_number }}</a></td>
                            <td>{{ $po->supplier?->name ?? '—' }}</td>
                            <td>{{ $po->expected_delivery_date?->format('d M Y') ?? 'Not set' }}</td>
                            <td><span class="badge text-bg-primary">{{ $po->status }}</span></td>
                            <td class="text-end">₹{{ number_format((float) $po->total_amount, 2) }}</td>
                            <td class="text-end">₹{{ number_format((float) $po->amount_paid, 2) }}</td>
                            <td class="text-end fw-semibold">
                                ₹{{ number_format(max(0, (float) $po->total_amount - (float) $po->amount_paid), 2) }}</td>
                        </tr>
                    @empty<tr>
                            <td colspan="7" class="text-center text-muted py-4">No ordered purchase commitments.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
