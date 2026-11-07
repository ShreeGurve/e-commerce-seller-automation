<?php

namespace App\Http\Controllers;

use App\Models\Inventory;
use App\Models\InventoryTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InventoryOperationsController extends Controller
{
    public function ledger(Request $request)
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'type' => ['nullable', 'string', 'in:PURCHASE,STOCK_IN,ORDER,RETURN,DAMAGE,LOST,DISPOSAL,ORDER_CANCEL,RETURN_DAMAGED,ADJUSTMENT'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);
        $query = InventoryTransaction::query()
            ->with(['product' => fn ($q) => $q->withTrashed(), 'user'])
            ->when($filters['q'] ?? null, fn ($q, $term) => $q->whereHas('product', fn ($p) => $p->withTrashed()->where(fn ($w) => $w->where('name', 'like', "%{$term}%")->orWhere('sku', 'like', "%{$term}%"))))
            ->when($filters['type'] ?? null, fn ($q, $type) => $q->where('type', $type))
            ->when($filters['from'] ?? null, fn ($q, $date) => $q->whereDate('created_at', '>=', $date))
            ->when($filters['to'] ?? null, fn ($q, $date) => $q->whereDate('created_at', '<=', $date));

        $summary = [
            'rows' => (clone $query)->count(),
            'units_in' => (int) (clone $query)->where('qty_change', '>', 0)->sum('qty_change'),
            'units_out' => abs((int) (clone $query)->where('qty_change', '<', 0)->sum('qty_change')),
        ];
        $transactions = $query->latest('id')->paginate(30)->withQueryString();
        return view('inventory.operations-ledger', compact('transactions', 'summary'));
    }

    public function ledgerExport(Request $request)
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'type' => ['nullable', 'string', 'in:PURCHASE,STOCK_IN,ORDER,RETURN,DAMAGE,LOST,DISPOSAL,ORDER_CANCEL,RETURN_DAMAGED,ADJUSTMENT'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);
        $query = InventoryTransaction::with(['product' => fn ($q) => $q->withTrashed(), 'user'])
            ->when($filters['q'] ?? null, fn ($q, $term) => $q->whereHas('product', fn ($p) => $p->withTrashed()->where(fn ($w) => $w->where('name', 'like', "%{$term}%")->orWhere('sku', 'like', "%{$term}%"))))
            ->when($filters['type'] ?? null, fn ($q, $type) => $q->where('type', $type))
            ->when($filters['from'] ?? null, fn ($q, $date) => $q->whereDate('created_at', '>=', $date))
            ->when($filters['to'] ?? null, fn ($q, $date) => $q->whereDate('created_at', '<=', $date))
            ->latest('id');

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Ledger ID','Date','SKU','Product','Type','Available Change','Damaged Change','Balance After','Unit Cost','User','Note']);
            $query->chunk(500, function ($rows) use ($out) {
                foreach ($rows as $r) fputcsv($out, [
                    $r->id, optional($r->created_at)->toDateTimeString(), $r->product?->sku, $r->product?->name,
                    $r->type, $r->qty_change, $r->damaged_change, $r->balance_after, $r->unit_cost,
                    $r->user?->name, $r->note,
                ]);
            });
            fclose($out);
        }, 'inventory-ledger-' . now()->format('Ymd-His') . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function inactivity(Request $request)
    {
        $days = (int) $request->input('days', 30);
        if (!in_array($days, [30, 60, 90, 180], true)) $days = 30;

        $rows = Inventory::query()->with('product')
            ->where('available_qty', '>', 0)
            ->select('inventory.*')
            ->selectSub(InventoryTransaction::query()->selectRaw('MAX(created_at)')
                ->whereColumn('product_id', 'inventory.product_id'), 'last_movement_at')
            ->get()
            ->filter(fn ($r) => !$r->last_movement_at || \Illuminate\Support\Carbon::parse($r->last_movement_at)->lt(now()->subDays($days)))
            ->sortBy(fn ($r) => $r->last_movement_at ? \Illuminate\Support\Carbon::parse($r->last_movement_at)->timestamp : 0)
            ->values();

        $summary = [
            'products' => $rows->count(),
            'units' => (int) $rows->sum('available_qty'),
            'no_history' => $rows->filter(fn ($r) => !$r->last_movement_at)->count(),
        ];
        return view('inventory.inactivity', compact('rows', 'days', 'summary'));
    }

    public function reservations()
    {
        $rows = Inventory::with('product')->where(function ($q) {
            $q->whereColumn('reserved_qty', '>', 'available_qty')
              ->orWhere('reserved_qty', '<', 0)
              ->orWhere('available_qty', '<', 0);
        })->orderBy('reserved_qty', 'desc')->get();

        $summary = [
            'exceptions' => $rows->count(),
            'reserved' => (int) $rows->sum('reserved_qty'),
            'available' => (int) $rows->sum('available_qty'),
        ];
        return view('inventory.reservation-audit', compact('rows', 'summary'));
    }

    public function reservationExport()
    {
        $rows = Inventory::with('product')->where(function ($q) {
            $q->whereColumn('reserved_qty', '>', 'available_qty')
              ->orWhere('reserved_qty', '<', 0)
              ->orWhere('available_qty', '<', 0);
        })->orderBy('reserved_qty', 'desc')->get();

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['SKU','Product','Available','Reserved','Difference','Exception']);
            foreach ($rows as $r) fputcsv($out, [
                $r->product?->sku, $r->product?->name, $r->available_qty, $r->reserved_qty,
                (int)$r->available_qty - (int)$r->reserved_qty,
                (int)$r->reserved_qty > (int)$r->available_qty ? 'Reserved exceeds available' : 'Negative stock bucket',
            ]);
            fclose($out);
        }, 'inventory-reservation-exceptions-' . now()->format('Ymd-His') . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
