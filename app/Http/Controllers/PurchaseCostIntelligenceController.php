<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PurchaseCostIntelligenceController extends Controller
{
    public function index(Request $request)
    {
        $products = Product::query()->orderBy('name')->get(['id', 'name', 'sku']);
        $suppliers = Supplier::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']);

        $query = DB::table('purchase_items as pi')
            ->join('purchases as p', 'p.id', '=', 'pi.purchase_id')
            ->join('products as pr', 'pr.id', '=', 'pi.product_id')
            ->join('suppliers as s', 's.id', '=', 'p.supplier_id')
            ->where('p.status', 'RECEIVED')
            ->whereNull('p.deleted_at')
            ->whereNull('s.deleted_at')
            ->select(
                'pi.product_id', 'pr.name as product_name', 'pr.sku',
                's.id as supplier_id', 's.name as supplier_name',
                DB::raw('COUNT(DISTINCT p.id) as purchase_count'),
                DB::raw('SUM(pi.received_qty) as received_units'),
                DB::raw('AVG(pi.unit_cost) as average_unit_cost'),
                DB::raw('MIN(pi.unit_cost) as lowest_unit_cost'),
                DB::raw('MAX(pi.unit_cost) as highest_unit_cost'),
                DB::raw('MAX(p.received_at) as last_received_at')
            )
            ->groupBy('pi.product_id', 'pr.name', 'pr.sku', 's.id', 's.name');

        if ($request->filled('product_id')) $query->where('pi.product_id', $request->integer('product_id'));
        if ($request->filled('supplier_id')) $query->where('s.id', $request->integer('supplier_id'));
        if ($request->filled('from')) $query->whereDate('p.received_at', '>=', $request->date('from'));
        if ($request->filled('to')) $query->whereDate('p.received_at', '<=', $request->date('to'));

        $rows = $query->get()->map(function ($row) {
            $row->latest_cost = DB::table('purchase_items as pi')
                ->join('purchases as p', 'p.id', '=', 'pi.purchase_id')
                ->where('pi.product_id', $row->product_id)
                ->where('p.supplier_id', $row->supplier_id)
                ->where('p.status', 'RECEIVED')
                ->whereNull('p.deleted_at')
                ->orderByDesc('p.received_at')
                ->orderByDesc('p.id')
                ->value('pi.unit_cost');
            $row->previous_cost = DB::table('purchase_items as pi')
                ->join('purchases as p', 'p.id', '=', 'pi.purchase_id')
                ->where('pi.product_id', $row->product_id)
                ->where('p.supplier_id', $row->supplier_id)
                ->where('p.status', 'RECEIVED')
                ->whereNull('p.deleted_at')
                ->orderByDesc('p.received_at')
                ->orderByDesc('p.id')
                ->skip(1)->value('pi.unit_cost');

            $row->change_percent = $row->previous_cost && (float) $row->previous_cost != 0
                ? round((((float) $row->latest_cost - (float) $row->previous_cost) / (float) $row->previous_cost) * 100, 2)
                : null;
            $row->negotiation_target = round((float) $row->average_unit_cost * 0.97, 2);
            $row->target_saving = max(0, round((float) $row->average_unit_cost - $row->negotiation_target, 2));
            return $row;
        })->sortByDesc(fn ($r) => abs((float) ($r->change_percent ?? 0)))->values();

        $summary = [
            'supplier_lines' => $rows->count(),
            'received_units' => (int) $rows->sum('received_units'),
            'average_cost' => $rows->isEmpty() ? 0 : (float) $rows->avg('average_unit_cost'),
            'increased' => $rows->filter(fn ($r) => $r->change_percent !== null && $r->change_percent > 0)->count(),
            'decreased' => $rows->filter(fn ($r) => $r->change_percent !== null && $r->change_percent < 0)->count(),
        ];

        return view('purchases.cost-intelligence', compact('products', 'suppliers', 'rows', 'summary'));
    }

    public function export(Request $request): StreamedResponse
    {
        $request->merge(['export' => true]);
        $response = $this->index($request);
        $rows = $response->getData()['rows'];

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Product', 'SKU', 'Supplier', 'Purchase Count', 'Received Units', 'Average Unit Cost', 'Latest Cost', 'Previous Cost', 'Change %', 'Negotiation Target']);
            foreach ($rows as $r) {
                fputcsv($out, [$r->product_name, $r->sku, $r->supplier_name, $r->purchase_count, $r->received_units, $r->average_unit_cost, $r->latest_cost, $r->previous_cost, $r->change_percent, $r->negotiation_target]);
            }
            fclose($out);
        }, 'purchase-cost-intelligence.csv', ['Content-Type' => 'text/csv']);
    }
}
