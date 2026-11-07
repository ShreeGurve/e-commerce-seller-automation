<?php

namespace App\Http\Controllers;

use App\Models\Inventory;
use App\Models\InventoryTransaction;
use App\Models\Product;
use App\Services\InventoryService;
use Illuminate\Http\Request;

class InventoryReconciliationController extends Controller
{
    public function index(Request $request, InventoryService $inventoryService)
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', 'in:mismatch,matched'],
        ]);

        // Reuse the project's canonical ledger-vs-bucket comparison.
        $mismatches = $inventoryService->mismatches();
        $mismatchIds = $mismatches->pluck('product_id')->map(fn ($id) => (int) $id)->all();

        $query = Inventory::query()
            ->with(['product' => fn ($q) => $q->withTrashed()])
            ->when($filters['q'] ?? null, function ($q, $search) {
                $q->whereHas('product', function ($product) use ($search) {
                    $product->withTrashed()->where(function ($where) use ($search) {
                        $where->where('name', 'like', '%' . $search . '%')
                            ->orWhere('sku', 'like', '%' . $search . '%');
                    });
                });
            })
            ->when(($filters['status'] ?? null) === 'mismatch', fn ($q) => $q->whereIn('product_id', $mismatchIds))
            ->when(($filters['status'] ?? null) === 'matched', fn ($q) => $q->whereNotIn('product_id', $mismatchIds))
            ->orderBy('product_id');

        $rows = $query->paginate(30)->withQueryString();

        $ledger = InventoryTransaction::query()
            ->with(['product' => fn ($q) => $q->withTrashed()])
            ->when($filters['q'] ?? null, function ($q, $search) {
                $q->whereHas('product', function ($product) use ($search) {
                    $product->withTrashed()->where(function ($where) use ($search) {
                        $where->where('name', 'like', '%' . $search . '%')
                            ->orWhere('sku', 'like', '%' . $search . '%');
                    });
                });
            })
            ->orderByDesc('id')
            ->limit(20)
            ->get();

        $summary = [
            'inventory_rows' => Inventory::count(),
            'mismatches' => count($mismatchIds),
            'matched' => max(0, Inventory::count() - count($mismatchIds)),
        ];

        return view('inventory.reconciliation', compact('rows', 'ledger', 'summary', 'filters', 'mismatchIds'));
    }
}
