<?php

namespace App\Http\Controllers;

use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\PurchaseQualityInspection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PurchaseQualityInspectionController extends Controller
{
    public function index(Request $request)
    {
        $inspections = PurchaseQualityInspection::with(['purchase.supplier', 'item.product', 'creator'])
            ->when($request->status, fn ($q, $v) => $q->where('status', $v))
            ->when($request->supplier_id, fn ($q, $v) => $q->whereHas('purchase', fn ($p) => $p->where('supplier_id', $v)))
            ->latest('inspection_date')->latest('id')->paginate(20)->withQueryString();

        $summary = [
            'records' => PurchaseQualityInspection::count(),
            'rejected' => (int) PurchaseQualityInspection::sum('rejected_qty'),
            'open' => PurchaseQualityInspection::where('status', 'OPEN')->count(),
            'resolved' => PurchaseQualityInspection::where('status', 'RESOLVED')->count(),
        ];

        return view('purchases.quality-inspections', [
            'inspections' => $inspections,
            'summary' => $summary,
            'suppliers' => \App\Models\Supplier::orderBy('name')->get(),
        ]);
    }

    public function create()
    {
        $items = PurchaseItem::with(['purchase.supplier', 'product'])
            ->whereHas('purchase', fn ($q) => $q->whereIn('status', ['ORDERED', 'RECEIVED']))
            ->where('received_qty', '>', 0)->orderByDesc('id')->get()
            ->filter(function ($item) {
                $inspected = PurchaseQualityInspection::where('purchase_item_id', $item->id)->sum('inspected_qty');
                return $inspected < (int) $item->received_qty;
            });

        return view('purchases.quality-inspection-create', compact('items'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'purchase_item_id' => 'required|integer|exists:purchase_items,id',
            'inspection_date' => 'required|date',
            'inspected_qty' => 'required|integer|min:1|max:1000000',
            'accepted_qty' => 'required|integer|min:0|max:1000000',
            'rejected_qty' => 'required|integer|min:0|max:1000000',
            'defect_type' => 'nullable|string|max:100',
            'supplier_action' => ['required', Rule::in(['PENDING', 'REPLACEMENT', 'REFUND', 'CREDIT', 'NO_ACTION'])],
            'notes' => 'nullable|string|max:3000',
        ]);

        if ((int) $data['accepted_qty'] + (int) $data['rejected_qty'] !== (int) $data['inspected_qty']) {
            return back()->withInput()->withErrors(['accepted_qty' => 'Accepted quantity plus rejected quantity must equal inspected quantity.']);
        }

        DB::transaction(function () use ($data, $request) {
            $item = PurchaseItem::whereKey($data['purchase_item_id'])->lockForUpdate()->firstOrFail();
            $alreadyInspected = PurchaseQualityInspection::where('purchase_item_id', $item->id)->sum('inspected_qty');
            if ($alreadyInspected + (int) $data['inspected_qty'] > (int) $item->received_qty) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'inspected_qty' => 'Inspection quantity cannot exceed the remaining received quantity.',
                ]);
            }

            $status = (int) $data['rejected_qty'] > 0 ? 'OPEN' : 'PASSED';
            PurchaseQualityInspection::create([
                ...$data,
                'purchase_id' => $item->purchase_id,
                'status' => $status,
                'created_by' => $request->user()->id,
            ]);
        });

        return redirect()->route('purchase-quality.index')->with('success', 'Quality inspection recorded.');
    }

    public function update(Request $request, PurchaseQualityInspection $inspection)
    {
        $data = $request->validate([
            'supplier_action' => ['required', Rule::in(['PENDING', 'REPLACEMENT', 'REFUND', 'CREDIT', 'NO_ACTION'])],
            'status' => ['required', Rule::in(['OPEN', 'RESOLVED'])],
            'notes' => 'nullable|string|max:3000',
        ]);
        if ((int) $inspection->rejected_qty === 0) {
            $data['status'] = 'RESOLVED';
            $data['supplier_action'] = 'NO_ACTION';
        }
        $inspection->update($data);
        return back()->with('success', 'Supplier quality follow-up updated.');
    }
}
