<?php
namespace App\Services;

use App\Models\{Claim, Inventory, Product, ProductReturn, Purchase, Expense, ReconciliationIssue, User};
use Illuminate\Support\Facades\DB;

/**
 * Every report returns ['title','columns'=>[[key,label,type]],'rows'=>[assoc],'totals'=>?assoc,'note'=>?string] so one screen, one CSV and one Excel writer serve them all.
 * Types: text | int | money | ratio | pct | date.  Numbers stay numbers (never pre-formatted) so exports are clean.
 */
class ReportService
{
    public function __construct(private DashboardService $dash, private AdsService $ads) {}

    public const GROUPS = ['Sales & profit' => ['sales', 'profit', 'product_profitability', 'marketplace_comparison', 'cogs'], 'Marketing' => ['advertising'],
        'Operations' => ['returns', 'claims', 'inventory', 'purchases'], 'Finance & tax' => ['expenses', 'settlements', 'gst']];
    public const SORTS = ['profit_desc' => 'Highest profit', 'profit_asc' => 'Lowest profit (losses first)', 'revenue_desc' => 'Highest sales', 'returns_desc' => 'Highest returns', 'acos_desc' => 'Highest ad cost share'];

    public function catalog(): array
    {
        $c = fn ($t, $d, $filters, $cols) => ['title' => $t, 'desc' => $d, 'filters' => $filters, 'columns' => $cols];
        return [
            'sales' => $c('Sales report', 'Orders, units and sales per day and channel.', ['date', 'marketplace'], [['date', 'Date', 'date'], ['channel', 'Channel', 'text'], ['orders', 'Orders', 'int'], ['units', 'Units', 'int'], ['sales', 'Sales (incl. GST)', 'money'], ['cancelled', 'Cancelled orders', 'int']]),
            'profit' => $c('Profit report', 'Revenue, costs, fees, refunds, ads and profit per day and channel.', ['date', 'marketplace'], [['date', 'Date', 'date'], ['channel', 'Channel', 'text'], ['revenue', 'Revenue (ex-GST)', 'money'], ['cogs', 'Product & packaging cost', 'money'], ['fees', 'Marketplace fees', 'money'], ['refunds', 'Refunds', 'money'], ['recovery', 'Recovered', 'money'], ['ads', 'Ads', 'money'], ['profit', 'Profit', 'money'], ['margin', 'Margin', 'pct']]),
            'product_profitability' => $c('Product profitability', 'Which products really make money, with flags for losses, high returns and high ad cost.', ['date', 'marketplace', 'product', 'category', 'sort'], [['sku', 'SKU', 'text'], ['name', 'Product', 'text'], ['units', 'Units', 'int'], ['revenue', 'Revenue (ex-GST)', 'money'], ['cogs', 'Product & packaging cost', 'money'], ['ads', 'Ads', 'money'], ['fees', 'Fees', 'money'], ['refunds', 'Returns / refunds', 'money'], ['recovery', 'Recovered', 'money'], ['profit', 'Profit', 'money'], ['margin', 'Margin', 'pct'], ['flags', 'Flags', 'text']]),
            'marketplace_comparison' => $c('Marketplace comparison', 'Amazon vs Flipkart (and any other channel) side by side.', ['date'], [['channel', 'Channel', 'text'], ['orders', 'Orders', 'int'], ['sales', 'Sales (incl. GST)', 'money'], ['profit', 'Profit', 'money'], ['margin', 'Margin', 'pct'], ['ad_spend', 'Ad spend', 'money'], ['acos', 'ACOS', 'pct'], ['returns', 'Returns', 'int'], ['refunded', 'Refunded', 'money']]),
            'cogs' => $c('COGS report', 'Cost of goods sold per product, and how today\'s cost compares with what you paid on average.', ['date', 'marketplace', 'product', 'category'], [['sku', 'SKU', 'text'], ['name', 'Product', 'text'], ['units', 'Units sold', 'int'], ['cogs', 'COGS (after units restocked)', 'money'], ['avg_unit', 'Average cost / unit sold', 'money'], ['current_unit', 'Current cost / unit', 'money'], ['change', 'Change', 'pct']]),
            'advertising' => $c('Advertising report', 'Spend, sales, ACOS, ROAS and clicks per campaign.', ['date', 'marketplace'], [['campaign', 'Campaign', 'text'], ['spend', 'Spend', 'money'], ['sales', 'Sales from ads', 'money'], ['acos', 'ACOS', 'pct'], ['roas', 'ROAS', 'ratio'], ['orders', 'Orders', 'int'], ['clicks', 'Clicks', 'int'], ['impressions', 'Impressions', 'int'], ['ctr', 'CTR', 'pct'], ['cpc', 'CPC', 'money']]),
            'returns' => $c('Return report', 'Every return with reason, status, units and refund.', ['date', 'marketplace', 'product'], [['return_number', 'Return', 'text'], ['date', 'Date', 'date'], ['order', 'Order', 'text'], ['channel', 'Channel', 'text'], ['reason', 'Reason', 'text'], ['status', 'Status', 'text'], ['claim', 'Claim eligible', 'text'], ['units', 'Units', 'int'], ['received', 'Received', 'int'], ['refunded', 'Refunded', 'money']]),
            'claims' => $c('Claim report', 'SAFE-T, reimbursement and dispute claims with amounts and deadlines.', ['date', 'marketplace', 'product'], [['claim_number', 'Claim', 'text'], ['created', 'Created', 'date'], ['channel', 'Channel', 'text'], ['type', 'Type', 'text'], ['order', 'Order', 'text'], ['sku', 'SKU', 'text'], ['status', 'Status', 'text'], ['claimed', 'Claimed', 'money'], ['approved', 'Approved', 'money'], ['rejected', 'Rejected', 'money'], ['deadline', 'Deadline', 'date'], ['decision', 'Decision date', 'date']]),
            'inventory' => $c('Inventory report', 'Stock now, thresholds and stock value at cost (no date: it is today\'s stock).', ['product', 'category'], [['sku', 'SKU', 'text'], ['name', 'Product', 'text'], ['category', 'Category', 'text'], ['available', 'Available', 'int'], ['damaged', 'Damaged', 'int'], ['reserved', 'Reserved', 'int'], ['minimum', 'Minimum', 'int'], ['reorder', 'Reorder at', 'int'], ['status', 'Status', 'text'], ['unit_cost', 'Cost / unit', 'money'], ['stock_value', 'Stock value', 'money']]),
            'purchases' => $c('Purchase report', 'Purchase orders with supplier, GST, payment and balance due.', ['date', 'product'], [['po', 'PO', 'text'], ['date', 'Date', 'date'], ['supplier', 'Supplier', 'text'], ['invoice', 'Invoice', 'text'], ['status', 'Status', 'text'], ['payment', 'Payment', 'text'], ['subtotal', 'Items', 'money'], ['gst', 'GST', 'money'], ['extras', 'Shipping & other', 'money'], ['total', 'Total', 'money'], ['paid', 'Paid', 'money'], ['due', 'Due', 'money']]),
            'expenses' => $c('Expense report', 'Business expenses by date, category and channel.', ['date', 'marketplace', 'product'], [['date', 'Date', 'date'], ['category', 'Category', 'text'], ['channel', 'Channel', 'text'], ['product', 'Product', 'text'], ['invoice', 'Invoice', 'text'], ['notes', 'Notes', 'text'], ['amount', 'Amount', 'money']]),
            'settlements' => $c('Settlement report', 'What each marketplace payout contained: sales, fees, refunds, reimbursements and the net.', ['date', 'marketplace'], [['settlement', 'Settlement', 'text'], ['channel', 'Channel', 'text'], ['from', 'Period start', 'date'], ['to', 'Period end', 'date'], ['lines', 'Lines', 'int'], ['sales', 'Sales', 'money'], ['fees', 'Fees (incl. GST on fees)', 'money'], ['refunds', 'Refunds', 'money'], ['reimbursed', 'Reimbursements', 'money'], ['net', 'Net payout', 'money']]),
            'gst' => $c('GST support report', 'GST collected on sales and paid on purchases, by month and rate. A working paper to help your accountant: it is not a GST return.', ['date', 'marketplace'], [['month', 'Month', 'text'], ['rate', 'GST rate', 'text'], ['taxable_sales', 'Taxable sales', 'money'], ['output_gst', 'GST on sales', 'money'], ['taxable_purchases', 'Taxable purchases', 'money'], ['input_gst', 'GST on purchases', 'money'], ['net_gst', 'GST on sales − on purchases', 'money']]),
        ];
    }

    public function run(string $key, array $f): array
    {
        $cat = $this->catalog();
        abort_unless(isset($cat[$key]), 404);
        $r = $this->{'r_' . $key}($f) + ['totals' => null, 'note' => null];
        return ['key' => $key, 'title' => $cat[$key]['title'], 'desc' => $cat[$key]['desc'], 'filters' => $cat[$key]['filters'], 'columns' => $cat[$key]['columns']] + $r;
    }

    private function sum(array $rows, array $keys): array { $t = []; foreach ($keys as $k) $t[$k] = round(array_sum(array_column($rows, $k)), 2); return $t; }
    private function d($v): ?string { return $v ? substr((string) $v, 0, 10) : null; }
    private function mp(array $f): ?int { return $f['marketplace_id'] ?? null; }

    private function r_sales(array $f): array
    {
        $rows = DB::table('daily_sales as s')->join('marketplaces as m', 'm.id', '=', 's.marketplace_id')->whereBetween('s.date', [$f['from'], $f['to']])->when($this->mp($f), fn ($q, $v) => $q->where('s.marketplace_id', $v))
            ->orderBy('s.date')->orderBy('m.name')->get(['s.date', 'm.name as channel', 's.orders', 's.units', 's.gross_sales', 's.cancelled_orders'])
            ->map(fn ($r) => ['date' => $this->d($r->date), 'channel' => $r->channel, 'orders' => (int) $r->orders, 'units' => (int) $r->units, 'sales' => (float) $r->gross_sales, 'cancelled' => (int) $r->cancelled_orders])->all();
        return ['rows' => $rows, 'totals' => $this->sum($rows, ['orders', 'units', 'sales', 'cancelled'])];
    }

    private function r_profit(array $f): array
    {
        $rows = DB::table('daily_profit as s')->join('marketplaces as m', 'm.id', '=', 's.marketplace_id')->whereBetween('s.date', [$f['from'], $f['to']])->when($this->mp($f), fn ($q, $v) => $q->where('s.marketplace_id', $v))
            ->orderBy('s.date')->orderBy('m.name')->get(['s.date', 'm.name as channel', 's.revenue', 's.cogs', 's.fees', 's.refunds', 's.recovery', 's.ads', 's.profit'])
            ->map(fn ($r) => ['date' => $this->d($r->date), 'channel' => $r->channel, 'revenue' => (float) $r->revenue, 'cogs' => (float) $r->cogs, 'fees' => (float) $r->fees, 'refunds' => (float) $r->refunds,
                'recovery' => (float) $r->recovery, 'ads' => (float) $r->ads, 'profit' => (float) $r->profit, 'margin' => $r->revenue > 0 ? $r->profit / $r->revenue * 100 : null])->all();
        $t = $this->sum($rows, ['revenue', 'cogs', 'fees', 'refunds', 'recovery', 'ads', 'profit']); $t['margin'] = $t['revenue'] > 0 ? round($t['profit'] / $t['revenue'] * 100, 1) : null;
        return ['rows' => $rows, 'totals' => $t, 'note' => 'Profit is real (settlement-based) where the marketplace has paid, otherwise estimated from your fee rules.'];
    }

    private function r_product_profitability(array $f): array
    {
        $order = ['profit_desc' => 'profit DESC', 'profit_asc' => 'profit ASC', 'revenue_desc' => 'revenue DESC', 'returns_desc' => 'refunds DESC',
            'acos_desc' => 'CASE WHEN SUM(p.revenue) = 0 THEN 0 ELSE SUM(p.ads_cost) / SUM(p.revenue) END DESC'][$f['sort'] ?? 'profit_desc'] ?? 'profit DESC';
        $data = DB::table('order_profits as p')->join('products as pr', 'pr.id', '=', 'p.product_id')->whereBetween('p.order_date', [$f['from'], $f['to']])
            ->when($this->mp($f), fn ($q, $v) => $q->where('p.marketplace_id', $v))->when($f['product_id'] ?? null, fn ($q, $v) => $q->where('p.product_id', $v))
            ->when($f['category_id'] ?? null, fn ($q, $v) => $q->where('pr.category_id', $v))
            ->selectRaw('pr.sku, pr.name, SUM(p.qty) units, SUM(p.revenue) revenue, SUM(p.cogs) cogs, SUM(p.ads_cost) ads, SUM(p.fees) fees, SUM(p.return_loss) refunds, SUM(p.recovery) recovery, SUM(COALESCE(p.actual_profit, p.estimated_profit)) profit')
            ->groupBy('p.product_id', 'pr.sku', 'pr.name')->orderByRaw($order)->get();
        $retLimit = (float) SettingsService::get('default_return_threshold', 5); $adLimit = (float) SettingsService::get('default_target_acos', 25);
        $top = $data->filter(fn ($r) => $r->profit > 0)->sortByDesc('profit')->take(10)->pluck('sku')->flip();
        $rows = $data->map(function ($r) use ($retLimit, $adLimit, $top) {
            $rev = (float) $r->revenue; $flags = [];
            if (isset($top[$r->sku])) $flags[] = 'TOP PROFIT'; if ($r->profit < 0) $flags[] = 'LOSS';
            if ($rev > 0 && $r->refunds / $rev * 100 >= $retLimit) $flags[] = 'HIGH RETURNS'; if ($rev > 0 && $r->ads / $rev * 100 >= $adLimit) $flags[] = 'HIGH AD COST';
            return ['sku' => $r->sku, 'name' => $r->name, 'units' => (int) $r->units, 'revenue' => $rev, 'cogs' => (float) $r->cogs, 'ads' => (float) $r->ads, 'fees' => (float) $r->fees, 'refunds' => (float) $r->refunds,
                'recovery' => (float) $r->recovery, 'profit' => (float) $r->profit, 'margin' => $rev > 0 ? $r->profit / $rev * 100 : null, 'flags' => implode(', ', $flags)];
        })->all();
        $t = $this->sum($rows, ['units', 'revenue', 'cogs', 'ads', 'fees', 'refunds', 'recovery', 'profit']); $t['margin'] = $t['revenue'] > 0 ? round($t['profit'] / $t['revenue'] * 100, 1) : null;
        return ['rows' => $rows, 'totals' => $t, 'note' => "TOP PROFIT = the 10 most profitable. HIGH RETURNS = refunds at or above {$retLimit}% of revenue. HIGH AD COST = ads at or above {$adLimit}% of revenue (limits are in Settings)."];
    }

    private function r_marketplace_comparison(array $f): array
    {
        $rows = array_map(fn ($c) => ['channel' => $c['name'], 'orders' => $c['orders'], 'sales' => $c['sales'], 'profit' => $c['profit'], 'margin' => $c['margin'], 'ad_spend' => $c['adSpend'], 'acos' => $c['acos'], 'returns' => $c['returns'], 'refunded' => $c['refunded']],
            $this->dash->channels(['from' => $f['from'], 'to' => $f['to']], null));
        return ['rows' => $rows, 'totals' => $this->sum($rows, ['orders', 'sales', 'profit', 'ad_spend', 'returns', 'refunded'])];
    }

    private function r_cogs(array $f): array
    {
        $data = DB::table('order_profits as p')->join('products as pr', 'pr.id', '=', 'p.product_id')->whereBetween('p.order_date', [$f['from'], $f['to']])
            ->when($this->mp($f), fn ($q, $v) => $q->where('p.marketplace_id', $v))->when($f['product_id'] ?? null, fn ($q, $v) => $q->where('p.product_id', $v))->when($f['category_id'] ?? null, fn ($q, $v) => $q->where('pr.category_id', $v))
            ->selectRaw('p.product_id, pr.sku, pr.name, SUM(p.qty) units, SUM(p.cogs) cogs')->groupBy('p.product_id', 'pr.sku', 'pr.name')->orderByRaw('cogs DESC')->get();
        $now = Product::with('currentCosts')->whereIn('id', $data->pluck('product_id'))->get()->keyBy('id');
        $rows = $data->map(function ($r) use ($now) {
            $avg = $r->units > 0 ? $r->cogs / $r->units : 0; $cur = isset($now[$r->product_id]) ? (float) $now[$r->product_id]->totalUnitCost() : 0;
            return ['sku' => $r->sku, 'name' => $r->name, 'units' => (int) $r->units, 'cogs' => (float) $r->cogs, 'avg_unit' => round($avg, 2), 'current_unit' => round($cur, 2), 'change' => $avg > 0 ? ($cur - $avg) / $avg * 100 : null];
        })->all();
        return ['rows' => $rows, 'totals' => $this->sum($rows, ['units', 'cogs']), 'note' => 'Cost is the cost in force on each order date, so a supplier price change never rewrites old orders. "Change" shows how far today\'s cost has moved from the average you actually paid.'];
    }

    private function r_advertising(array $f): array
    {
        $s = $this->ads->summary(['from' => $f['from'], 'to' => $f['to'], 'marketplace_id' => $this->mp($f)]);
        $rows = $s['campaignRows']->map(function ($c) { $m = $this->ads->metrics($c);
            return ['campaign' => $c->campaign, 'spend' => $m['spend'], 'sales' => $m['sales'], 'acos' => $m['acos'], 'roas' => $m['roas'], 'orders' => $m['orders'], 'clicks' => $m['clicks'], 'impressions' => $m['impressions'], 'ctr' => $m['ctr'], 'cpc' => $m['cpc']]; })->all();
        $m = $s['totals']; $t = ['spend' => $m['spend'], 'sales' => $m['sales'], 'acos' => $m['acos'], 'roas' => $m['roas'], 'orders' => $m['orders'], 'clicks' => $m['clicks'], 'impressions' => $m['impressions'], 'ctr' => $m['ctr'], 'cpc' => $m['cpc']];
        return ['rows' => $rows, 'totals' => $t, 'note' => 'One report type per channel is used for totals (campaign, else product, else keyword, else search term) so nothing is counted twice.'];
    }

    private function r_returns(array $f): array
    {
        $rows = ProductReturn::with(['order:id,external_order_id', 'marketplace:id,name'])->withSum('items as qty', 'quantity')->withSum('items as recv', 'received_qty')->withSum('refunds as refunded', 'amount')
            ->whereBetween('return_date', [$f['from'], $f['to']])->when($this->mp($f), fn ($q, $v) => $q->where('marketplace_id', $v))
            ->when($f['product_id'] ?? null, fn ($q, $v) => $q->whereHas('items', fn ($i) => $i->where('product_id', $v)))->orderBy('return_date')->orderBy('id')->get()
            ->map(fn ($r) => ['return_number' => $r->return_number, 'date' => $r->return_date->toDateString(), 'order' => $r->order->external_order_id ?? '', 'channel' => $r->marketplace->name ?? '', 'reason' => ProductReturn::TYPES[$r->return_type] ?? $r->return_type,
                'status' => ucfirst(strtolower($r->status)), 'claim' => $r->claim_eligible ? 'Yes' : 'No', 'units' => (int) $r->qty, 'received' => (int) $r->recv, 'refunded' => (float) $r->refunded])->all();
        return ['rows' => $rows, 'totals' => $this->sum($rows, ['units', 'received', 'refunded'])];
    }

    private function r_claims(array $f): array
    {
        $rows = Claim::with(['marketplace:id,name', 'order:id,external_order_id', 'product:id,sku'])->whereDate('created_at', '>=', $f['from'])->whereDate('created_at', '<=', $f['to'])
            ->when($this->mp($f), fn ($q, $v) => $q->where('marketplace_id', $v))->when($f['product_id'] ?? null, fn ($q, $v) => $q->where('product_id', $v))->orderBy('id')->get()
            ->map(fn ($c) => ['claim_number' => $c->claim_number, 'created' => $c->created_at->toDateString(), 'channel' => $c->marketplace->name ?? '', 'type' => Claim::TYPES[$c->claim_type] ?? $c->claim_type, 'order' => $c->order->external_order_id ?? '',
                'sku' => $c->product->sku ?? '', 'status' => Claim::LABELS[$c->status] ?? $c->status, 'claimed' => (float) $c->claim_amount, 'approved' => (float) $c->approved_amount, 'rejected' => (float) $c->rejected_amount,
                'deadline' => $c->deadline?->toDateString(), 'decision' => $c->decision_date?->toDateString()])->all();
        return ['rows' => $rows, 'totals' => $this->sum($rows, ['claimed', 'approved', 'rejected'])];
    }

    private function r_inventory(array $f): array
    {
        $rows = Product::with(['inventory', 'currentCosts', 'category:id,name'])->when($f['product_id'] ?? null, fn ($q, $v) => $q->where('id', $v))->when($f['category_id'] ?? null, fn ($q, $v) => $q->where('category_id', $v))->orderBy('sku')->get()
            ->map(function ($p) { $i = $p->inventory; $a = (int) ($i->available_qty ?? 0); $min = (int) ($i->minimum_stock ?? 0); $cost = (float) $p->totalUnitCost();
                return ['sku' => $p->sku, 'name' => $p->name, 'category' => $p->category->name ?? '', 'available' => $a, 'damaged' => (int) ($i->damaged_qty ?? 0), 'reserved' => (int) ($i->reserved_qty ?? 0), 'minimum' => $min,
                    'reorder' => (int) ($i->reorder_level ?? 0), 'status' => $a <= 0 ? 'Out of stock' : ($min > 0 && $a <= $min ? 'Low' : 'OK'), 'unit_cost' => round($cost, 2), 'stock_value' => round($a * $cost, 2)]; })->all();
        return ['rows' => $rows, 'totals' => $this->sum($rows, ['available', 'damaged', 'reserved', 'stock_value'])];
    }

    private function r_purchases(array $f): array
    {
        $rows = Purchase::with('supplier:id,name')->whereBetween('purchase_date', [$f['from'], $f['to']])->when($f['product_id'] ?? null, fn ($q, $v) => $q->whereHas('items', fn ($i) => $i->where('product_id', $v)))
            ->orderBy('purchase_date')->orderBy('id')->get()->map(fn ($p) => ['po' => $p->po_number, 'date' => $p->purchase_date->toDateString(), 'supplier' => $p->supplier->name ?? '', 'invoice' => $p->invoice_number ?? '',
                'status' => ucfirst(strtolower($p->status)), 'payment' => ucfirst(strtolower($p->payment_status)), 'subtotal' => (float) $p->subtotal, 'gst' => (float) $p->gst_amount,
                'extras' => (float) $p->shipping_charges + (float) $p->other_charges, 'total' => (float) $p->total_amount, 'paid' => (float) $p->amount_paid, 'due' => round((float) $p->total_amount - (float) $p->amount_paid, 2)])->all();
        return ['rows' => $rows, 'totals' => $this->sum($rows, ['subtotal', 'gst', 'extras', 'total', 'paid', 'due'])];
    }

    private function r_expenses(array $f): array
    {
        $rows = Expense::with(['category:id,name', 'marketplace:id,name', 'product:id,sku'])->whereBetween('expense_date', [$f['from'], $f['to']])->when($this->mp($f), fn ($q, $v) => $q->where('marketplace_id', $v))
            ->when($f['product_id'] ?? null, fn ($q, $v) => $q->where('product_id', $v))->orderBy('expense_date')->orderBy('id')->get()
            ->map(fn ($e) => ['date' => $e->expense_date->toDateString(), 'category' => $e->category->name ?? '', 'channel' => $e->marketplace->name ?? 'General', 'product' => $e->product->sku ?? '', 'invoice' => $e->invoice_number ?? '', 'notes' => $e->notes ?? '', 'amount' => (float) $e->amount])->all();
        return ['rows' => $rows, 'totals' => $this->sum($rows, ['amount'])];
    }

    private function r_settlements(array $f): array
    {
        $fee = "'REFERRAL_FEE','CLOSING_FEE','SHIPPING_FEE','COLLECTION_FEE','PAYMENT_FEE','OTHER_FEE','FEE_TAX'";
        $rows = DB::table('settlements as s')->join('marketplaces as m', 'm.id', '=', 's.marketplace_id')->leftJoin('settlement_items as i', 'i.settlement_id', '=', 's.id')
            ->whereBetween('s.period_end', [$f['from'], $f['to']])->when($this->mp($f), fn ($q, $v) => $q->where('s.marketplace_id', $v))
            ->selectRaw("s.external_settlement_id sid, m.name channel, s.period_start pf, s.period_end pt, COUNT(i.id) lines, COALESCE(SUM(CASE WHEN i.kind = 'SALE' THEN i.amount ELSE 0 END),0) sales,
                COALESCE(SUM(CASE WHEN i.kind IN ({$fee}) THEN i.amount ELSE 0 END),0) fees, COALESCE(SUM(CASE WHEN i.kind = 'REFUND' THEN i.amount ELSE 0 END),0) refunds,
                COALESCE(SUM(CASE WHEN i.kind = 'REIMBURSEMENT' THEN i.amount ELSE 0 END),0) reimb, COALESCE(SUM(i.amount),0) net")
            ->groupBy('s.id', 's.external_settlement_id', 'm.name', 's.period_start', 's.period_end')->orderBy('s.period_end')->get()
            ->map(fn ($r) => ['settlement' => $r->sid, 'channel' => $r->channel, 'from' => $this->d($r->pf), 'to' => $this->d($r->pt), 'lines' => (int) $r->lines, 'sales' => (float) $r->sales, 'fees' => (float) $r->fees,
                'refunds' => (float) $r->refunds, 'reimbursed' => (float) $r->reimb, 'net' => (float) $r->net])->all();
        return ['rows' => $rows, 'totals' => $this->sum($rows, ['lines', 'sales', 'fees', 'refunds', 'reimbursed', 'net']), 'note' => 'Money out (fees, refunds) is negative. Net payout also includes tax lines and adjustments.'];
    }

    private function r_gst(array $f): array
    {
        $b = [];
        $key = fn ($d, $r) => substr((string) $d, 0, 7) . '|' . number_format((float) $r, 2, '.', '');
        $sales = DB::table('order_items as i')->join('orders as o', 'o.id', '=', 'i.order_id')->whereNull('o.deleted_at')->where('o.source', '<>', 'TEST')->where('o.status', '<>', 'CANCELLED')->whereBetween('o.order_date', [$f['from'] . ' 00:00:00', $f['to'] . ' 23:59:59'])
            ->when($this->mp($f), fn ($q, $v) => $q->where('o.marketplace_id', $v))->selectRaw('DATE(o.order_date) d, i.gst_rate r, SUM(i.line_total - i.tax_amount) taxable, SUM(i.tax_amount) gst')->groupByRaw('DATE(o.order_date), i.gst_rate')->get();
        foreach ($sales as $r) { $k = $key($r->d, $r->r); $b[$k]['ts'] = ($b[$k]['ts'] ?? 0) + (float) $r->taxable; $b[$k]['og'] = ($b[$k]['og'] ?? 0) + (float) $r->gst; }
        $purch = DB::table('purchase_items as i')->join('purchases as p', 'p.id', '=', 'i.purchase_id')->whereNull('p.deleted_at')->where('p.status', 'RECEIVED')->whereBetween('p.purchase_date', [$f['from'], $f['to']])
            ->selectRaw('p.purchase_date d, i.gst_rate r, SUM(i.line_subtotal) taxable, SUM(i.gst_amount) gst')->groupByRaw('p.purchase_date, i.gst_rate')->get();
        foreach ($purch as $r) { $k = $key($r->d, $r->r); $b[$k]['tp'] = ($b[$k]['tp'] ?? 0) + (float) $r->taxable; $b[$k]['ig'] = ($b[$k]['ig'] ?? 0) + (float) $r->gst; }
        ksort($b);
        $rows = [];
        foreach ($b as $k => $v) { [$m, $rate] = explode('|', $k);
            $rows[] = ['month' => $m, 'rate' => rtrim(rtrim($rate, '0'), '.') . '%', 'taxable_sales' => round($v['ts'] ?? 0, 2), 'output_gst' => round($v['og'] ?? 0, 2), 'taxable_purchases' => round($v['tp'] ?? 0, 2),
                'input_gst' => round($v['ig'] ?? 0, 2), 'net_gst' => round(($v['og'] ?? 0) - ($v['ig'] ?? 0), 2)]; }
        return ['rows' => $rows, 'totals' => $this->sum($rows, ['taxable_sales', 'output_gst', 'taxable_purchases', 'input_gst', 'net_gst']),
            'note' => 'Support working paper only, not a GST return. Sales exclude cancelled orders; refunds / credit notes and GST on marketplace fees are NOT netted here; purchases count only RECEIVED purchase orders. The channel filter applies to sales only.'];
    }

    /** the one-page morning report (spec: "SHREE GURVE DAILY BUSINESS REPORT") */
    public function daily(string $date, User $u): array
    {
        $f = ['from' => $date, 'to' => $date, 'marketplace_id' => null];
        $d = $this->dash->build($f); $since = now()->subDays(29)->toDateString();
        $retLimit = (float) SettingsService::get('default_return_threshold', 5); $acosLimit = (float) SettingsService::get('default_target_acos', 25);
        $safe = function (callable $fn, $fallback) { try { return $fn(); } catch (\Throwable $e) { report($e); return $fallback; } };
        return [
            'date' => $date, 'kpi' => $d['kpi'], 'channels' => $d['channels'], 'products' => $this->dash->products($f, null, 10),
            'lowStock' => $safe(fn () => Inventory::with('product:id,sku,name')->where(fn ($q) => $q->where('available_qty', '<=', 0)->orWhere(fn ($w) => $w->where('minimum_stock', '>', 0)->whereColumn('available_qty', '<=', 'minimum_stock')))->orderBy('available_qty')->limit(10)->get(), collect()),
            'highAcos' => $safe(fn () => $this->ads->summary($f)['campaignRows']->map(fn ($c) => ['name' => $c->campaign] + $this->ads->metrics($c))->filter(fn ($m) => $m['spend'] > 0 && ($m['acos'] === null || $m['acos'] > $acosLimit))->sortByDesc('spend')->take(5)->values(), collect()),
            'highReturns' => $safe(fn () => DB::table('order_profits as p')->join('products as pr', 'pr.id', '=', 'p.product_id')->where('p.order_date', '>=', $since)->selectRaw('pr.sku, pr.name, SUM(p.revenue) rev, SUM(p.return_loss) ret')
                ->groupBy('p.product_id', 'pr.sku', 'pr.name')->havingRaw('SUM(p.revenue) > 0 AND SUM(p.return_loss) * 100.0 / SUM(p.revenue) >= CAST(? AS DECIMAL(15,4))', [$retLimit])->orderByRaw('ret DESC')->limit(5)->get(), collect()),
            'claims' => $safe(fn () => Claim::whereIn('status', Claim::ALERT_STATUSES)->whereNotNull('deadline')->whereDate('deadline', '<=', now()->addDays(Claim::warningDays())->toDateString())->orderBy('deadline')->limit(10)->get(), collect()),
            'issues' => $safe(fn () => ReconciliationIssue::where('status', 'OPEN')->select('issue_type', DB::raw('count(*) c'))->groupBy('issue_type')->pluck('c', 'issue_type'), collect()),
            'expenseRows' => $safe(fn () => Expense::with('category:id,name')->where('expense_date', $date)->orderByDesc('amount')->limit(10)->get(), collect()),
            'actions' => $safe(fn () => app(DecisionService::class)->actions($u), []),
        ];
    }
}
