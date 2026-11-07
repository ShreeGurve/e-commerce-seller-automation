<?php

namespace App\Http\Controllers;

use App\Models\InventoryCorrectionRequest;
use App\Models\InventoryTransaction;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InventoryReconciliationReportController extends Controller
{
    public function index(Request $request)
    {
        $query = InventoryCorrectionRequest::with(['product', 'requester', 'reviewer']);
        if ($request->filled('status') && in_array($request->status, ['pending', 'applied', 'rejected'], true)) {
            $query->where('status', $request->status);
        }
        if ($request->filled('from')) $query->whereDate('created_at', '>=', $request->date('from'));
        if ($request->filled('to')) $query->whereDate('created_at', '<=', $request->date('to'));

        $summary = [
            'pending' => (clone $query)->where('status', 'pending')->count(),
            'applied' => (clone $query)->where('status', 'applied')->count(),
            'rejected' => (clone $query)->where('status', 'rejected')->count(),
            'net_variance' => (int) (clone $query)->where('status', 'applied')->sum('variance'),
        ];
        $requests = $query->latest()->paginate(25)->withQueryString();
        return view('inventory.reconciliation-report', compact('requests', 'summary'));
    }

    public function export(Request $request): StreamedResponse
    {
        $query = InventoryCorrectionRequest::with(['product', 'requester', 'reviewer'])->latest();
        if ($request->filled('status') && in_array($request->status, ['pending', 'applied', 'rejected'], true)) {
            $query->where('status', $request->status);
        }
        if ($request->filled('from')) $query->whereDate('created_at', '>=', $request->date('from'));
        if ($request->filled('to')) $query->whereDate('created_at', '<=', $request->date('to'));

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Request ID', 'SKU', 'Product', 'System Qty', 'Counted Qty', 'Variance', 'Status', 'Requested By', 'Reviewed By', 'Note', 'Review Note', 'Created At', 'Applied At']);
            $query->chunk(500, function ($rows) use ($out) {
                foreach ($rows as $row) {
                    fputcsv($out, [
                        $row->id, $row->product?->sku, $row->product?->name,
                        $row->system_qty, $row->counted_qty, $row->variance, $row->status,
                        $row->requester?->name, $row->reviewer?->name, $row->note, $row->review_note,
                        optional($row->created_at)->toDateTimeString(), optional($row->applied_at)->toDateTimeString(),
                    ]);
                }
            });
            fclose($out);
        }, 'inventory-reconciliation-' . now()->format('Ymd-His') . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
