<?php

namespace App\Http\Controllers;

use App\Models\ApprovalRequest;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Supplier;
use App\Services\PurchaseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReorderApprovalController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'supplier_id' => ['required', 'exists:suppliers,id'],
            'purchase_date' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1', 'max:200'],
            'items.*.include' => ['nullable', 'boolean'],
            'items.*.product_id' => ['required', 'distinct', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:1000000'],
            'items.*.unit_cost' => ['required', 'numeric', 'min:0', 'max:9999999999'],
        ]);

        $selected = collect($data['items'])->filter(fn ($line) => !empty($line['include']))->values();
        if ($selected->isEmpty()) {
            throw ValidationException::withMessages(['items' => 'Select at least one product for approval.']);
        }

        $ids = $selected->pluck('product_id')->map(fn ($id) => (int) $id)->all();
        $products = Product::with('inventory')->whereIn('id', $ids)->where('status', 'active')->get()->keyBy('id');
        $open = PurchaseItem::query()->join('purchases', 'purchases.id', '=', 'purchase_items.purchase_id')
            ->where('purchases.status', 'ORDERED')->whereIn('purchase_items.product_id', $ids)
            ->select('purchase_items.product_id')
            ->selectRaw('SUM(purchase_items.quantity - purchase_items.received_qty) as outstanding_qty')
            ->groupBy('purchase_items.product_id')->pluck('outstanding_qty', 'product_id');

        $lines = [];
        $estimatedTotal = 0.0;
        foreach ($selected as $line) {
            $product = $products->get((int) $line['product_id']);
            $inventory = $product?->inventory;
            $threshold = (int) ($inventory?->reorder_level ?? 0);
            if ($threshold <= 0) $threshold = (int) ($inventory?->minimum_stock ?? 0);
            if (!$product || $threshold <= 0 || (int) ($inventory?->available_qty ?? 0) > $threshold) {
                throw ValidationException::withMessages(['items' => 'A selected product is no longer eligible for reorder. Refresh suggestions and submit again.']);
            }
            $quantity = (int) $line['quantity'];
            $unitCost = round((float) $line['unit_cost'], 2);
            $lineTotal = round($quantity * $unitCost, 2);
            $estimatedTotal += $lineTotal;
            $lines[] = [
                'product_id' => (int) $product->id,
                'sku' => (string) $product->sku,
                'product_name' => (string) $product->name,
                'quantity' => $quantity,
                'unit_cost' => number_format($unitCost, 2, '.', ''),
                'line_estimate' => number_format($lineTotal, 2, '.', ''),
                'available_at_request' => (int) ($inventory?->available_qty ?? 0),
                'outstanding_order_qty' => (int) ($open[$product->id] ?? 0),
            ];
        }

        $supplier = Supplier::findOrFail($data['supplier_id']);
        $approval = ApprovalRequest::create([
            'business_id' => $request->user()->business_id ?? null,
            'title' => 'Reorder purchase plan — ' . $supplier->name,
            'type' => 'purchase',
            'request_type' => 'purchase',
            'reference' => 'REORDER-' . now()->format('YmdHis'),
            'amount' => number_format($estimatedTotal, 2, '.', ''),
            'description' => trim(($data['notes'] ?? 'Reorder plan submitted for approval.') . "\nSupplier: {$supplier->name}\nRequested PO date: {$data['purchase_date']}\nEstimated amount excludes final tax, shipping and other charges."),
            'current_value' => ['supplier_id' => $supplier->id, 'status' => 'suggestion'],
            'proposed_value' => [
                'supplier_id' => $supplier->id,
                'supplier_name' => $supplier->name,
                'purchase_date' => $data['purchase_date'],
                'notes' => $data['notes'] ?? null,
                'items' => $lines,
                'estimated_subtotal' => number_format($estimatedTotal, 2, '.', ''),
                'currency' => 'INR',
            ],
            'source' => 'AUTOMATION',
            'status' => 'pending',
            'requested_by' => $request->user()->id,
        ]);

        return redirect()->route('reorders.approvals')->with('success', 'Purchase plan submitted for approval. No purchase order has been created. Request #' . $approval->id . '.');
    }

    public function index(Request $request)
    {
        $query = ApprovalRequest::query()->with(['requester', 'reviewer'])
            ->where('request_type', 'purchase')->where('source', 'AUTOMATION');
        if (!$request->user()->isSuperAdmin()) {
            $query->where('business_id', $request->user()->business_id);
        }
        $requests = $query->latest()->paginate(15)->withQueryString();
        return view('reorders.approvals', compact('requests'));
    }

    public function createDraft(Request $request, ApprovalRequest $approvalRequest, PurchaseService $service)
    {
        abort_unless($approvalRequest->request_type === 'purchase' && $approvalRequest->source === 'AUTOMATION', 404);
        if (!$request->user()->isSuperAdmin()) {
            abort_unless($approvalRequest->business_id !== null && (int) $approvalRequest->business_id === (int) $request->user()->business_id, 404);
        }
        abort_unless($approvalRequest->status === 'approved', 422, 'Only approved purchase plans can create a draft PO.');
        abort_unless(!$approvalRequest->executed_at, 422, 'A draft PO has already been created for this approval.');

        $payload = $approvalRequest->proposed_value ?? [];
        $supplierId = (int) ($payload['supplier_id'] ?? 0);
        $supplier = Supplier::find($supplierId);
        abort_unless($supplier, 422, 'The original supplier is no longer available.');
        $items = collect($payload['items'] ?? [])->map(fn ($line) => [
            'product_id' => (int) $line['product_id'],
            'quantity' => (int) $line['quantity'],
            'unit_cost' => (string) $line['unit_cost'],
            'gst_rate' => 0,
        ])->all();
        abort_if(empty($items), 422, 'The approved request contains no purchase items.');

        $purchase = DB::transaction(function () use ($approvalRequest, $payload, $supplierId, $items, $service, $request) {
            $locked = ApprovalRequest::whereKey($approvalRequest->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->status === 'approved' && !$locked->executed_at, 422, 'This approval has already been used or is no longer approved.');
            $created = $service->save(null, [
                'supplier_id' => $supplierId,
                'purchase_date' => $payload['purchase_date'] ?? today()->toDateString(),
                'status' => 'DRAFT',
                'notes' => 'Created from approved reorder request #' . $locked->id . '. ' . ($payload['notes'] ?? ''),
            ], $items, $request->user());
            $locked->update(['executed_at' => now()]);
            return $created;
        });

        return redirect()->route('purchases.show', $purchase)->with('success', 'Approved plan converted into a draft PO. Review it before marking it ordered.');
    }
}
