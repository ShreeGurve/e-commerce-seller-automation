<?php

namespace App\Http\Controllers;

use App\Models\PurchaseInvoiceMatch;
use App\Models\PurchaseInvoiceLineCheck;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PurchaseInvoiceLineCheckController extends Controller
{
    public function index(Request $request)
    {
        $matches = PurchaseInvoiceMatch::with(['purchase.supplier', 'purchase.items.product', 'lineChecks'])
            ->when($request->status, fn ($q, $status) => $q->whereHas('lineChecks', fn ($l) => $l->where('line_status', $status)))
            ->latest()->paginate(15)->withQueryString();

        $summary = [
            'matches' => PurchaseInvoiceMatch::count(),
            'checked_lines' => PurchaseInvoiceLineCheck::count(),
            'review_lines' => PurchaseInvoiceLineCheck::where('line_status', 'REVIEW_REQUIRED')->count(),
            'quantity_variance' => (int) PurchaseInvoiceLineCheck::sum('quantity_variance'),
        ];

        return view('purchases.invoice-line-checks', compact('matches', 'summary'));
    }

    public function store(Request $request, PurchaseInvoiceMatch $match)
    {
        $data = $request->validate([
            'lines' => 'required|array|min:1',
            'lines.*.purchase_item_id' => 'required|integer|distinct|exists:purchase_items,id',
            'lines.*.invoice_quantity' => 'required|integer|min:0|max:1000000',
            'lines.*.invoice_unit_cost' => 'required|numeric|min:0|max:9999999999',
            'lines.*.review_note' => 'nullable|string|max:1000',
        ]);

        DB::transaction(function () use ($data, $match) {
            $match = PurchaseInvoiceMatch::with('purchase.items')->lockForUpdate()->findOrFail($match->id);
            foreach ($data['lines'] as $line) {
                $item = $match->purchase->items->firstWhere('id', (int) $line['purchase_item_id']);
                if (! $item) {
                    throw ValidationException::withMessages(['lines' => 'A selected item does not belong to this purchase order.']);
                }

                $qty = (int) $line['invoice_quantity'];
                $unit = round((float) $line['invoice_unit_cost'], 2);
                $expectedQty = (int) $item->received_qty;
                $qtyVariance = $qty - $expectedQty;
                $costVariance = round(($unit - (float) $item->unit_cost) * $qty, 2);
                $status = ($qtyVariance === 0 && abs($costVariance) <= 0.01) ? 'MATCHED' : 'REVIEW_REQUIRED';

                PurchaseInvoiceLineCheck::updateOrCreate(
                    ['purchase_invoice_match_id' => $match->id, 'purchase_item_id' => $item->id],
                    [
                        'invoice_quantity' => $qty,
                        'invoice_unit_cost' => number_format($unit, 2, '.', ''),
                        'invoice_line_total' => number_format($qty * $unit, 2, '.', ''),
                        'quantity_variance' => $qtyVariance,
                        'cost_variance' => number_format($costVariance, 2, '.', ''),
                        'line_status' => $status,
                        'review_note' => $line['review_note'] ?? null,
                    ]
                );
            }
        });

        return redirect()->route('purchase-invoice-line-checks.index')
            ->with('success', 'Invoice line quantities and unit costs have been checked against received purchase quantities and PO costs.');
    }
}
