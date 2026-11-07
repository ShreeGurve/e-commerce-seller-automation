<?php

namespace App\Http\Controllers;

use App\Models\Inventory;
use App\Models\InventoryTransaction;
use Illuminate\Http\Request;

class InventoryHealthController extends Controller
{
    public function index(Request $request)
    {
        $rows = Inventory::query()->with('product')->get()->filter(function ($row) {
            return (int) $row->available_qty < 0
                || (int) $row->damaged_qty < 0
                || (int) $row->reserved_qty < 0
                || (int) $row->reserved_qty > (int) $row->available_qty
                || ((int) $row->minimum_stock > 0 && (int) $row->available_qty <= (int) $row->minimum_stock)
                || ((int) $row->reorder_level > 0 && (int) $row->available_qty <= (int) $row->reorder_level);
        })->values();

        $summary = [
            'checked' => Inventory::count(),
            'exceptions' => $rows->count(),
            'negative' => $rows->filter(fn ($r) => (int)$r->available_qty < 0 || (int)$r->damaged_qty < 0 || (int)$r->reserved_qty < 0)->count(),
            'reservation' => $rows->filter(fn ($r) => (int)$r->reserved_qty > (int)$r->available_qty)->count(),
            'low' => $rows->filter(fn ($r) => (int)$r->available_qty >= 0 && (
                ((int)$r->minimum_stock > 0 && (int)$r->available_qty <= (int)$r->minimum_stock) ||
                ((int)$r->reorder_level > 0 && (int)$r->available_qty <= (int)$r->reorder_level)
            ))->count(),
        ];

        return view('inventory.health', compact('rows', 'summary'));
    }

    public function export()
    {
        $rows = Inventory::with('product')->get()->filter(fn ($r) =>
            (int)$r->available_qty < 0 || (int)$r->damaged_qty < 0 || (int)$r->reserved_qty < 0 ||
            (int)$r->reserved_qty > (int)$r->available_qty ||
            ((int)$r->minimum_stock > 0 && (int)$r->available_qty <= (int)$r->minimum_stock) ||
            ((int)$r->reorder_level > 0 && (int)$r->available_qty <= (int)$r->reorder_level)
        );

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['SKU','Product','Available','Damaged','Reserved','Minimum','Reorder','Health Flags']);
            foreach ($rows as $r) {
                $flags = [];
                if ((int)$r->available_qty < 0) $flags[] = 'Negative available';
                if ((int)$r->damaged_qty < 0) $flags[] = 'Negative damaged';
                if ((int)$r->reserved_qty < 0) $flags[] = 'Negative reserved';
                if ((int)$r->reserved_qty > (int)$r->available_qty) $flags[] = 'Reserved exceeds available';
                if ((int)$r->minimum_stock > 0 && (int)$r->available_qty <= (int)$r->minimum_stock) $flags[] = 'Below minimum';
                if ((int)$r->reorder_level > 0 && (int)$r->available_qty <= (int)$r->reorder_level) $flags[] = 'At reorder level';
                fputcsv($out, [$r->product?->sku, $r->product?->name, $r->available_qty, $r->damaged_qty, $r->reserved_qty, $r->minimum_stock, $r->reorder_level, implode('; ', $flags)]);
            }
            fclose($out);
        }, 'inventory-health-' . now()->format('Ymd-His') . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
