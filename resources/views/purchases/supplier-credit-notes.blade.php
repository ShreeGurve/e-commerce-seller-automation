@extends('layouts.app')
@section('content')
<div class="container-fluid">
    <h1>Supplier Credit Notes</h1>
    <p class="text-muted">Track credit notes received from suppliers and apply them against matching supplier invoices. No supplier balance or accounting ledger is automatically changed.</p>
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <div class="card mb-4"><div class="card-body"><h5>Record Credit Note</h5>
        <form method="POST" action="{{ route('supplier-credit-notes.store') }}" class="row g-2">@csrf
            <div class="col-md-3"><select class="form-select" name="supplier_id" required><option value="">Select supplier</option>@foreach($suppliers as $supplier)<option value="{{ $supplier->id }}">{{ $supplier->name }}</option>@endforeach</select></div>
            <div class="col-md-3"><select class="form-select" name="purchase_invoice_match_id"><option value="">Link invoice (optional)</option>@foreach($invoices as $invoice)<option value="{{ $invoice->id }}">{{ $invoice->purchase?->supplier?->name }} · {{ $invoice->supplier_invoice_number }}</option>@endforeach</select></div>
            <div class="col-md-2"><input class="form-control" name="supplier_return_id" type="number" min="1" placeholder="Return ID (optional)"></div>
            <div class="col-md-2"><input class="form-control" name="credit_note_number" placeholder="Credit note no." required></div>
            <div class="col-md-2"><input class="form-control" name="credit_date" type="date" required></div>
            <div class="col-md-2"><input class="form-control" name="credit_amount" type="number" step="0.01" min="0.01" placeholder="Amount ₹" required></div>
            <div class="col-md-8"><input class="form-control" name="notes" placeholder="Notes"></div>
            <div class="col-md-2"><button class="btn btn-primary">Save Credit Note</button></div>
        </form>
    </div></div>
    @foreach($credits as $credit)
        @php($applied=(float)$credit->applications->sum('amount'))
        @php($available=max(0,(float)$credit->credit_amount-$applied))
        <div class="card mb-3"><div class="card-body">
            <div class="d-flex justify-content-between flex-wrap"><div><h5>{{ $credit->credit_note_number }}</h5>{{ $credit->supplier?->name }} · {{ $credit->credit_date?->format('d M Y') }} · {{ $credit->status }}</div>
                <div>Credit ₹{{ number_format((float)$credit->credit_amount,2) }} · Applied ₹{{ number_format($applied,2) }} · Available <strong>₹{{ number_format($available,2) }}</strong></div></div>
            @if($available > 0)
            <form method="POST" action="{{ route('supplier-credit-notes.apply',$credit) }}" class="row g-2 mt-2">@csrf
                <div class="col-md-5"><select name="purchase_invoice_match_id" class="form-select" required><option value="">Apply to supplier invoice</option>@foreach($invoices->where('purchase.supplier_id',$credit->supplier_id) as $invoice)<option value="{{ $invoice->id }}">{{ $invoice->supplier_invoice_number }} · ₹{{ number_format((float)$invoice->invoice_total,2) }}</option>@endforeach</select></div>
                <div class="col-md-2"><input name="amount" class="form-control" type="number" step="0.01" min="0.01" max="{{ $available }}" placeholder="Amount" required></div>
                <div class="col-md-2"><input name="applied_date" class="form-control" type="date" required></div>
                <div class="col-md-2"><input name="notes" class="form-control" placeholder="Notes"></div>
                <div class="col-md-1"><button class="btn btn-outline-primary">Apply</button></div>
            </form>@endif
        </div></div>
    @endforeach
    {{ $credits->links() }}
</div>
@endsection
