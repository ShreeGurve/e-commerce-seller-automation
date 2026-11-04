<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SupplierSourcingController extends Controller
{
    public function index(Request $request)
    {
        $products = Product::query()->orderBy('name')->get(['id', 'name', 'sku']);
        $selectedProduct = $request->filled('product_id')
            ? Product::query()->find($request->integer('product_id'))
            : null;

        $recommendations = collect();
        if ($selectedProduct) {
            $rows = DB::table('purchase_items as pi')
                ->join('purchases as p', 'p.id', '=', 'pi.purchase_id')
                ->join('suppliers as s', 's.id', '=', 'p.supplier_id')
                ->where('pi.product_id', $selectedProduct->id)
                ->where('p.status', 'RECEIVED')
                ->whereNull('p.deleted_at')
                ->whereNull('s.deleted_at')
                ->select(
                    's.id as supplier_id', 's.name as supplier_name',
                    DB::raw('AVG(pi.unit_cost) as average_unit_cost'),
                    DB::raw('MIN(pi.unit_cost) as lowest_unit_cost'),
                    DB::raw('MAX(pi.unit_cost) as highest_unit_cost'),
                    DB::raw('SUM(pi.received_qty) as received_units'),
                    DB::raw('COUNT(DISTINCT p.id) as received_orders'),
                    DB::raw('MAX(p.received_at) as last_received_at'),
                    DB::raw('AVG(CASE WHEN p.expected_delivery_date IS NOT NULL AND p.received_at IS NOT NULL AND DATE(p.received_at) <= p.expected_delivery_date THEN 100 WHEN p.expected_delivery_date IS NOT NULL AND p.received_at IS NOT NULL THEN 0 ELSE NULL END) as on_time_rate')
                )
                ->groupBy('s.id', 's.name')
                ->get();

            $recommendations = $rows->map(function ($row) {
                $review = DB::table('supplier_reviews')->where('supplier_id', $row->supplier_id)
                    ->selectRaw('AVG((delivery_rating + quality_rating + pricing_rating) / 3) as rating')->value('rating');
                $ratingPct = $review === null ? 50 : ((float) $review / 5) * 100;
                $deliveryPct = $row->on_time_rate === null ? 50 : (float) $row->on_time_rate;
                $costs = $rowsCostPlaceholder = null;
                $costScore = 50;
                $row->rating_out_of_5 = $review === null ? null : round((float) $review, 1);
                $row->score = (int) round(($deliveryPct * .35) + ($ratingPct * .35) + ($costScore * .30));
                $row->risk_label = $row->score >= 80 ? 'Preferred' : ($row->score >= 60 ? 'Consider' : 'Review');
                return $row;
            });

            if ($recommendations->isNotEmpty()) {
                $min = (float) $recommendations->min('average_unit_cost');
                $max = (float) $recommendations->max('average_unit_cost');
                $recommendations = $recommendations->map(function ($row) use ($min, $max) {
                    $costScore = $max > $min
                        ? 100 - (((float) $row->average_unit_cost - $min) / ($max - $min) * 100)
                        : 100;
                    $ratingPct = $row->rating_out_of_5 === null ? 50 : ($row->rating_out_of_5 / 5) * 100;
                    $deliveryPct = $row->on_time_rate === null ? 50 : (float) $row->on_time_rate;
                    $row->score = (int) round(($deliveryPct * .35) + ($ratingPct * .35) + ($costScore * .30));
                    $row->cost_score = round($costScore, 1);
                    $row->risk_label = $row->score >= 80 ? 'Preferred' : ($row->score >= 60 ? 'Consider' : 'Review');
                    return $row;
                })->sortByDesc('score')->values();
            }
        }

        return view('sourcing.recommendations', compact('products', 'selectedProduct', 'recommendations'));
    }
}
