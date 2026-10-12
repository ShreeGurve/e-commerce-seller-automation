<?php
namespace App\Http\Controllers;

use App\Models\{Order, ProductReturn, ReturnItem, ReturnMedia};
use App\Services\{ActivityLogger, ReturnService};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ReturnController extends Controller
{
    public function __construct(private ReturnService $service) {}

    public function index(Request $request)
    {
        $returns = ProductReturn::with(['order', 'marketplace'])->withCount('items')
            ->when($request->q, fn ($q, $s) => $q->where(fn ($w) => $w->where('return_number', 'like', "%$s%")->orWhereHas('order', fn ($o) => $o->where('external_order_id', 'like', "%$s%")->orWhere('order_number', 'like', "%$s%"))))
            ->when($request->status, fn ($q, $v) => $q->where('status', $v))
            ->when($request->return_type, fn ($q, $v) => $q->where('return_type', $v))
            ->latest('return_date')->latest('id')->paginate(25)->withQueryString();
        return view('returns.index', compact('returns'));
    }

    public function create(Order $order)
    {
        $order->load('items.product', 'marketplace');
        $returned = ReturnItem::whereIn('order_item_id', $order->items->pluck('id'))->selectRaw('order_item_id, SUM(quantity) q')->groupBy('order_item_id')->pluck('q', 'order_item_id');
        return view('returns.create', compact('order', 'returned'));
    }

    public function store(Request $request, Order $order)
    {
        $d = $request->validate([
            'return_type' => ['required', Rule::in(array_keys(ProductReturn::TYPES))], 'return_date' => 'required|date|before_or_equal:today',
            'reason_text' => 'nullable|string|max:255', 'external_return_id' => 'nullable|string|max:60',
            'qty' => 'required|array', 'qty.*' => 'nullable|integer|min:0|max:100000',
        ]);
        $return = $this->service->create($order, $d, $d['qty'], $request->user());
        return redirect()->route('returns.show', $return)->with('success', "Return {$return->return_number} created. Inspect it when the parcel arrives.");
    }

    public function show(ProductReturn $productReturn)
    {
        $productReturn->load(['order.marketplace', 'items.product', 'media', 'refunds', 'claims']);
        return view('returns.show', ['r' => $productReturn]);
    }

    public function inspect(Request $request, ProductReturn $productReturn)
    {
        $d = $request->validate([
            'lines' => 'required|array', 'lines.*.received_qty' => 'required|integer|min:0|max:100000',
            'lines.*.condition' => ['nullable', Rule::in(array_keys(ReturnItem::CONDITIONS))], 'lines.*.note' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:2000',
        ]);
        $this->service->inspect($productReturn, $d['lines'], $request->boolean('claim_eligible'), $d['notes'] ?? null, $request->user());
        return back()->with('success', 'Inspection saved. Stock updated.');
    }

    public function close(ProductReturn $productReturn)
    {
        $this->service->close($productReturn);
        return back()->with('success', 'Return closed.');
    }

    public function upload(Request $request, ProductReturn $productReturn)
    {
        $request->validate(['files' => 'required|array|max:10', 'files.*' => 'file|mimes:jpg,jpeg,png,webp,mp4,mov|max:20480']);
        foreach ($request->file('files') as $f) {
            $productReturn->media()->create([
                'path' => $f->store("returns/{$productReturn->id}", 'local'), 'original_name' => $f->getClientOriginalName(),
                'mime' => $f->getMimeType(), 'size' => $f->getSize(), 'uploaded_by' => $request->user()->id,
            ]);
        }
        ActivityLogger::log('return.evidence_added', $productReturn, null, ['files' => count($request->file('files'))]);
        return back()->with('success', 'Evidence uploaded.');
    }

    public function media(ProductReturn $productReturn, ReturnMedia $media)
    {
        abort_unless($media->return_id === $productReturn->id && Storage::disk('local')->exists($media->path), 404);
        return Storage::disk('local')->response($media->path, $media->original_name);
    }

    public function deleteMedia(ProductReturn $productReturn, ReturnMedia $media)
    {
        abort_unless($media->return_id === $productReturn->id, 404);
        Storage::disk('local')->delete($media->path);
        $media->delete();
        ActivityLogger::log('return.evidence_removed', $productReturn, ['file' => $media->original_name]);
        return back()->with('success', 'File removed.');
    }
}
