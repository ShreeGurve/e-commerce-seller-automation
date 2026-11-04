<?php

namespace App\Http\Controllers;

use App\Models\Purchase;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SupplierPaymentPlanningController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'horizon_days' => 'nullable|integer|in:7,15,30,60,90',
            'supplier_id' => 'nullable|integer|exists:suppliers,id',
            'status' => 'nullable|in:all,overdue,due,upcoming,unknown',
        ]);

        $horizon = (int) ($filters['horizon_days'] ?? 30);
        $statusFilter = $filters['status'] ?? 'all';
        $today = today();
        $horizonDate = $today->copy()->addDays($horizon)->endOfDay();

        $purchases = Purchase::query()
            ->with('supplier')
            ->whereIn('status', ['ORDERED', 'RECEIVED'])
            ->whereNull('deleted_at')
            ->when(!empty($filters['supplier_id']), fn ($q) => $q->where('supplier_id', $filters['supplier_id']))
            ->whereRaw('(total_amount - amount_paid) > 0.009')
            ->orderBy('purchase_date')
            ->get()
            ->map(function (Purchase $purchase) use ($today, $horizonDate) {
                $balance = max(0, (float) $purchase->total_amount - (float) $purchase->amount_paid);
                $baseDate = $purchase->invoice_date ?: $purchase->purchase_date;
                $terms = (string) ($purchase->supplier?->payment_terms ?? '');
                $days = $this->paymentTermDays($terms);
                $dueDate = ($baseDate && $days !== null)
                    ? Carbon::parse($baseDate)->addDays($days)->startOfDay()
                    : null;

                if (!$dueDate) {
                    $bucket = 'unknown';
                    $priority = 2;
                } elseif ($dueDate->lt($today)) {
                    $bucket = 'overdue';
                    $priority = 0;
                } elseif ($dueDate->lte($horizonDate)) {
                    $bucket = $dueDate->isSameDay($today) ? 'due' : 'upcoming';
                    $priority = 1;
                } else {
                    $bucket = 'later';
                    $priority = 3;
                }

                return (object) [
                    'purchase' => $purchase,
                    'supplier' => $purchase->supplier,
                    'balance' => $balance,
                    'due_date' => $dueDate,
                    'terms' => $terms ?: 'Not configured',
                    'bucket' => $bucket,
                    'priority' => $priority,
                ];
            })
            ->sortBy(fn ($row) => sprintf('%d-%s-%010d', $row->priority, $row->due_date?->format('Ymd') ?? '99999999', $row->purchase->id))
            ->values();

        $visible = $purchases->filter(function ($row) use ($statusFilter, $horizonDate) {
            if ($statusFilter === 'all') return true;
            if ($statusFilter === 'due') return $row->bucket === 'due';
            if ($statusFilter === 'upcoming') return $row->bucket === 'upcoming';
            return $row->bucket === $statusFilter;
        })->values();

        $totalOutstanding = (float) $purchases->sum('balance');
        $overdueAmount = (float) $purchases->where('bucket', 'overdue')->sum('balance');
        $dueSoonAmount = (float) $purchases->filter(fn ($row) => in_array($row->bucket, ['due', 'upcoming'], true))->sum('balance');
        $unknownAmount = (float) $purchases->where('bucket', 'unknown')->sum('balance');
        $suppliers = \App\Models\Supplier::query()->orderBy('name')->get(['id', 'name']);
        $supplierExposure = $purchases->groupBy(fn ($row) => $row->supplier?->id ?? 0)->map(function ($rows) {
            return (object) [
                'name' => $rows->first()->supplier?->name ?? 'Unknown supplier',
                'balance' => (float) $rows->sum('balance'),
                'overdue' => (float) $rows->where('bucket', 'overdue')->sum('balance'),
                'orders' => $rows->count(),
            ];
        })->sortByDesc('balance')->values();

        return view('supplier-payments.index', compact(
            'horizon', 'statusFilter', 'suppliers', 'visible', 'totalOutstanding',
            'overdueAmount', 'dueSoonAmount', 'unknownAmount', 'supplierExposure'
        ));
    }

    public function export(Request $request): StreamedResponse
    {
        $rows = Purchase::query()->with('supplier')
            ->whereIn('status', ['ORDERED', 'RECEIVED'])
            ->whereRaw('(total_amount - amount_paid) > 0.009')
            ->orderBy('purchase_date')->get();

        $filename = 'supplier-payment-plan-' . now()->format('Ymd-His') . '.csv';
        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['PO Number', 'Supplier', 'Invoice Date', 'Purchase Date', 'Payment Terms', 'Estimated Due Date', 'Total', 'Paid', 'Outstanding', 'PO Status']);
            foreach ($rows as $purchase) {
                $terms = (string) ($purchase->supplier?->payment_terms ?? '');
                $baseDate = $purchase->invoice_date ?: $purchase->purchase_date;
                $days = $this->paymentTermDays($terms);
                $dueDate = ($baseDate && $days !== null) ? Carbon::parse($baseDate)->addDays($days)->format('Y-m-d') : '';
                fputcsv($out, [
                    $purchase->po_number,
                    $purchase->supplier?->name ?? '',
                    $purchase->invoice_date?->format('Y-m-d') ?? '',
                    $purchase->purchase_date?->format('Y-m-d') ?? '',
                    $terms,
                    $dueDate,
                    number_format((float) $purchase->total_amount, 2, '.', ''),
                    number_format((float) $purchase->amount_paid, 2, '.', ''),
                    number_format(max(0, (float) $purchase->total_amount - (float) $purchase->amount_paid), 2, '.', ''),
                    $purchase->status,
                ]);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function paymentTermDays(string $terms): ?int
    {
        if (preg_match('/\b(?:net\s*)?(\d{1,3})\s*(?:days?|d)\b/i', $terms, $matches)) {
            return min(365, (int) $matches[1]);
        }
        if (preg_match('/\b(?:immediate|due\s+on\s+receipt|cod|cash\s+on\s+delivery|advance)\b/i', $terms)) {
            return 0;
        }
        return null;
    }
}
