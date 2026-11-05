@extends('layouts.app')
@section('content')
<div class="container-fluid">
    <h1>Supplier Invoice Payments</h1>
    <p class="text-muted">Record payments already made outside this system. This page does not initiate bank or UPI transfers.</p>
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @foreach($invoices as $invoice)
        @php($paid = (float)$invoice->payments->where('status','CONFIRMED')->sum('amount'))
        @php($credited = (float) $invoice->creditApplications->sum('amount'))
        @php($remaining = max(0,(float)$invoice->invoice_total-$paid-$credited))
        <div class="card mb-3"><div class="card-body">
            <div class="d-flex justify-content-between flex-wrap">
                <div><h5>Invoice {{ $invoice->supplier_invoice_number }}</h5>
                    <div>{{ $invoice->purchase?->supplier?->name ?? 'Supplier' }} · PO {{ $invoice->purchase?->po_number }}</div>
                </div>
                <div class="text-end">Invoice: ₹{{ number_format((float)$invoice->invoice_total,2) }}<br>
                    Paid: ₹{{ number_format($paid,2) }} · Credits: ₹{{ number_format($credited,2) }} · Balance: <strong>₹{{ number_format($remaining,2) }}</strong></div>
            </div>
            @if($remaining > 0)
            <form method="POST" action="{{ route('supplier-payments.store',$invoice) }}" class="row g-2 mt-2">
                @csrf
                <div class="col-md-2"><input class="form-control" type="number" step="0.01" min="0.01" max="{{ $remaining }}" name="amount" placeholder="Amount" required></div>
                <div class="col-md-2"><input class="form-control" type="date" name="payment_date" required></div>
                <div class="col-md-2"><select class="form-select" name="payment_method">@foreach(['BANK_TRANSFER','UPI','CASH','CHEQUE','CARD','OTHER'] as $method)<option value="{{ $method }}">{{ str_replace('_',' ',$method) }}</option>@endforeach</select></div>
                <div class="col-md-3"><input class="form-control" name="reference_number" placeholder="Transaction/reference no."></div>
                <div class="col-md-2"><input class="form-control" name="notes" placeholder="Notes"></div>
                <div class="col-md-1"><button class="btn btn-primary">Record</button></div>
            </form>
            @endif
            @if($invoice->payments->count())
                <small class="d-block mt-2 text-muted">Payments: @foreach($invoice->payments as $payment){{ $payment->payment_date?->format('d M Y') }} ₹{{ number_format((float)$payment->amount,2) }} ({{ $payment->payment_method }})@if(!$loop->last); @endif @endforeach</small>
            @endif
        </div></div>
    @endforeach
    {{ $invoices->links() }}
</div>
@endsection
