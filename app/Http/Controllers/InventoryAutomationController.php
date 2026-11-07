<?php

namespace App\Http\Controllers;

use App\Models\Inventory;
use Illuminate\Http\Request;

class InventoryAutomationController extends Controller
{
    public function index(Request $request)
    {
        $query = Inventory::with('product')->whereHas('product');
        $rows = $query->get()->filter(function ($row) {
            return (int) $row->available_qty <= 0
                || ((int) $row->minimum_stock > 0 && (int) $row->available_qty <= (int) $row->minimum_stock)
                || ((int) $row->reorder_level > 0 && (int) $row->available_qty <= (int) $row->reorder_level);
        })->sortBy(fn ($row) => (int) $row->available_qty)->values();

        $stats = [
            'out' => $rows->filter(fn ($r) => (int) $r->available_qty <= 0)->count(),
            'low' => $rows->filter(fn ($r) => (int) $r->available_qty > 0)->count(),
            'suggested_units' => $rows->sum(fn ($r) => max(0, (int) max($r->minimum_stock, $r->reorder_level) - (int) $r->available_qty)),
        ];

        return view('inventory.automation', compact('rows', 'stats'));
    }

    public function export()
    {
        $rows = Inventory::with('product')->get()->filter(fn ($r) =>
            (int) $r->available_qty <= 0 ||
            ((int) $r->minimum_stock > 0 && (int) $r->available_qty <= (int) $r->minimum_stock) ||
            ((int) $r->reorder_level > 0 && (int) $r->available_qty <= (int) $r->reorder_level)
        );

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['SKU', 'Product', 'Available', 'Minimum Stock', 'Reorder Level', 'Suggested Replenishment', 'Signal']);
            foreach ($rows as $r) {
                $target = max((int) $r->minimum_stock, (int) $r->reorder_level);
                fputcsv($out, [$r->product?->sku, $r->product?->name, $r->available_qty, $r->minimum_stock, $r->reorder_level, max(0, $target - (int) $r->available_qty), (int) $r->available_qty <= 0 ? 'OUT' : 'LOW']);
            }
            fclose($out);
        }, 'inventory-automation-signals-' . now()->format('Ymd-His') . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
