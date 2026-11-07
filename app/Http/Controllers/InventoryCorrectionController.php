<?php

namespace App\Http\Controllers;

use App\Models\InventoryCorrectionRequest;
use App\Models\Product;
use App\Services\InventoryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryCorrectionController extends Controller
{
    public function index()
    {
        $requests = InventoryCorrectionRequest::with(['product', 'requester', 'reviewer'])
            ->latest()->paginate(25);
        return view('inventory.corrections', compact('requests'));
    }

    public function store(Request $request, Product $product)
    {
        $data = $request->validate([
            'counted_qty' => ['required', 'integer', 'min:0'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $inventory = app(InventoryService::class)->ensureRow($product);
        $variance = (int) $data['counted_qty'] - (int) $inventory->available_qty;
        if ($variance === 0) {
            throw ValidationException::withMessages(['counted_qty' => 'Counted quantity matches current stock; no correction is required.']);
        }

        $pending = InventoryCorrectionRequest::where('product_id', $product->id)->where('status', 'pending')->exists();
        if ($pending) {
            throw ValidationException::withMessages(['counted_qty' => 'A correction request is already pending for this product.']);
        }

        InventoryCorrectionRequest::create([
            'product_id' => $product->id,
            'requested_by' => $request->user()->id,
            'status' => 'pending',
            'system_qty' => (int) $inventory->available_qty,
            'counted_qty' => (int) $data['counted_qty'],
            'variance' => $variance,
            'note' => $data['note'] ?? null,
        ]);

        return redirect()->route('inventory.corrections')->with('success', 'Correction submitted for approval. Stock has not been changed.');
    }

    public function approve(Request $request, InventoryCorrectionRequest $correction, InventoryService $inventoryService)
    {
        $data = $request->validate(['review_note' => ['nullable', 'string', 'max:500']]);

        DB::transaction(function () use ($request, $correction, $inventoryService, $data) {
            $locked = InventoryCorrectionRequest::whereKey($correction->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== 'pending') {
                throw ValidationException::withMessages(['correction' => 'This request has already been reviewed.']);
            }

            $product = Product::withTrashed()->findOrFail($locked->product_id);
            $inventory = $inventoryService->ensureRow($product);
            if ((int) $inventory->available_qty !== (int) $locked->system_qty) {
                throw ValidationException::withMessages(['correction' => 'Stock changed after this request was submitted. Reject this request and submit a fresh count.']);
            }

            $transaction = $inventoryService->record($product, 'ADJUSTMENT', (int) $locked->variance, [
                'note' => 'Approved stock correction request #' . $locked->id . ($locked->note ? ': ' . $locked->note : ''),
                'reference' => $locked,
            ]);

            $locked->forceFill([
                'status' => 'applied',
                'reviewed_by' => $request->user()->id,
                'review_note' => $data['review_note'] ?? null,
                'approved_at' => now(),
                'applied_at' => now(),
                'inventory_transaction_id' => $transaction->id,
            ])->save();
        });

        return back()->with('success', 'Correction approved and applied to the inventory ledger.');
    }

    public function reject(Request $request, InventoryCorrectionRequest $correction)
    {
        $data = $request->validate(['review_note' => ['required', 'string', 'max:500']]);

        DB::transaction(function () use ($request, $correction, $data) {
            $locked = InventoryCorrectionRequest::whereKey($correction->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== 'pending') {
                throw ValidationException::withMessages(['correction' => 'This request has already been reviewed.']);
            }
            $locked->forceFill([
                'status' => 'rejected',
                'reviewed_by' => $request->user()->id,
                'review_note' => $data['review_note'],
            ])->save();
        });

        return back()->with('success', 'Correction request rejected. Stock was not changed.');
    }
}
