@extends('layouts.app')

@section('title', 'Supplier Invoice Automation')
@section('subtitle', 'Document vault, GSTIN checks and invoice review workflow')

@section('content')
<div class="container-fluid py-3">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
        <div>
            <h2 class="h4 mb-1">Supplier Invoice Automation</h2>
            <p class="text-muted mb-0">Securely store invoice files, validate GSTIN formats and track manual verification.</p>
        </div>
        <div class="d-flex gap-2">
            <a class="btn btn-outline-secondary" href="{{ route('purchase-invoice-matching.index') }}">Invoice matching</a>
            <a class="btn btn-outline-secondary" href="{{ route('purchase-invoice-line-checks.index') }}">Line reconciliation</a>
        </div>
    </div>

    <div class="alert alert-info">
        <strong>Processing mode:</strong> Documents are stored privately. OCR extraction is not activated in this package;
        enter invoice details manually after reviewing the uploaded PDF or image. GSTIN checks are format and basic state-code checks, not government portal verification.
    </div>

    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="table-light">
                    <tr><th>Purchase / Supplier</th><th>Invoice</th><th>Document</th><th>GST check</th><th>Processing</th><th style="min-width:360px">Actions</th></tr>
                </thead>
                <tbody>
                @forelse($matches as $match)
                    <tr>
                        <td>
                            <div class="fw-semibold">{{ $match->purchase->po_number }}</div>
                            <small class="text-muted">{{ $match->purchase->supplier->name ?? 'Supplier' }}</small>
                        </td>
                        <td>{{ $match->supplier_invoice_number }}<small class="d-block text-muted">{{ optional($match->supplier_invoice_date)->format('d M Y') }}</small></td>
                        <td>
                            @if($match->document_path)
                                <a href="{{ route('purchase-invoice-documents.download', $match) }}">{{ $match->document_original_name }}</a>
                                <small class="d-block text-muted">{{ number_format(($match->document_size ?? 0) / 1024, 1) }} KB</small>
                            @else <span class="text-muted">Not uploaded</span> @endif
                        </td>
                        <td><span class="badge {{ $match->gst_validation_status === 'FORMAT_VALID' ? 'text-bg-success' : ($match->gst_validation_status === 'REVIEW_REQUIRED' ? 'text-bg-warning' : 'text-bg-secondary') }}">{{ $match->gst_validation_status }}</span></td>
                        <td><span class="badge text-bg-light">{{ $match->document_processing_status }}</span></td>
                        <td>
                            <form method="POST" enctype="multipart/form-data" action="{{ route('purchase-invoice-documents.upload', $match) }}" class="d-flex gap-2 mb-2">
                                @csrf
                                <input class="form-control form-control-sm" type="file" name="invoice_document" accept=".pdf,.jpg,.jpeg,.png,.webp" required>
                                <button class="btn btn-sm btn-primary">Upload</button>
                            </form>
                            <form method="POST" action="{{ route('purchase-invoice-documents.gst-validation', $match) }}" class="row g-1">
                                @csrf
                                <div class="col-6"><input class="form-control form-control-sm text-uppercase" name="supplier_gstin" maxlength="15" placeholder="Supplier GSTIN" value="{{ $match->supplier_gstin }}"></div>
                                <div class="col-6"><input class="form-control form-control-sm text-uppercase" name="buyer_gstin" maxlength="15" placeholder="Buyer GSTIN" value="{{ $match->buyer_gstin }}"></div>
                                <div class="col-7"><input class="form-control form-control-sm" name="place_of_supply" placeholder="Place of supply" value="{{ $match->place_of_supply }}"></div>
                                <div class="col-5"><button class="btn btn-sm btn-outline-primary w-100">Validate GSTIN</button></div>
                                @if($match->gst_validation_notes)
                                    <div class="col-12 small text-warning">{{ $match->gst_validation_notes }}</div>
                                @endif
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted py-5">No invoice matches yet. Create an invoice match first, then upload its document here.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer bg-white">{{ $matches->links() }}</div>
    </div>
</div>
@endsection
