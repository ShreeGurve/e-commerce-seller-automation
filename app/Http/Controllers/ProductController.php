<?php
namespace App\Http\Controllers;

use App\Models\{ActivityLog, Brand, Category, Marketplace, Product, ProductCostHistory, ProductMarketplace};
use App\Services\{ActivityLogger, ProductCostService};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    public function __construct(private ProductCostService $costs) {}

    public function index(Request $request)
    {
        $products = Product::with(['category', 'currentCosts'])
            ->when($request->q, fn ($q, $s) => $q->where(fn ($w) => $w->where('name', 'like', "%$s%")->orWhere('sku', 'like', "%$s%")))
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->when($request->category_id, fn ($q, $c) => $q->where('category_id', $c))
            ->latest('id')->paginate(20)->withQueryString();

        return view('products.index', ['products' => $products, 'categories' => Category::orderBy('name')->get()]);
    }

    public function create()
    {
        return view('products.create', $this->formData(new Product(['status' => 'active'])));
    }

    public function store(Request $request)
    {
        $data = $request->validate($this->rules());
        $costs = collect($data['costs'] ?? [])->filter(fn ($v) => $v !== null && $v !== '');
        unset($data['costs']);

        $product = DB::transaction(function () use ($data, $costs) {
            $product = Product::create($data + ['created_by' => auth()->id()]);
            foreach ($costs as $type => $amount) {
                if (array_key_exists($type, ProductCostHistory::TYPES)) {
                    $this->costs->setCost($product, $type, $amount, now(), 'Initial cost');
                }
            }
            ActivityLogger::log('product.created', $product, null, ['sku' => $product->sku, 'name' => $product->name]);
            return $product;
        });

        return redirect()->route('products.show', $product)->with('success', 'Product created.');
    }

    public function show(Product $product)
    {
        $product->load(['category', 'brand', 'marketplaces.marketplace']);
        return view('products.show', [
            'product' => $product,
            'marketplaces' => Marketplace::active()->get(),
            'listings' => \App\Models\Listing::with(['marketplace', 'current:id,version_no,score'])->where('product_id', $product->id)->get(),
            'keywords' => \App\Models\Keyword::with('marketplace:id,name')->where('product_id', $product->id)->orderByRaw("CASE keyword_type WHEN 'PRIMARY' THEN 1 WHEN 'SECONDARY' THEN 2 ELSE 3 END")->limit(60)->get(),
            'costRows' => $product->costs()->orderBy('cost_type')->orderByDesc('effective_from')->get()->groupBy('cost_type'),
            'activity' => ActivityLog::with('user')->where('subject_type', $product->getMorphClass())
                ->where('subject_id', $product->id)->latest('id')->limit(40)->get(),
        ]);
    }

    public function edit(Product $product)
    {
        return view('products.edit', $this->formData($product));
    }

    public function update(Request $request, Product $product)
    {
        $data = $request->validate($this->rules($product));
        unset($data['costs']);
        $product->fill($data);
        $dirty = $product->getDirty();
        $old = array_intersect_key($product->getOriginal(), $dirty);
        $product->save();
        if ($dirty) {
            ActivityLogger::log('product.updated', $product, $old, $dirty);
        }
        return redirect()->route('products.show', $product)->with('success', 'Product updated.');
    }

    public function destroy(Product $product)
    {
        $product->delete();
        ActivityLogger::log('product.deleted', $product, ['sku' => $product->sku]);
        return redirect()->route('products.index')->with('success', 'Product archived (soft deleted).');
    }

    public function storeCost(Request $request, Product $product)
    {
        $data = $request->validate([
            'cost_type' => ['required', Rule::in(array_keys(ProductCostHistory::TYPES))],
            'amount' => 'required|numeric|min:0|max:9999999999',
            'effective_from' => 'required|date',
            'note' => 'nullable|string|max:255',
        ]);
        $row = $this->costs->setCost($product, $data['cost_type'], $data['amount'], \Carbon\Carbon::parse($data['effective_from']), $data['note'] ?? null);
        return redirect()->route('products.show', [$product, 'tab' => 'costs'])
            ->with($row ? 'success' : 'error', $row ? 'Cost saved; previous value kept in history.' : 'Amount unchanged.');
    }

    public function storeMarketplace(Request $request, Product $product)
    {
        $data = $request->validate([
            'marketplace_id' => 'required|exists:marketplaces,id',
            'marketplace_sku' => ['required', 'string', 'max:120',
                Rule::unique('product_marketplace')->where('marketplace_id', $request->marketplace_id)],
            'external_product_id' => 'nullable|string|max:60',
            'fulfillment_sku' => 'nullable|string|max:60',
            'listing_status' => ['required', Rule::in(['active', 'inactive', 'suppressed', 'draft'])],
        ]);
        $map = $product->marketplaces()->create($data);
        ActivityLogger::log('product.marketplace_mapped', $product, null, $data);
        return redirect()->route('products.show', [$product, 'tab' => 'marketplace'])->with('success', 'Marketplace mapping added.');
    }

    public function destroyMarketplace(Product $product, ProductMarketplace $mapping)
    {
        abort_unless($mapping->product_id === $product->id, 404);
        $mapping->delete();
        ActivityLogger::log('product.marketplace_unmapped', $product, $mapping->only(['marketplace_id', 'marketplace_sku']));
        return redirect()->route('products.show', [$product, 'tab' => 'marketplace'])->with('success', 'Mapping removed.');
    }

    private function formData(Product $product): array
    {
        return [
            'product' => $product,
            'brands' => Brand::where('is_active', true)->orderBy('name')->get(),
            'categories' => Category::where('is_active', true)->orderBy('name')->get(),
        ];
    }

    private function rules(?Product $p = null): array
    {
        $money = 'nullable|numeric|min:0|max:9999999999';
        return [
            'sku' => ['required', 'string', 'max:100', Rule::unique('products', 'sku')->ignore($p?->id)],
            'parent_sku' => 'nullable|string|max:100',
            'name' => 'required|string|max:255',
            'brand_id' => 'nullable|exists:brands,id',
            'category_id' => 'nullable|exists:categories,id',
            'subcategory' => 'nullable|string|max:120',
            'product_type' => 'nullable|string|max:80',
            'hsn' => 'nullable|string|max:20',
            'gst_rate' => 'nullable|numeric|between:0,100',
            'mrp' => $money, 'default_selling_price' => $money, 'min_selling_price' => $money, 'target_selling_price' => $money,
            'weight_g' => $money, 'length_cm' => $money, 'width_cm' => $money, 'height_cm' => $money,
            'status' => ['required', Rule::in(Product::STATUSES)],
            'description' => 'nullable|string|max:5000',
            'notes' => 'nullable|string|max:2000',
            'costs' => 'nullable|array',
            'costs.*' => $money,
        ];
    }
}
