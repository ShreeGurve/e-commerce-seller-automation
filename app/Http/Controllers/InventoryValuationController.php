<?php

namespace App\Http\Controllers;

use App\Models\Inventory;
use Illuminate\Http\Request;

class InventoryValuationController extends Controller
{
    public function index()
    {
        $rows = Inventory::query()->with(['product.currentCosts'])->get()->map(function ($row) {
            $product = $row->product;
            $cost = $product ? (float) $product->totalUnitCost() : 0.0;
            $row->valuation_unit_cost = $cost;
            $row->valuation_value = max(0, (int)$row->available_qty) * $cost;
            $row->cost_missing = $cost <= 0;
            return $row;
        });

        $summary = [
            'products' => $rows->count(),
            'units' => (int) $rows->sum(fn ($r) => max(0, (int)$r->available_qty)),
            'value' => (float) $rows->sum('valuation_value'),
            'missing_cost' => $rows->where('cost_missing', true)->count(),
        ];

        return view('inventory.valuation', compact('rows', 'summary'));
    }

    public function export()
    {
        $rows = Inventory::query()->with(['product.currentCosts'])->get();

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['SKU','Product','Available Units','Unit Cost Source','Unit Cost','Estimated Available Value','Cost Status']);
            foreach ($rows as $r) {
                $product = $r->product;
                $cost = $product ? (float) $product->totalUnitCost() : 0.0;
                fputcsv($out, [
                    $product?->sku, $product?->name, max(0, (int)$r->available_qty),
                    'current product cost history (all active cost types)',
                    $cost, max(0, (int)$r->available_qty) * $cost, $cost > 0 ? 'Available' : 'Missing cost',
                ]);
            }
            fclose($out);
        }, 'inventory-valuation-estimate-' . now()->format('Ymd-His') . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
