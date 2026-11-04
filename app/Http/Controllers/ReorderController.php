<?php
namespace App\Http\Controllers;

use App\Models\{Product, Purchase, PurchaseItem, Supplier};
use App\Services\PurchaseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReorderController extends Controller
{
    public function index()
    {
        $open = PurchaseItem::query()
            ->join('purchases', 'purchases.id', '=', 'purchase_items.purchase_id')
            ->where('purchases.status', 'ORDERED')
            ->select('purchase_items.product_id')
            ->selectRaw('SUM(purchase_items.quantity - purchase_items.received_qty) as outstanding_qty')
            ->groupBy('purchase_items.product_id')->pluck('outstanding_qty', 'product_id');

        $products = Product::with(['inventory', 'currentCosts'])
            ->where('status', 'active')->orderBy('sku')->get()
            ->map(function ($product) use ($open) {
                $inv = $product->inventory;
                $available = (int) ($inv?->available_qty ?? 0);
                $reorderAt = (int) ($inv?->reorder_level ?? 0);
                if ($reorderAt <= 0) $reorderAt = (int) ($inv?->minimum_stock ?? 0);
                $onOrder = (int) ($open[$product->id] ?? 0);
                $target = $reorderAt > 0 ? max($reorderAt * 2, $reorderAt + 1) : 0;
                $suggested = $target > 0 ? max(0, $target - $available - $onOrder) : 0;
                return (object) [
                    'product' => $product, 'available' => $available, 'reorder_at' => $reorderAt,
                    'on_order' => $onOrder, 'suggested' => $suggested,
                    'unit_cost' => $product->currentCost('COGS'),
                ];
            })->filter(fn ($row) => $row->reorder_at > 0 && $row->available <= $row->reorder_at)
            ->values();

        return view('reorders.index', [
            'rows' => $products,
            'suppliers' => Supplier::where('is_active', true)->orderBy('name')->get(),
            'lowCount' => $products->count(),
            'totalSuggested' => $products->sum('suggested'),
        ]);
    }

    public function createPurchase(Request $request, PurchaseService $service)
    {
        $data = $request->validate([
            'supplier_id' => 'required|exists:suppliers,id',
            'purchase_date' => 'required|date',
            'notes' => 'nullable|string|max:2000',
            'items' => 'required|array|min:1',
            'items.*.include' => 'nullable|boolean',
            'items.*.product_id' => 'required|distinct|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1|max:1000000',
            'items.*.unit_cost' => 'required|numeric|min:0|max:9999999999',
        ]);

        $selected = collect($data['items'])->filter(fn ($line) => ! empty($line['include']))->values();
        if ($selected->isEmpty()) {
            throw ValidationException::withMessages(['items' => 'Select at least one product for the draft purchase order.']);
        }
        $ids = $selected->pluck('product_id')->map(fn ($id) => (int) $id)->all();
        $eligible = Product::with('inventory')->whereIn('id', $ids)->get()->keyBy('id');
        foreach ($selected as $line) {
            $product = $eligible->get((int) $line['product_id']);
            $inventory = $product?->inventory;
            $trigger = (int) ($inventory?->reorder_level ?? 0);
            if ($trigger <= 0) $trigger = (int) ($inventory?->minimum_stock ?? 0);
            if (! $product || $trigger <= 0 || (int) ($inventory?->available_qty ?? 0) > $trigger) {
                throw ValidationException::withMessages(['items' => 'One or more selected products are no longer eligible for reorder. Refresh the reorder suggestions and try again.']);
            }
        }

        $items = $selected->map(fn ($line) => [
            'product_id' => $line['product_id'], 'quantity' => $line['quantity'],
            'unit_cost' => $line['unit_cost'], 'gst_rate' => 0,
        ])->all();
        $purchase = $service->save(null, [
            'supplier_id' => $data['supplier_id'], 'purchase_date' => $data['purchase_date'],
            'status' => 'DRAFT', 'notes' => $data['notes'] ?? 'Created from Phase 7 reorder planning.',
        ], $items, $request->user());

        return redirect()->route('purchases.show', $purchase)->with('success', 'Draft purchase order created from reorder planning. Review it before marking it ordered.');
    }
}
