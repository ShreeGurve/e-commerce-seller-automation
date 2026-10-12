<?php
namespace App\Http\Controllers;

use App\Models\{Claim, ClaimDocument, Marketplace, Order, Product, ProductReturn};
use App\Services\{ActivityLogger, ClaimService};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB, Storage};
use Illuminate\Validation\Rule;

class ClaimController extends Controller
{
    public function __construct(private ClaimService $service) {}

    public function index(Request $request)
    {
        $u = Claim::urgentDays(); $w = Claim::warningDays();
        $alert = fn () => Claim::whereIn('status', Claim::ALERT_STATUSES)->whereNotNull('deadline');
        $claims = Claim::with(['marketplace', 'order:id,order_number,external_order_id', 'product:id,sku'])
            ->when($request->status, fn ($q, $v) => $q->where('status', $v))->when($request->claim_type, fn ($q, $v) => $q->where('claim_type', $v))
            ->when($request->marketplace_id, fn ($q, $v) => $q->where('marketplace_id', $v))
            ->when($request->q, fn ($q, $s) => $q->where(fn ($x) => $x->where('claim_number', 'like', "%$s%")->orWhere('external_claim_id', 'like', "%$s%")
                ->orWhereHas('order', fn ($o) => $o->where('external_order_id', 'like', "%$s%")->orWhere('order_number', 'like', "%$s%"))))
            ->when($request->deadline === 'urgent', fn ($q) => $q->whereIn('status', Claim::ALERT_STATUSES)->whereDate('deadline', '<=', now()->addDays($u)->toDateString()))
            ->orderByRaw('CASE WHEN deadline IS NULL THEN 1 ELSE 0 END')->orderBy('deadline')->latest('id')->paginate(25)->withQueryString();

        $decided = Claim::whereNotNull('decision_date');
        return view('claims.index', [
            'claims' => $claims, 'marketplaces' => Marketplace::active()->get(), 'candidates' => $this->service->candidates(),
            'counts' => Claim::select('status', DB::raw('count(*) c'))->groupBy('status')->pluck('c', 'status'),
            'open' => Claim::whereIn('status', ['DRAFT', 'SUBMITTED', 'UNDER_REVIEW', 'APPEAL'])->selectRaw('COUNT(*) c, COALESCE(SUM(claim_amount),0) amt')->first(),
            'urgent' => $alert()->whereDate('deadline', '<=', now()->addDays($u)->toDateString())->count(),
            'warning' => $alert()->whereDate('deadline', '>', now()->addDays($u)->toDateString())->whereDate('deadline', '<=', now()->addDays($w)->toDateString())->count(),
            'recovered' => (float) Claim::sum('approved_amount'), 'recoveredMonth' => (float) Claim::whereDate('decision_date', '>=', now()->startOfMonth()->toDateString())->sum('approved_amount'),
            'winRate' => (clone $decided)->count() ? round((clone $decided)->where('approved_amount', '>', 0)->count() / (clone $decided)->count() * 100) : null,
            'urgentDays' => $u, 'warningDays' => $w,
        ]);
    }

    public function create(Request $request)
    {
        $pre = ['claim_type' => 'DAMAGED_ITEM', 'claim_amount' => '', 'notes' => ''];
        $return = $request->query('return_id') ? ProductReturn::with('order')->find($request->query('return_id')) : null;
        $order = $return?->order ?? ($request->query('order_id') ? Order::find($request->query('order_id')) : null);
        if ($return) $pre = $this->service->suggestFromReturn($return) + $pre;
        elseif ($order) $pre += ['marketplace_id' => $order->marketplace_id, 'order_id' => $order->id];
        return view('claims.create', ['claim' => new Claim($pre), 'marketplaces' => Marketplace::active()->get(), 'order' => $order, 'return' => $return,
            'products' => Product::orderBy('sku')->get(['id', 'sku', 'name'])]);
    }

    public function store(Request $request)
    {
        $d = $request->validate([
            'marketplace_id' => 'required|exists:marketplaces,id', 'claim_type' => ['required', Rule::in(array_keys(Claim::TYPES))],
            'claim_amount' => 'required|numeric|gt:0|max:9999999999', 'order_id' => 'nullable|exists:orders,id', 'return_id' => 'nullable|exists:returns,id',
            'order_ref' => 'nullable|string|max:60', 'product_id' => 'nullable|exists:products,id', 'external_claim_id' => 'nullable|string|max:60',
            'deadline' => 'nullable|date', 'notes' => 'nullable|string|max:2000',
        ]);
        if (empty($d['order_id']) && ! empty($d['order_ref'])) {
            $o = Order::where('marketplace_id', $d['marketplace_id'])->where(fn ($q) => $q->where('external_order_id', $d['order_ref'])->orWhere('order_number', $d['order_ref']))->first();
            if (! $o) return back()->withInput()->withErrors(['order_ref' => 'No order with that id for the chosen channel.']);
            $d['order_id'] = $o->id;
        }
        $claim = $this->service->create($d, $request->user());
        return redirect()->route('claims.show', $claim)->with('success', "Claim {$claim->claim_number} saved as draft. Add evidence, file it on the marketplace portal, then mark it Submitted.");
    }

    public function show(Claim $claim)
    {
        $claim->load(['marketplace', 'order', 'productReturn.media', 'product', 'documents', 'events.user', 'creator']);
        return view('claims.show', compact('claim'));
    }

    public function edit(Claim $claim)
    {
        abort_if($claim->status === 'CLOSED', 403);
        return view('claims.edit', ['claim' => $claim, 'products' => Product::orderBy('sku')->get(['id', 'sku', 'name'])]);
    }

    public function update(Request $request, Claim $claim)
    {
        $d = $request->validate(['claim_type' => ['nullable', Rule::in(array_keys(Claim::TYPES))], 'claim_amount' => 'nullable|numeric|gt:0|max:9999999999', 'product_id' => 'nullable|exists:products,id',
            'external_claim_id' => 'nullable|string|max:60', 'deadline' => 'nullable|date', 'notes' => 'nullable|string|max:2000']);
        $this->service->update($claim, $d);
        return redirect()->route('claims.show', $claim)->with('success', 'Claim updated.');
    }

    public function status(Request $request, Claim $claim)
    {
        $d = $request->validate(['to' => ['required', Rule::in(Claim::STATUSES)], 'note' => 'nullable|string|max:500', 'submitted_at' => 'nullable|date|before_or_equal:today',
            'decision_date' => 'nullable|date|before_or_equal:today', 'approved_amount' => 'nullable|numeric|min:0|max:9999999999', 'external_claim_id' => 'nullable|string|max:60', 'deadline' => 'nullable|date']);
        $this->service->transition($claim, $d['to'], $d, $request->user());
        return back()->with('success', 'Claim moved to ' . Claim::LABELS[$d['to']] . '.');
    }

    public function upload(Request $request, Claim $claim)
    {
        $request->validate(['files' => 'required|array|max:10', 'files.*' => 'file|mimes:jpg,jpeg,png,webp,mp4,mov,pdf|max:20480']);
        $n = $this->service->addDocuments($claim, $request->file('files'), $request->user());
        return back()->with('success', "{$n} file(s) added.");
    }

    public function attachReturn(Request $request, Claim $claim)
    {
        abort_unless($claim->productReturn, 404);
        $n = $this->service->attachReturnEvidence($claim, $claim->productReturn, $request->user());
        return back()->with($n ? 'success' : 'error', $n ? "{$n} file(s) copied from the return." : 'The return has no evidence files.');
    }

    public function document(Claim $claim, ClaimDocument $document)
    {
        abort_unless($document->claim_id === $claim->id && Storage::disk('local')->exists($document->path), 404);
        return Storage::disk('local')->response($document->path, $document->original_name);
    }

    public function deleteDocument(Claim $claim, ClaimDocument $document)
    {
        abort_unless($document->claim_id === $claim->id, 404);
        Storage::disk('local')->delete($document->path);
        $document->delete();
        ActivityLogger::log('claim.evidence_removed', $claim, ['file' => $document->original_name]);
        return back()->with('success', 'File removed.');
    }
}
