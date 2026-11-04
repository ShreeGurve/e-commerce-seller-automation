<?php
namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BusinessAnalyticsController extends Controller
{
    public function index(Request $request)
    {
        [$from, $to, $marketplaceId] = $this->filters($request);
        $days = max(1, $from->diffInDays($to) + 1);
        $previousTo = $from->copy()->subDay();
        $previousFrom = $previousTo->copy()->subDays($days - 1);

        $base = fn () => DB::table('order_profits as op')
            ->join('orders as o', 'o.id', '=', 'op.order_id')
            ->where('o.source', '<>', 'TEST')
            ->where('o.status', '<>', 'CANCELLED')
            ->whereBetween('op.order_date', [$from->toDateString(), $to->toDateString()])
            ->when($marketplaceId, fn ($q) => $q->where('op.marketplace_id', $marketplaceId));

        $totals = (clone ($base()))->selectRaw('COUNT(DISTINCT op.order_id) as orders, COALESCE(SUM(op.qty),0) as units, COALESCE(SUM(op.revenue),0) as revenue, COALESCE(SUM(COALESCE(op.actual_profit, op.estimated_profit)),0) as profit')->first();
        $prev = DB::table('order_profits as op')->join('orders as o','o.id','=','op.order_id')->where('o.source','<>','TEST')->where('o.status','<>','CANCELLED')
            ->whereBetween('op.order_date',[$previousFrom->toDateString(),$previousTo->toDateString()])->when($marketplaceId,fn($q)=>$q->where('op.marketplace_id',$marketplaceId))
            ->selectRaw('COALESCE(SUM(op.revenue),0) as revenue, COALESCE(SUM(COALESCE(op.actual_profit,op.estimated_profit)),0) as profit, COUNT(DISTINCT op.order_id) as orders')->first();

        $daily = (clone ($base()))->selectRaw('op.order_date as day, COUNT(DISTINCT op.order_id) as orders, SUM(op.qty) as units, SUM(op.revenue) as revenue, SUM(COALESCE(op.actual_profit,op.estimated_profit)) as profit')
            ->groupBy('op.order_date')->orderBy('op.order_date')->get();
        $products = (clone ($base()))->join('products as p','p.id','=','op.product_id')->selectRaw('p.id,p.sku,p.name,SUM(op.qty) as units,SUM(op.revenue) as revenue,SUM(COALESCE(op.actual_profit,op.estimated_profit)) as profit')
            ->groupBy('p.id','p.sku','p.name')->orderByDesc('profit')->limit(10)->get();
        $channels = (clone ($base()))->join('marketplaces as m','m.id','=','op.marketplace_id')->selectRaw('m.id,m.name,COUNT(DISTINCT op.order_id) as orders,SUM(op.qty) as units,SUM(op.revenue) as revenue,SUM(COALESCE(op.actual_profit,op.estimated_profit)) as profit')
            ->groupBy('m.id','m.name')->orderByDesc('revenue')->get();
        $lowMargin = (clone ($base()))->selectRaw('COUNT(*) as line_count')->whereRaw('COALESCE(op.actual_profit,op.estimated_profit) < 0')->value('line_count');
        $returnLoss = (clone ($base()))->sum('op.return_loss');
        $avgOrder = (int)($totals->orders ?? 0) > 0 ? (float)$totals->revenue / (int)$totals->orders : 0;
        $marketplaces = DB::table('marketplaces')->where('is_active',1)->orderBy('sort_order')->get(['id','name']);
        $maxRevenue = max(1, (float)$daily->max('revenue'));

        return view('analytics.business', compact('from','to','marketplaceId','marketplaces','totals','prev','daily','products','channels','lowMargin','returnLoss','avgOrder','maxRevenue'));
    }

    public function export(Request $request)
    {
        [$from,$to,$marketplaceId] = $this->filters($request);
        $rows = DB::table('order_profits as op')->join('orders as o','o.id','=','op.order_id')->join('products as p','p.id','=','op.product_id')->join('marketplaces as m','m.id','=','op.marketplace_id')
            ->where('o.source','<>','TEST')->where('o.status','<>','CANCELLED')->whereBetween('op.order_date',[$from->toDateString(),$to->toDateString()])
            ->when($marketplaceId,fn($q)=>$q->where('op.marketplace_id',$marketplaceId))
            ->orderBy('op.order_date')->get(['op.order_date','o.order_number','m.name as marketplace','p.sku','p.name as product','op.qty','op.revenue','op.cogs','op.fees','op.return_loss','op.ads_cost','op.estimated_profit','op.actual_profit']);
        $filename='business-analytics-'.$from->toDateString().'-to-'.$to->toDateString().'.csv';
        return response()->streamDownload(function() use($rows){
            $out=fopen('php://output','w'); fwrite($out,"\xEF\xBB\xBF");
            fputcsv($out,['Date','Order','Marketplace','SKU','Product','Units','Revenue','COGS','Fees','Return loss','Ads cost','Estimated profit','Actual profit']);
            foreach($rows as $r) fputcsv($out,[$r->order_date,$r->order_number,$r->marketplace,$r->sku,$r->product,$r->qty,$r->revenue,$r->cogs,$r->fees,$r->return_loss,$r->ads_cost,$r->estimated_profit,$r->actual_profit]);
            fclose($out);
        },$filename,['Content-Type'=>'text/csv; charset=UTF-8']);
    }

    private function filters(Request $request): array
    {
        try { $to=Carbon::parse($request->query('to',now()->toDateString()))->startOfDay(); } catch (\Throwable) { $to=now()->startOfDay(); }
        try { $from=Carbon::parse($request->query('from',now()->subDays(29)->toDateString()))->startOfDay(); } catch (\Throwable) { $from=now()->subDays(29)->startOfDay(); }
        if($from->gt($to)) [$from,$to]=[$to,$from];
        if($to->gt(now()->endOfDay())) $to=now()->startOfDay();
        $mp=$request->integer('marketplace_id') ?: null;
        return [$from,$to,$mp];
    }
}
