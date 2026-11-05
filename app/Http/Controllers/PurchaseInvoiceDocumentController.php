<?php

namespace App\Http\Controllers;

use App\Models\PurchaseInvoiceMatch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class PurchaseInvoiceDocumentController extends Controller
{
    public function index()
    {
        $matches = PurchaseInvoiceMatch::with('purchase.supplier')->latest()->paginate(20);
        return view('purchases.invoice-documents', compact('matches'));
    }

    public function upload(Request $request, PurchaseInvoiceMatch $match)
    {
        $data = $request->validate([
            'invoice_document' => 'required|file|mimes:pdf,jpg,jpeg,png,webp|max:10240',
        ]);

        if ($match->document_path) {
            Storage::disk('local')->delete($match->document_path);
        }

        $file = $data['invoice_document'];
        $path = $file->store('private/supplier-invoices/' . $match->id, 'local');

        $match->update([
            'document_path' => $path,
            'document_original_name' => $file->getClientOriginalName(),
            'document_mime' => $file->getMimeType(),
            'document_size' => $file->getSize(),
            // OCR is deliberately marked as manual review until an OCR provider is configured.
            'document_processing_status' => 'UPLOADED_MANUAL_REVIEW',
            'extracted_text' => null,
        ]);

        return back()->with('success', 'Invoice document securely uploaded. Review the document and enter extracted values manually.');
    }

    public function download(PurchaseInvoiceMatch $match)
    {
        abort_unless($match->document_path && Storage::disk('local')->exists($match->document_path), 404);
        return Storage::disk('local')->download(
            $match->document_path,
            $match->document_original_name ?: 'supplier-invoice'
        );
    }

    public function validateGst(Request $request, PurchaseInvoiceMatch $match)
    {
        $data = $request->validate([
            'supplier_gstin' => ['nullable', 'string', 'size:15', 'regex:/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z][1-9A-Z]Z[0-9A-Z]$/'],
            'buyer_gstin' => ['nullable', 'string', 'size:15', 'regex:/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z][1-9A-Z]Z[0-9A-Z]$/'],
            'place_of_supply' => 'nullable|string|max:100',
            'gst_validation_notes' => 'nullable|string|max:2000',
        ], [
            'supplier_gstin.regex' => 'Supplier GSTIN format is invalid.',
            'buyer_gstin.regex' => 'Buyer GSTIN format is invalid.',
        ]);

        $supplier = strtoupper($data['supplier_gstin'] ?? '');
        $buyer = strtoupper($data['buyer_gstin'] ?? '');
        $notes = [];
        if ($supplier && $match->purchase->supplier && $match->purchase->supplier->gstin
            && strtoupper($match->purchase->supplier->gstin) !== $supplier) {
            $notes[] = 'Invoice supplier GSTIN does not match the GSTIN stored for this supplier.';
        }
        if ($supplier && $buyer && substr($supplier, 0, 2) === substr($buyer, 0, 2)) {
            $notes[] = 'Supplier and buyer GSTIN state codes match; verify whether intra-state tax treatment is correct.';
        } elseif ($supplier && $buyer) {
            $notes[] = 'Supplier and buyer GSTIN state codes differ; verify whether inter-state tax treatment is correct.';
        }

        $match->update([
            'supplier_gstin' => $supplier ?: null,
            'buyer_gstin' => $buyer ?: null,
            'place_of_supply' => $data['place_of_supply'] ?? null,
            'gst_validation_notes' => trim(implode(' ', array_filter([$data['gst_validation_notes'] ?? null, ...$notes]))),
            'gst_validation_status' => $supplier && $buyer ? (count($notes) ? 'REVIEW_REQUIRED' : 'FORMAT_VALID') : 'INCOMPLETE',
        ]);

        return back()->with('success', 'GSTIN format and basic state-code checks completed. Confirm tax treatment with the actual invoice and applicable GST rules.');
    }
}
