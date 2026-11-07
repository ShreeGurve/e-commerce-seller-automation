<?php

namespace App\Http\Controllers;

use App\Models\Inventory;
use App\Models\InventoryTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class InventoryMovementAnalyticsController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'type' => ['nullable', Rule::in(array_keys(InventoryTransaction::TYPES))],
            'q' => ['nullable', 'string', 'max:120'],
        ]);

        $base = InventoryTransaction::query()
            ->join('products', 'products.id', '=', 'inventory_transactions.product_id')
            ->when($filters['from'] ?? null, fn ($q, $date) => $q->whereDate('inventory_transactions.created_at', '>=', $date))
            ->when($filters['to'] ?? null, fn ($q, $date) => $q->whereDate('inventory_transactions.created_at', '<=', $date))
            ->when($filters['type'] ?? null, fn ($q, $type) => $q->where('inventory_transactions.type', $type))
            ->when($filters['q'] ?? null, function ($q, $search) {
                $q->where(function ($where) use ($search) {
                    $where->where('products.name', 'like', '%' . $search . '%')
                        ->orWhere('products.sku', 'like', '%' . $search . '%');
                });
            });

        $summary = (clone $base)->selectRaw('
            COUNT(*) as movement_count,
            COALESCE(SUM(CASE WHEN qty_change > 0 THEN qty_change ELSE 0 END), 0) as units_in,
            COALESCE(SUM(CASE WHEN qty_change < 0 THEN ABS(qty_change) ELSE 0 END), 0) as units_out,
            COALESCE(SUM(damaged_change), 0) as damaged_net
        ')->first();

        $byType = (clone $base)
            ->select('inventory_transactions.type')
            ->selectRaw('COUNT(*) as movement_count, SUM(qty_change) as net_units, SUM(damaged_change) as damaged_net')
            ->groupBy('inventory_transactions.type')
            ->orderByDesc('movement_count')
            ->get();

        $daily = (clone $base)
            ->selectRaw('DATE(inventory_transactions.created_at) as movement_date')
            ->selectRaw('SUM(qty_change) as net_units, COUNT(*) as movement_count')
            ->groupByRaw('DATE(inventory_transactions.created_at)')
            ->orderBy('movement_date')
            ->limit(90)
            ->get();

        $products = (clone $base)
            ->select('products.id', 'products.sku', 'products.name')
            ->selectRaw('COUNT(*) as movement_count, SUM(inventory_transactions.qty_change) as net_units, SUM(inventory_transactions.damaged_change) as damaged_net')
            ->groupBy('products.id', 'products.sku', 'products.name')
            ->orderByDesc('movement_count')
            ->limit(15)
            ->get();

        $integrity = Inventory::query()
            ->select('inventory.product_id', 'inventory.available_qty', 'inventory.damaged_qty')
            ->selectSub(
                InventoryTransaction::query()->selectRaw('COALESCE(SUM(qty_change), 0)')
                    ->whereColumn('product_id', 'inventory.product_id'),
                'ledger_available'
            )
            ->selectSub(
                InventoryTransaction::query()->selectRaw('COALESCE(SUM(damaged_change), 0)')
                    ->whereColumn('product_id', 'inventory.product_id'),
                'ledger_damaged'
            )
            ->get()
            ->filter(fn ($row) => (int) $row->ledger_available !== (int) $row->available_qty
                || (int) $row->ledger_damaged !== (int) $row->damaged_qty)
            ->count();

        $recent = (clone $base)
            ->select('inventory_transactions.*', 'products.sku', 'products.name')
            ->orderByDesc('inventory_transactions.id')
            ->paginate(30)
            ->withQueryString();

        return view('inventory.movement-analytics', [
            'filters' => $filters,
            'summary' => $summary,
            'byType' => $byType,
            'daily' => $daily,
            'products' => $products,
            'integrityCount' => $integrity,
            'recent' => $recent,
            'types' => InventoryTransaction::TYPES,
        ]);
    }
}
