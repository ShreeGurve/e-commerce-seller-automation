<?php
namespace App\Http\Controllers;

use App\Models\{Inventory, InventoryTransaction, Product};
use App\Services\{ActivityLogger, InventoryService};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class InventoryController extends Controller
{
    public function __construct(private InventoryService $service) {}

    public function index(Request $request)
    {
        $filter = $request->get('stock');
        $products = Product::with('inventory')
            ->when($request->q, fn ($q, $s) => $q->where(fn ($w) => $w->where('name', 'like', "%$s%")->orWhere('sku', 'like', "%$s%")))
            ->when($filter === 'low', fn ($q) => $q->whereHas('inventory', fn ($i) => $i->where('minimum_stock', '>', 0)->whereColumn('available_qty', '<=', 'minimum_stock')))
            ->when($filter === 'out', fn ($q) => $q->where(fn ($w) => $w->doesntHave('inventory')->orWhereHas('inventory', fn ($i) => $i->where('available_qty', '<=', 0))))
            ->orderBy('sku')->paginate(25)->withQueryString();

        $stockValue = DB::table('inventory')
            ->join('product_cost_history as c', fn ($j) => $j->on('c.product_id', '=', 'inventory.product_id')->where('c.cost_type', 'COGS')->whereNull('c.effective_to'))
            ->sum(DB::raw('inventory.available_qty * c.amount'));

        return view('inventory.index', [
            'products' => $products,
            'totalUnits' => (int) Inventory::sum('available_qty'),
            'lowCount' => Inventory::where('minimum_stock', '>', 0)->whereColumn('available_qty', '<=', 'minimum_stock')->count(),
            'outCount' => Product::where(fn ($w) => $w->doesntHave('inventory')->orWhereHas('inventory', fn ($i) => $i->where('available_qty', '<=', 0)))->count(),
            'stockValue' => $stockValue,
            'mismatches' => $this->service->mismatches()->count(),
        ]);
    }

    public function show(Product $product)
    {
        $inv = $this->service->ensureRow($product);
        return view('inventory.show', [
            'product' => $product, 'inv' => $inv,
            'ledger' => InventoryTransaction::with('user')->where('product_id', $product->id)->latest('id')->paginate(30),
        ]);
    }

    public function adjust(Request $request, Product $product)
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(InventoryTransaction::MANUAL)],
            'quantity' => 'required|integer|min:-1000000|max:1000000|not_in:0',
            'note' => 'nullable|string|max:255',
        ]);
        $this->service->record($product, $data['type'], (int) $data['quantity'], ['note' => $data['note'] ?? null]);
        return redirect()->route('inventory.show', $product)->with('success', 'Stock updated.');
    }

    public function levels(Request $request, Product $product)
    {
        $data = $request->validate(['minimum_stock' => 'required|integer|min:0|max:1000000', 'reorder_level' => 'required|integer|min:0|max:1000000']);
        $inv = $this->service->ensureRow($product);
        $old = $inv->only(array_keys($data));
        $inv->update($data);
        ActivityLogger::log('inventory.levels_changed', $product, $old, $data);
        return redirect()->route('inventory.show', $product)->with('success', 'Stock levels saved.');
    }
}
