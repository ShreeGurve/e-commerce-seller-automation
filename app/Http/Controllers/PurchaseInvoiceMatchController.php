<?php

namespace App\Http\Controllers;

use App\Models\Purchase;
use App\Models\PurchaseInvoiceMatch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PurchaseInvoiceMatchController extends Controller
{
    public function index(Request $request)
    {
        $matches = PurchaseInvoiceMatch::with(['purchase.supplier', 'creator', 'reviewer'])
            ->when($request->status, fn ($q, $status) => $q->where('match_status', $status))
            ->when($request->q, function ($q, $term) {
                $q->where('supplier_invoice_number', 'like', "%{$term}%")
                    ->orWhereHas('purchase', fn ($p) => $p->where('po_number', 'like', "%{$term}%"));
            })
            ->latest()->paginate(20)->withQueryString();

        $summary = [
            'total' => PurchaseInvoiceMatch::count(),
            'matched' => PurchaseInvoiceMatch::where('match_status', 'MATCHED')->count(),
            'review' => PurchaseInvoiceMatch::where('match_status', 'REVIEW_REQUIRED')->count(),
            'variance' => (float) PurchaseInvoiceMatch::where('match_status', 'REVIEW_REQUIRED')->sum('variance_amount'),
        ];

        return view('purchases.invoice-matching', compact('matches', 'summary'));
    }

    public function create(Request $request)
    {
        $purchases = Purchase::with('supplier')
            ->whereIn('status', ['ORDERED', 'RECEIVED'])
            ->whereDoesntHave('invoiceMatch')
            ->when($request->purchase_id, fn ($q, $id) => $q->whereKey($id))
            ->latest('purchase_date')->get();
        return view('purchases.invoice-match-create', compact('purchases'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'purchase_id' => 'required|integer|exists:purchases,id',
            'supplier_invoice_number' => 'required|string|max:100',
            'supplier_invoice_date' => 'required|date',
            'invoice_subtotal' => 'required|numeric|min:0|max:9999999999',
            'invoice_tax' => 'required|numeric|min:0|max:9999999999',
            'invoice_shipping' => 'nullable|numeric|min:0|max:9999999999',
            'invoice_other' => 'nullable|numeric|min:0|max:9999999999',
            'tolerance_amount' => 'nullable|numeric|min:0|max:1000000',
            'review_notes' => 'nullable|string|max:2000',
        ]);

        $match = DB::transaction(function () use ($data, $request) {
            $purchase = Purchase::with('items')->lockForUpdate()->findOrFail($data['purchase_id']);
            if (! in_array($purchase->status, ['ORDERED', 'RECEIVED'], true)) {
                throw ValidationException::withMessages(['purchase_id' => 'Only ordered or received purchase orders can be invoice-matched.']);
            }
            if (PurchaseInvoiceMatch::where('purchase_id', $purchase->id)->exists()) {
                throw ValidationException::withMessages(['purchase_id' => 'An invoice match already exists for this purchase order.']);
            }
            if ($purchase->supplier_id !== (int) $purchase->supplier_id) {
                throw ValidationException::withMessages(['purchase_id' => 'Invalid purchase supplier.']);
            }
            $expected = (float) $purchase->total_amount;
            $invoiceTotal = round((float) $data['invoice_subtotal'] + (float) $data['invoice_tax'] + (float) ($data['invoice_shipping'] ?? 0) + (float) ($data['invoice_other'] ?? 0), 2);
            $variance = round($invoiceTotal - $expected, 2);
            $tolerance = (float) ($data['tolerance_amount'] ?? 0.01);
            $status = abs($variance) <= $tolerance ? 'MATCHED' : 'REVIEW_REQUIRED';

            return PurchaseInvoiceMatch::create([
                'purchase_id' => $purchase->id,
                'supplier_invoice_number' => $data['supplier_invoice_number'],
                'supplier_invoice_date' => $data['supplier_invoice_date'],
                'invoice_subtotal' => $data['invoice_subtotal'], 'invoice_tax' => $data['invoice_tax'],
                'invoice_shipping' => $data['invoice_shipping'] ?? 0, 'invoice_other' => $data['invoice_other'] ?? 0,
                'invoice_total' => $invoiceTotal, 'tolerance_amount' => $tolerance,
                'variance_amount' => $variance, 'match_status' => $status,
                'review_notes' => $data['review_notes'] ?? null, 'created_by' => $request->user()->id,
            ]);
        });

        return redirect()->route('purchase-invoice-matching.index')->with('success', $match->match_status === 'MATCHED'
            ? 'Supplier invoice matched within the configured tolerance.'
            : 'Invoice recorded. A value variance requires review.');
    }

    public function review(Request $request, PurchaseInvoiceMatch $match)
    {
        $data = $request->validate([
            'decision' => 'required|in:ACCEPT_VARIANCE,REJECT',
            'review_notes' => 'required|string|max:2000',
        ]);
        if ($match->match_status !== 'REVIEW_REQUIRED') {
            throw ValidationException::withMessages(['decision' => 'Only invoices requiring review can be reviewed.']);
        }
        $match->update([
            'match_status' => $data['decision'] === 'ACCEPT_VARIANCE' ? 'REVIEWED' : 'REJECTED',
            'review_notes' => $data['review_notes'], 'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);
        return redirect()->route('purchase-invoice-matching.index')->with('success', 'Invoice review decision saved.');
    }
}
