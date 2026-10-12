<?php
namespace App\Http\Controllers;

use App\Models\{Product, Purchase, Supplier};
use App\Services\PurchaseService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PurchaseController extends Controller
{
    public function __construct(private PurchaseService $service) {}

    public function index(Request $request)
    {
        $purchases = Purchase::with('supplier')
            ->when($request->q, fn ($q, $s) => $q->where(fn ($w) => $w->where('po_number', 'like', "%$s%")->orWhere('invoice_number', 'like', "%$s%")))
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->when($request->supplier_id, fn ($q, $s) => $q->where('supplier_id', $s))
            ->latest('purchase_date')->latest('id')->paginate(20)->withQueryString();
        return view('purchases.index', ['purchases' => $purchases, 'suppliers' => Supplier::orderBy('name')->get()]);
    }

    public function create()
    {
        return view('purchases.create', $this->formData(new Purchase(['purchase_date' => now(), 'status' => 'DRAFT'])));
    }

    public function store(Request $request)
    {
        [$data, $items] = $this->validated($request);
        $purchase = $this->service->save(null, $data, $items, $request->user());
        return redirect()->route('purchases.show', $purchase)->with('success', "Purchase {$purchase->po_number} saved.");
    }

    public function show(Purchase $purchase)
    {
        $purchase->load(['supplier', 'items.product', 'creator']);
        return view('purchases.show', compact('purchase'));
    }

    public function edit(Purchase $purchase)
    {
        if (! $purchase->isEditable()) {
            return redirect()->route('purchases.show', $purchase)->with('error', 'Only Draft/Ordered purchases can be edited.');
        }
        $purchase->load('items');
        return view('purchases.edit', $this->formData($purchase));
    }

    public function update(Request $request, Purchase $purchase)
    {
        [$data, $items] = $this->validated($request);
        $this->service->save($purchase, $data, $items, $request->user());
        return redirect()->route('purchases.show', $purchase)->with('success', 'Purchase updated.');
    }

    public function receive(Purchase $purchase)
    {
        $this->service->receive($purchase);
        return redirect()->route('purchases.show', $purchase)->with('success', 'Stock received and added to inventory.');
    }

    public function cancel(Purchase $purchase)
    {
        $this->service->cancel($purchase);
        return redirect()->route('purchases.show', $purchase)->with('success', 'Purchase cancelled.');
    }

    public function payment(Request $request, Purchase $purchase)
    {
        $data = $request->validate(['amount' => 'required|numeric|gt:0|max:9999999999']);
        $this->service->addPayment($purchase, $data['amount']);
        return redirect()->route('purchases.show', $purchase)->with('success', 'Payment recorded.');
    }

    private function formData(Purchase $purchase): array
    {
        return [
            'purchase' => $purchase,
            'suppliers' => Supplier::where('is_active', true)->orWhere('id', $purchase->supplier_id)->orderBy('name')->get(),
            'products' => Product::with('currentCosts')->orderBy('sku')->get(),
        ];
    }

    private function validated(Request $request): array
    {
        $money = 'nullable|numeric|min:0|max:9999999999';
        $v = $request->validate([
            'supplier_id' => 'required|exists:suppliers,id',
            'purchase_date' => 'required|date',
            'status' => ['required', Rule::in(['DRAFT', 'ORDERED'])],
            'invoice_number' => 'nullable|string|max:60', 'invoice_date' => 'nullable|date',
            'shipping_charges' => $money, 'other_charges' => $money, 'notes' => 'nullable|string|max:2000',
            'items' => 'required|array|min:1|max:200',
            'items.*.product_id' => 'required|distinct|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1|max:1000000',
            'items.*.unit_cost' => 'required|numeric|min:0|max:9999999999',
            'items.*.gst_rate' => 'nullable|numeric|between:0,100',
        ], ['items.required' => 'Add at least one product.', 'items.*.product_id.distinct' => 'Same product added twice.']);
        $items = $v['items'];
        unset($v['items']);
        return [$v, $items];
    }
}
