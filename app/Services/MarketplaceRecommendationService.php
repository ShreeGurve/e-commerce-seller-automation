<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MarketplaceRecommendationService
{
    /** Build explainable, read-only recommendations from existing operational data. */
    public function dashboard(array $filters = []): array
    {
        $days = (int) ($filters['days'] ?? 30);
        $lowStockCover = (int) ($filters['low_stock_cover'] ?? 7);
        $returnThreshold = (float) ($filters['return_threshold'] ?? 5);
        if (! in_array($days, [7, 14, 30, 60, 90], true)) {
            throw ValidationException::withMessages(['days' => 'Choose 7, 14, 30, 60 or 90 days.']);
        }
        if ($lowStockCover < 1 || $lowStockCover > 60) {
            throw ValidationException::withMessages(['low_stock_cover' => 'Stock cover must be between 1 and 60 days.']);
        }
        if ($returnThreshold < 0 || $returnThreshold > 100) {
            throw ValidationException::withMessages(['return_threshold' => 'Return threshold must be between 0 and 100.']);
        }

        $to = now()->toDateString();
        $from = now()->subDays($days - 1)->toDateString();
        $previousFrom = now()->subDays(($days * 2) - 1)->toDateString();
        $previousTo = now()->subDays($days)->toDateString();

        $sales = DB::table('order_profits as op')
            ->join('orders as o', 'o.id', '=', 'op.order_id')
            ->whereNull('o.deleted_at')
            ->where('o.status', '!=', 'CANCELLED')
            ->where(function ($q) { $q->whereNull('o.source')->orWhere('o.source', '!=', 'TEST'); });

        $currentSales = (clone $sales)->whereBetween('op.order_date', [$from, $to]);
        $previousSales = (clone $sales)->whereBetween('op.order_date', [$previousFrom, $previousTo]);

        $currentByProduct = (clone $currentSales)->selectRaw('op.product_id, SUM(op.qty) as units, SUM(op.revenue) as revenue, SUM(COALESCE(op.actual_profit, op.estimated_profit)) as profit')
            ->groupBy('op.product_id')->get()->keyBy('product_id');
        $previousByProduct = (clone $previousSales)->selectRaw('op.product_id, SUM(op.qty) as units, SUM(op.revenue) as revenue')
            ->groupBy('op.product_id')->get()->keyBy('product_id');

        $returnRows = DB::table('return_items as ri')->join('returns as r', 'r.id', '=', 'ri.return_id')
            ->whereNull('r.deleted_at')->whereBetween('r.return_date', [$from, $to])
            ->selectRaw('ri.product_id, SUM(ri.quantity) as returned_units')->groupBy('ri.product_id')->get()->keyBy('product_id');

        $inventoryRows = DB::table('inventory as i')->join('products as p', 'p.id', '=', 'i.product_id')
            ->whereNull('p.deleted_at')->where('p.status', 'active')
            ->select('p.id as product_id', 'p.name', 'p.sku', 'i.available_qty', 'i.reserved_qty', 'i.minimum_stock', 'i.reorder_level')->get();

        $recommendations = [];
        foreach ($inventoryRows as $item) {
            $current = $currentByProduct->get($item->product_id);
            $previous = $previousByProduct->get($item->product_id);
            $units = (int) ($current->units ?? 0);
            $previousUnits = (int) ($previous->units ?? 0);
            $dailyVelocity = $units / max(1, $days);
            $available = max(0, (int) $item->available_qty - (int) $item->reserved_qty);
            $cover = $dailyVelocity > 0 ? round($available / $dailyVelocity, 1) : null;

            if ($item->minimum_stock > 0 && $available <= (int) $item->minimum_stock) {
                $recommendations[] = $this->item('RESTOCK', 'HIGH', $item, 'Available stock is at or below the minimum stock threshold.', 'Review replenishment quantity and supplier lead time.', ['available' => $available, 'minimum' => (int) $item->minimum_stock, 'days_cover' => $cover]);
            } elseif ($dailyVelocity > 0 && $cover !== null && $cover <= $lowStockCover) {
                $recommendations[] = $this->item('STOCK_RISK', 'HIGH', $item, 'At the recent sales pace, stock may run out within '.$cover.' days.', 'Review replenishment before the estimated stock-out date.', ['available' => $available, 'days_cover' => $cover, 'daily_units' => round($dailyVelocity, 2)]);
            }

            if ($previousUnits >= 3 && $units <= $previousUnits * 0.6) {
                $drop = round((1 - ($units / $previousUnits)) * 100, 1);
                $recommendations[] = $this->item('SALES_DECLINE', 'MEDIUM', $item, 'Sales volume declined by '.$drop.'% versus the previous '.$days.'-day period.', 'Review listing visibility, price competitiveness and advertising performance.', ['current_units' => $units, 'previous_units' => $previousUnits, 'decline_percent' => $drop]);
            }

            $returned = (int) ($returnRows->get($item->product_id)->returned_units ?? 0);
            if ($units > 0 && ($returned / $units) * 100 >= $returnThreshold && $returned > 0) {
                $rate = round(($returned / $units) * 100, 1);
                $recommendations[] = $this->item('HIGH_RETURN_RATE', 'HIGH', $item, 'Returned units are '.$rate.'% of units sold in the selected period.', 'Review return reasons, product quality, packaging and listing accuracy.', ['returned_units' => $returned, 'sold_units' => $units, 'return_rate' => $rate]);
            }

            if ($current && (float) $current->revenue > 0 && ((float) $current->profit / (float) $current->revenue) * 100 < 5) {
                $margin = round(((float) $current->profit / (float) $current->revenue) * 100, 2);
                $recommendations[] = $this->item('LOW_PROFIT_MARGIN', $margin < 0 ? 'HIGH' : 'MEDIUM', $item, 'Recorded profit margin is '.$margin.'% for the selected period.', 'Review marketplace fees, fulfilment costs, product cost and pricing recommendations.', ['revenue' => round((float) $current->revenue, 2), 'profit' => round((float) $current->profit, 2), 'margin' => $margin]);
            }
        }

        $priority = ['HIGH' => 0, 'MEDIUM' => 1, 'LOW' => 2];
        usort($recommendations, fn ($a, $b) => ($priority[$a['priority']] <=> $priority[$b['priority']]) ?: strcmp($a['type'], $b['type']));

        return [
            'period' => ['from' => $from, 'to' => $to, 'days' => $days],
            'settings' => ['low_stock_cover' => $lowStockCover, 'return_threshold' => $returnThreshold],
            'counts' => [
                'total' => count($recommendations),
                'high' => count(array_filter($recommendations, fn ($r) => $r['priority'] === 'HIGH')),
                'medium' => count(array_filter($recommendations, fn ($r) => $r['priority'] === 'MEDIUM')),
                'restock' => count(array_filter($recommendations, fn ($r) => in_array($r['type'], ['RESTOCK', 'STOCK_RISK'], true))),
            ],
            'recommendations' => $recommendations,
        ];
    }

    private function item(string $type, string $priority, object $product, string $reason, string $action, array $metrics): array
    {
        return ['type' => $type, 'priority' => $priority, 'product_id' => (int) $product->product_id,
            'product_name' => $product->name, 'sku' => $product->sku, 'reason' => $reason, 'suggested_action' => $action, 'metrics' => $metrics];
    }
}
