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

    /** Internal-only test order form. It never contacts a marketplace. */
    public function createTest()
    {
        return view('orders.test-create', [
            'marketplaces' => Marketplace::active()->get(),
            'products' => Product::where('status', '!=', 'discontinued')->orderBy('sku')->get(),
        ]);
    }

    /** Creates a clearly marked TEST order without changing stock or financial reports. */
    public function storeTest(Request $request)
    {
        $v = $request->validate([
            'marketplace_id' => 'required|exists:marketplaces,id',
            'order_date' => 'required|date|before_or_equal:now',
            'customer_ref' => 'nullable|string|max:120',
            'ship_state' => 'nullable|string|max:80',
            'shipping_charged' => 'nullable|numeric|min:0|max:9999999999',
            'notes' => 'nullable|string|max:2000',
            'items' => 'required|array|min:1|max:50',
            'items.*.product_id' => 'required|distinct|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1|max:100000',
            'items.*.unit_price' => 'required|numeric|min:0|max:9999999999',
            'items.*.discount' => 'nullable|numeric|min:0|max:9999999999',
        ]);
        $externalId = 'TEST-' . now()->format('YmdHis') . '-' . strtoupper(bin2hex(random_bytes(2)));
        $order = $this->service->create([
            'marketplace_id' => $v['marketplace_id'],
            'external_order_id' => $externalId,
            'order_date' => $v['order_date'],
            'status' => 'CONFIRMED',
            'fulfillment_type' => 'SELF',
            'customer_ref' => $v['customer_ref'] ?? 'TEST CUSTOMER',
            'ship_state' => $v['ship_state'] ?? null,
            'shipping_charged' => $v['shipping_charged'] ?? 0,
            'notes' => trim(($v['notes'] ?? '') . "\n[TEST ORDER] Internal simulation only. No marketplace API call; stock unchanged."),
            'source' => 'TEST',
            'deduct_stock' => false,
        ], $v['items'], $request->user());

        return redirect()->route('orders.show', $order)->with('success', "Test order {$order->order_number} created. Stock and financial reports were not changed.");
    }

    public function show(Order $order)
    {
        $order->load(['marketplace', 'items.product', 'history.user', 'returns', 'refunds', 'creator', 'profits', 'settlementItems.settlement', 'claims', 'fulfillment', 'fulfillmentEvents.user']);
        return view('orders.show', compact('order'));
    }

    public function status(Request $request, Order $order)
    {
        $d = $request->validate(['to' => ['required', Rule::in(Order::STATUSES)], 'note' => 'nullable|string|max:255']);
        $this->service->changeStatus($order, $d['to'], $request->user(), $d['note'] ?? null);
        return back()->with('success', "Order moved to {$d['to']}.");
    }
}
