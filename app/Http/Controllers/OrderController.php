<?php
namespace App\Http\Controllers;

use App\Models\{Marketplace, Order, Product};
use App\Services\OrderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class OrderController extends Controller
{
    public function __construct(private OrderService $service) {}

    public function index(Request $request)
    {
        $orders = Order::with('marketplace')->withCount('items')
            ->when($request->q, fn ($q, $s) => $q->where(fn ($w) => $w->where('order_number', 'like', "%$s%")->orWhere('external_order_id', 'like', "%$s%")->orWhere('customer_ref', 'like', "%$s%")))
            ->when($request->marketplace_id, fn ($q, $v) => $q->where('marketplace_id', $v))
            ->when($request->status, fn ($q, $v) => $q->where('status', $v))
            ->when($request->from, fn ($q, $v) => $q->whereDate('order_date', '>=', $v))
            ->when($request->to, fn ($q, $v) => $q->whereDate('order_date', '<=', $v))
            ->latest('order_date')->latest('id')->paginate(25)->withQueryString();
        return view('orders.index', [
            'orders' => $orders, 'marketplaces' => Marketplace::active()->get(),
            'counts' => Order::select('status', DB::raw('count(*) c'))->groupBy('status')->pluck('c', 'status'),
        ]);
    }

    public function create()
    {
        return view('orders.create', [
            'marketplaces' => Marketplace::active()->get(),
            'products' => Product::where('status', '!=', 'discontinued')->orderBy('sku')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $money = 'nullable|numeric|min:0|max:9999999999';
        $v = $request->validate([
            'marketplace_id' => 'required|exists:marketplaces,id', 'external_order_id' => 'required|string|max:60',
            'order_date' => 'required|date|before_or_equal:now', 'status' => ['required', Rule::in(['IMPORTED', 'CONFIRMED'])],
            'fulfillment_type' => ['required', Rule::in(array_keys(Order::FULFILLMENT))],
            'customer_ref' => 'nullable|string|max:120', 'ship_state' => 'nullable|string|max:80', 'shipping_charged' => $money, 'notes' => 'nullable|string|max:2000',
            'items' => 'required|array|min:1|max:100',
            'items.*.product_id' => 'required|distinct|exists:products,id', 'items.*.quantity' => 'required|integer|min:1|max:100000',
            'items.*.unit_price' => 'required|numeric|min:0|max:9999999999', 'items.*.discount' => $money,
        ], ['items.required' => 'Add at least one product.', 'items.*.product_id.distinct' => 'Same product added twice.']);
        $items = $v['items']; unset($v['items']);
        $order = $this->service->create($v, $items, $request->user());
        return redirect()->route('orders.show', $order)->with('success', "Order {$order->order_number} created and stock deducted.");
    }

    public function show(Order $order)
    {
        $order->load(['marketplace', 'items.product', 'history.user', 'returns', 'refunds', 'creator', 'profits', 'settlementItems.settlement', 'claims']);
        return view('orders.show', compact('order'));
    }

    public function status(Request $request, Order $order)
    {
        $d = $request->validate(['to' => ['required', Rule::in(Order::STATUSES)], 'note' => 'nullable|string|max:255']);
        $this->service->changeStatus($order, $d['to'], $request->user(), $d['note'] ?? null);
        return back()->with('success', "Order moved to {$d['to']}.");
    }
}
