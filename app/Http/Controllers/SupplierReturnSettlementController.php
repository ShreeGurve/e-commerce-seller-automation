<?php

namespace App\Http\Controllers;

use App\Models\SupplierReturn;
use App\Models\SupplierReturnSettlement;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SupplierReturnSettlementController extends Controller
{
    public function index(Request $request)
    {
        $eligibleReturns = SupplierReturn::with(['purchase.supplier', 'item.product'])
            ->whereIn('status', ['REPLACED', 'REFUNDED', 'CREDITED', 'CLOSED'])
            ->whereDoesntHave('settlements', fn ($q) => $q->where('status', 'CONFIRMED'))
            ->latest('return_date')->limit(200)->get();

        $settlements = SupplierReturnSettlement::with(['supplierReturn.purchase.supplier', 'supplierReturn.item.product', 'creator', 'confirmer'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->when($request->filled('type'), fn ($q) => $q->where('settlement_type', $request->input('type')))
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $request->input('q')) . '%';
                $q->where(function ($w) use ($term) {
                    $w->where('reference_number', 'like', $term)
                      ->orWhereHas('supplierReturn', fn ($r) => $r->where('return_number', 'like', $term)->orWhere('supplier_reference', 'like', $term))
                      ->orWhereHas('supplierReturn.purchase.supplier', fn ($s) => $s->where('name', 'like', $term));
                });
            })
            ->latest()->paginate(20)->withQueryString();

        $summary = [
            'pending' => SupplierReturnSettlement::where('status', 'PENDING')->count(),
            'confirmed' => SupplierReturnSettlement::where('status', 'CONFIRMED')->count(),
            'refunds' => (float) SupplierReturnSettlement::where('status', 'CONFIRMED')->where('settlement_type', 'REFUND')->sum('amount'),
            'credits' => (float) SupplierReturnSettlement::where('status', 'CONFIRMED')->where('settlement_type', 'CREDIT')->sum('amount'),
            'replacement_units' => (int) SupplierReturnSettlement::where('status', 'CONFIRMED')->where('settlement_type', 'REPLACEMENT')->sum('replacement_quantity'),
        ];

        return view('purchases.supplier-return-settlements', compact('settlements', 'summary'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'supplier_return_id' => ['required', 'integer', 'exists:supplier_returns,id'],
            'settlement_type' => ['required', Rule::in(['REFUND', 'CREDIT', 'REPLACEMENT'])],
            'amount' => ['nullable', 'numeric', 'min:0', 'max:999999999999.99'],
            'replacement_quantity' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'reference_number' => ['nullable', 'string', 'max:120'],
            'settlement_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:3000'],
        ]);

        $return = SupplierReturn::findOrFail($data['supplier_return_id']);
        if (!in_array($return->status, ['REPLACED', 'REFUNDED', 'CREDITED', 'CLOSED'], true)) {
            return back()->withInput()->withErrors(['supplier_return_id' => 'The supplier return must first be marked as resolved in Phase 26.']);
        }

        $type = $data['settlement_type'];
        $amount = (float) ($data['amount'] ?? 0);
        $quantity = (int) ($data['replacement_quantity'] ?? 0);
        if ($type === 'REPLACEMENT' && ($quantity < 1 || $quantity > (int) $return->quantity)) {
            return back()->withInput()->withErrors(['replacement_quantity' => 'Replacement quantity must be between 1 and the returned quantity.']);
        }
        if ($type !== 'REPLACEMENT' && $amount <= 0) {
            return back()->withInput()->withErrors(['amount' => 'Enter a settlement amount greater than zero.']);
        }

        SupplierReturnSettlement::create([
            'supplier_return_id' => $return->id,
            'settlement_type' => $type,
            'amount' => $type === 'REPLACEMENT' ? 0 : $amount,
            'replacement_quantity' => $type === 'REPLACEMENT' ? $quantity : 0,
            'reference_number' => $data['reference_number'] ?? null,
            'settlement_date' => $data['settlement_date'] ?? null,
            'status' => 'PENDING',
            'notes' => $data['notes'] ?? null,
            'created_by' => $request->user()->id,
        ]);

        return redirect()->route('supplier-return-settlements.index')->with('success', 'Settlement record created as pending confirmation.');
    }

    public function confirm(Request $request, SupplierReturnSettlement $settlement)
    {
        if ($settlement->status !== 'PENDING') {
            return back()->withErrors(['settlement' => 'Only pending settlements can be confirmed.']);
        }
        $settlement->update([
            'status' => 'CONFIRMED',
            'confirmed_by' => $request->user()->id,
            'confirmed_at' => now(),
        ]);

        return back()->with('success', 'Settlement confirmation recorded. No accounting or inventory entries were posted.');
    }

    public function cancel(SupplierReturnSettlement $settlement)
    {
        if ($settlement->status !== 'PENDING') {
            return back()->withErrors(['settlement' => 'Only pending settlements can be cancelled.']);
        }
        $settlement->update(['status' => 'CANCELLED']);
        return back()->with('success', 'Pending settlement cancelled.');
    }
}
