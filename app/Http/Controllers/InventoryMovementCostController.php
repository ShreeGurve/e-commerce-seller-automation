<?php

namespace App\Http\Controllers;

use App\Models\InventoryTransaction;
use Illuminate\Http\Request;

class InventoryMovementCostController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'type' => ['nullable', 'string', 'in:PURCHASE,STOCK_IN,ORDER,RETURN,DAMAGE,LOST,DISPOSAL,ORDER_CANCEL,RETURN_DAMAGED,ADJUSTMENT'],
        ]);

        $query = InventoryTransaction::with(['product', 'user'])
            ->whereNotNull('unit_cost')
            ->when($filters['from'] ?? null, fn ($q, $d) => $q->whereDate('created_at', '>=', $d))
            ->when($filters['to'] ?? null, fn ($q, $d) => $q->whereDate('created_at', '<=', $d))
            ->when($filters['type'] ?? null, fn ($q, $t) => $q->where('type', $t));

        $rows = $query->latest('id')->paginate(30)->withQueryString();
        $summaryQuery = clone $query;
        $summary = [
            'records' => (clone $summaryQuery)->count(),
            'units' => (int) (clone $summaryQuery)->sum('qty_change'),
            'costed_value' => (float) (clone $summaryQuery)->selectRaw('COALESCE(SUM(ABS(qty_change) * unit_cost), 0) as aggregate')->value('aggregate'),
        ];
        return view('inventory.movement-costs', compact('rows', 'summary'));
    }

    public function export(Request $request)
    {
        $filters = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'type' => ['nullable', 'string', 'in:PURCHASE,STOCK_IN,ORDER,RETURN,DAMAGE,LOST,DISPOSAL,ORDER_CANCEL,RETURN_DAMAGED,ADJUSTMENT'],
        ]);
        $query = InventoryTransaction::with('product')->whereNotNull('unit_cost')
            ->when($filters['from'] ?? null, fn ($q, $d) => $q->whereDate('created_at', '>=', $d))
            ->when($filters['to'] ?? null, fn ($q, $d) => $q->whereDate('created_at', '<=', $d))
            ->when($filters['type'] ?? null, fn ($q, $t) => $q->where('type', $t))->latest('id');

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Ledger ID','Date','Product','SKU','Movement Type','Quantity Change','Unit Cost','Absolute Movement Cost']);
            $query->chunk(500, function ($rows) use ($out) {
                foreach ($rows as $r) fputcsv($out, [
                    $r->id, optional($r->created_at)->toDateTimeString(), $r->product?->name, $r->product?->sku,
                    $r->type, $r->qty_change, $r->unit_cost, abs((int)$r->qty_change) * (float)$r->unit_cost,
                ]);
            });
            fclose($out);
        }, 'inventory-movement-costs-' . now()->format('Ymd-His') . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
