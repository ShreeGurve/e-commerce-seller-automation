<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InventoryExportController extends Controller
{
    /**
     * Export the same inventory scope and filters used by the inventory register.
     * This is a read-only CSV export; it never changes stock or ledger records.
     */
    public function __invoke(Request $request): StreamedResponse
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'stock' => ['nullable', 'in:low,out'],
        ]);

        $query = Product::query()
            ->with('inventory')
            ->when($filters['q'] ?? null, function ($query, $search) {
                $query->where(function ($where) use ($search) {
                    $where->where('name', 'like', '%' . $search . '%')
                        ->orWhere('sku', 'like', '%' . $search . '%');
                });
            })
            ->when(($filters['stock'] ?? null) === 'low', function ($query) {
                $query->whereHas('inventory', function ($inventory) {
                    $inventory->where('minimum_stock', '>', 0)
                        ->whereColumn('available_qty', '<=', 'minimum_stock');
                });
            })
            ->when(($filters['stock'] ?? null) === 'out', function ($query) {
                $query->where(function ($where) {
                    $where->doesntHave('inventory')
                        ->orWhereHas('inventory', fn ($inventory) => $inventory->where('available_qty', '<=', 0));
                });
            })
            ->orderBy('sku');

        $filename = 'inventory-register-' . now()->format('Ymd-His') . '.csv';

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            // UTF-8 BOM helps spreadsheet applications display product names correctly.
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, [
                'SKU', 'Product', 'Available Qty', 'Damaged Qty',
                'Minimum Stock', 'Reorder Level', 'Stock Status',
            ]);

            $query->chunk(500, function ($products) use ($out) {
                foreach ($products as $product) {
                    $inventory = $product->inventory;
                    $available = (int) ($inventory->available_qty ?? 0);
                    $minimum = (int) ($inventory->minimum_stock ?? 0);
                    $status = $available <= 0
                        ? 'Out of stock'
                        : (($minimum > 0 && $available <= $minimum) ? 'Low stock' : 'OK');

                    fputcsv($out, [
                        $this->spreadsheetSafe($product->sku),
                        $this->spreadsheetSafe($product->name),
                        $available,
                        (int) ($inventory->damaged_qty ?? 0),
                        $minimum,
                        (int) ($inventory->reorder_level ?? 0),
                        $status,
                    ]);
                }
            });

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
        ]);
    }

    /**
     * Prevent CSV formula injection when a text value begins with a formula marker.
     */
    private function spreadsheetSafe(?string $value): string
    {
        $value = (string) $value;
        return preg_match('/^[\s]*[=+\-@]/u', $value) ? "'" . $value : $value;
    }
}
