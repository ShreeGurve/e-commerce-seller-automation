<?php

namespace App\Http\Controllers;

use App\Models\PurchaseItem;
use App\Models\PurchaseQualityInspection;
use App\Models\SupplierReturn;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SupplierReturnController extends Controller
{
    public function index(Request $request)
    {
        $returns = SupplierReturn::with(['purchase.supplier', 'item.product', 'inspection', 'creator'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('resolution'), fn ($q) => $q->where('resolution', $request->string('resolution')))
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $request->input('q')) . '%';
                $q->where(fn ($w) => $w->where('return_number', 'like', $term)->orWhere('supplier_reference', 'like', $term)
                    ->orWhereHas('purchase', fn ($p) => $p->where('po_number', 'like', $term))
                    ->orWhereHas('purchase.supplier', fn ($s) => $s->where('name', 'like', $term)));
            })
            ->latest('return_date')->latest('id')->paginate(20)->withQueryString();

        $summary = [
            'open' => SupplierReturn::whereIn('status', ['DRAFT', 'APPROVED', 'DISPATCHED'])->count(),
            'dispatched' => SupplierReturn::where('status', 'DISPATCHED')->count(),
            'resolved' => SupplierReturn::whereIn('status', ['REPLACED', 'REFUNDED', 'CREDITED', 'CLOSED'])->count(),
            'units' => (int) SupplierReturn::sum('quantity'),
        ];

        return view('purchases.supplier-returns', compact('returns', 'summary'));
    }

    public function create()
    {
        $inspections = PurchaseQualityInspection::with(['purchase.supplier', 'item.product'])
            ->where('rejected_qty', '>', 0)->whereIn('status', ['OPEN', 'RESOLVED'])
            ->latest('inspection_date')->get()->filter(function ($inspection) {
                $allocated = SupplierReturn::where('quality_inspection_id', $inspection->id)
                    ->whereNotIn('status', ['CANCELLED'])->sum('quantity');
                return $allocated < (int) $inspection->rejected_qty;
            })->values();

        return view('purchases.supplier-return-create', compact('inspections'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'quality_inspection_id' => ['required', 'integer', 'exists:purchase_quality_inspections,id'],
            'return_date' => ['required', 'date'],
            'quantity' => ['required', 'integer', 'min:1', 'max:1000000'],
            'reason' => ['required', 'string', 'max:120'],
            'resolution' => ['required', Rule::in(['REPLACEMENT', 'REFUND', 'CREDIT'])],
            'supplier_reference' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:3000'],
        ]);

        DB::transaction(function () use ($data, $request) {
            $inspection = PurchaseQualityInspection::whereKey($data['quality_inspection_id'])->lockForUpdate()->firstOrFail();
            $allocated = SupplierReturn::where('quality_inspection_id', $inspection->id)
                ->whereNotIn('status', ['CANCELLED'])->sum('quantity');
            $remaining = (int) $inspection->rejected_qty - (int) $allocated;
            if ((int) $data['quantity'] > $remaining) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'quantity' => "Return quantity exceeds the remaining rejected quantity ({$remaining}).",
                ]);
            }

            $number = 'SR-' . now()->format('Ymd') . '-' . strtoupper(Str::random(6));
            SupplierReturn::create($data + [
                'return_number' => $number,
                'purchase_id' => $inspection->purchase_id,
                'purchase_item_id' => $inspection->purchase_item_id,
                'status' => 'DRAFT',
                'created_by' => $request->user()->id,
            ]);
        });

        return redirect()->route('supplier-returns.index')->with('success', 'Supplier return request created as draft.');
    }

    public function updateStatus(Request $request, SupplierReturn $supplierReturn)
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['APPROVED', 'DISPATCHED', 'REPLACED', 'REFUNDED', 'CREDITED', 'CLOSED', 'CANCELLED'])],
            'supplier_reference' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:3000'],
        ]);

        $allowed = [
            'DRAFT' => ['APPROVED', 'CANCELLED'],
            'APPROVED' => ['DISPATCHED', 'CANCELLED'],
            'DISPATCHED' => ['REPLACED', 'REFUNDED', 'CREDITED', 'CLOSED'],
            'REPLACED' => ['CLOSED'], 'REFUNDED' => ['CLOSED'], 'CREDITED' => ['CLOSED'],
            'CLOSED' => [], 'CANCELLED' => [],
        ];
        if (! in_array($data['status'], $allowed[$supplierReturn->status] ?? [], true)) {
            return back()->withErrors(['status' => "Cannot change return from {$supplierReturn->status} to {$data['status']}."]);
        }

        $updates = ['status' => $data['status'], 'supplier_reference' => $data['supplier_reference'] ?? $supplierReturn->supplier_reference,
            'notes' => $data['notes'] ?? $supplierReturn->notes];
        if ($data['status'] === 'DISPATCHED') $updates['dispatched_at'] = now();
        if (in_array($data['status'], ['REPLACED', 'REFUNDED', 'CREDITED', 'CLOSED', 'CANCELLED'], true)) $updates['resolved_at'] = now();
        $supplierReturn->update($updates);

        return back()->with('success', 'Supplier return status updated.');
    }
}
