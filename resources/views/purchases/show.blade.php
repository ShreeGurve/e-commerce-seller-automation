@extends('layouts.app')
@section('title', $purchase->po_number)
@section('subtitle', $purchase->supplier->name . ' · ' . $purchase->purchase_date->format('d M Y'))
@section('content')
    @php
        $badge = ['DRAFT' => 'secondary', 'ORDERED' => 'info', 'RECEIVED' => 'success', 'CANCELLED' => 'dark'];
        $pay = ['UNPAID' => 'danger', 'PARTIAL' => 'warning', 'PAID' => 'success'];
    @endphp
    <div class="d-flex flex-wrap gap-2 align-items-center mb-3">
        <span class="badge text-bg-{{ $badge[$purchase->status] }} fs-6">{{ ucfirst(strtolower($purchase->status)) }}</span>
        <span
            class="badge text-bg-{{ $pay[$purchase->payment_status] }} fs-6">{{ ucfirst(strtolower($purchase->payment_status)) }}</span>
        <div class="ms-auto d-flex flex-wrap gap-2">
            @can('purchases.edit')
                @if ($purchase->isEditable())
                    <a href="{{ route('purchases.edit', $purchase) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                    @if ($purchase->items->contains(fn ($item) => (int) $item->received_qty < (int) $item->quantity))
                        <button type="button" class="btn btn-sm btn-success" data-bs-toggle="collapse" data-bs-target="#receiveItems"><i class="bi bi-box-arrow-in-down"></i> Receive quantities</button>
                    @endif
                    <form method="POST" action="{{ route('purchases.cancel', $purchase) }}"
                        onsubmit="return confirm('Cancel this purchase?')">@csrf<button
                            class="btn btn-sm btn-outline-danger">Cancel</button></form>
                @endif
            @endcan
        </div>
    </div>
    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card">
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th class="text-end">Qty</th>
                                <th class="text-end">Unit cost</th>
                                <th class="text-end">GST</th>
                                <th class="text-end">Line total</th>
                                <th class="text-end">Received</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($purchase->items as $i)
                                <tr>
                                    <td><a class="text-decoration-none fw-semibold"
                                            href="{{ route('products.show', $i->product_id) }}">{{ $i->product->sku }}</a>
                                        <div class="small text-muted">{{ $i->product->name }}</div>
                                    </td>
                                    <td class="text-end">{{ $i->quantity }}</td>
                                    <td class="text-end">₹{{ number_format($i->unit_cost, 2) }}</td>
                                    <td class="text-end">{{ rtrim(rtrim((string) $i->gst_rate, '0'), '.') }}%</td>
                                    <td class="text-end">₹{{ number_format($i->line_total, 2) }}</td>
                                    <td class="text-end">{{ $i->received_qty }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            @can('purchases.edit')
                @if ($purchase->isEditable() && $purchase->items->contains(fn ($item) => (int) $item->received_qty < (int) $item->quantity))
                    <div class="card mt-3 collapse" id="receiveItems">
                        <div class="card-header fw-semibold">Receive delivered quantities</div>
                        <div class="card-body">
                            <form method="POST" action="{{ route('purchases.receive-quantities', $purchase) }}">
                                @csrf
                                <p class="small text-muted">Enter only the units delivered in this shipment. Leave other items at zero. Inventory updates only for the quantities entered.</p>
                                <div class="table-responsive"><table class="table table-sm align-middle">
                                    <thead><tr><th>Product</th><th class="text-end">Outstanding</th><th style="width:160px">Received now</th></tr></thead>
                                    <tbody>
                                    @foreach ($purchase->items as $item)
                                        @php $remaining = (int) $item->quantity - (int) $item->received_qty; @endphp
                                        @if ($remaining > 0)
                                            <tr>
                                                <td>{{ $item->product->sku }} <span class="text-muted">{{ $item->product->name }}</span></td>
                                                <td class="text-end">{{ $remaining }}</td>
                                                <td><input type="number" class="form-control form-control-sm" name="quantities[{{ $item->id }}]" min="0" max="{{ $remaining }}" value="0"></td>
                                            </tr>
                                        @endif
                                    @endforeach
                                    </tbody>
                                </table></div>
                                <button class="btn btn-success btn-sm" onclick="return confirm('Add these received quantities to inventory?')">Save receipt</button>
                            </form>
                        </div>
                    </div>
                @endif
            @endcan
            @if ($purchase->notes)
                <div class="card mt-3">
                    <div class="card-body"><b>Notes</b>
                        <div class="text-muted">{{ $purchase->notes }}</div>
                    </div>
                </div>
            @endif
        </div>
        <div class="col-lg-4">
            <div class="card mb-3">
                <div class="card-body">
                    @foreach (['Subtotal' => $purchase->subtotal, 'GST' => $purchase->gst_amount, 'Shipping' => $purchase->shipping_charges, 'Other charges' => $purchase->other_charges] as $l => $v)
                        <div class="d-flex justify-content-between"><span
                                class="text-muted">{{ $l }}</span><span>₹{{ number_format($v, 2) }}</span></div>
                    @endforeach
                    <div class="d-flex justify-content-between fw-bold fs-5 border-top pt-2 mt-2">
                        <span>Total</span><span>₹{{ number_format($purchase->total_amount, 2) }}</span></div>
                    <div class="d-flex justify-content-between"><span
                            class="text-muted">Paid</span><span>₹{{ number_format($purchase->amount_paid, 2) }}</span></div>
                    <div class="d-flex justify-content-between fw-semibold"><span>Balance
                            due</span><span>₹{{ number_format((float) $purchase->balance(), 2) }}</span></div>
                    <hr>
                    <div class="small text-muted">Invoice:
                        {{ $purchase->invoice_number ?? '—' }}{{ $purchase->invoice_date ? ' · ' . $purchase->invoice_date->format('d M Y') : '' }}<br>
                        Created by {{ $purchase->creator->name ?? '—' }}@if ($purchase->received_at)
                            <br>Received {{ $purchase->received_at->format('d M Y H:i') }}
                        @endif
                    </div>
                </div>
            </div>
            @can('purchases.edit')@if ($purchase->status !== 'CANCELLED' && (float) $purchase->balance() > 0)
                <div class="card">
                    <div class="card-header">Record payment</div>
                    <div class="card-body">
                        <form method="POST" action="{{ route('purchases.payment', $purchase) }}" class="d-flex gap-2">@csrf
                            <input type="number" name="amount" step="0.01" min="0.01"
                                max="{{ $purchase->balance() }}" class="form-control" placeholder="Amount ₹" required>
                            <button class="btn btn-primary">Add</button>
                        </form>
                    </div>
                </div>
            @endif @endcan
        </div>
    </div>
@endsection
