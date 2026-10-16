<?php
namespace App\Http\Controllers;

use App\Models\{ActivityLog, Category, Claim, Marketplace, Order, Product};
use App\Services\{DashboardService, DecisionService, SummaryService};
use Carbon\Carbon;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request, DashboardService $dash, DecisionService $decide)
    {
        $f = $this->filters($request);
        $data = null; $error = null;
        try { $data = $dash->build($f); } catch (\Throwable $e) { report($e); $error = 'The dashboard tables are not ready. Run  php artisan migrate  in the project folder, then reload.'; }

        $hasOrders = $data ? Order::exists() : false;
        return view('dashboard', [
            'f' => $f, 'd' => $data, 'error' => $error, 'marketplaces' => Marketplace::active()->get(),
            'actions' => auth()->user() ? $decide->actions($request->user()) : [],
            'needsRebuild' => $data && $hasOrders && $data['kpi']['orders'] === 0 && $data['kpi']['cancelled'] === 0 && \Illuminate\Support\Facades\DB::table('daily_sales')->doesntExist(),
            'setup' => ['products' => Product::count(), 'categories' => Category::count(), 'marketplaces' => Marketplace::count()],
            'claimAlerts' => $this->claimAlerts(),
            'activity' => ActivityLog::with('user')->latest('id')->limit(6)->get(),
        ]);
    }

    private function filters(Request $r): array
    {
        $to = $this->date($r->query('to')) ?? now(); $from = $this->date($r->query('from')) ?? now()->subDays(29);
        if ($from->gt($to)) [$from, $to] = [$to, $from];
        if ($from->diffInDays($to) > 730) $from = $to->copy()->subDays(730);        // keep charts light
        return ['from' => $from->toDateString(), 'to' => $to->toDateString(), 'marketplace_id' => $r->query('marketplace_id') ? (int) $r->query('marketplace_id') : null];
    }

    private function date($v): ?Carbon { try { return $v ? Carbon::parse($v)->startOfDay() : null; } catch (\Throwable) { return null; } }

    /** one-click catch-up for the speed tables (orders that existed before they were created) */
    public function rebuild(SummaryService $s)
    {
        set_time_limit(300);
        return redirect()->route('dashboard')->with('success', 'Dashboard numbers rebuilt for ' . number_format($s->rebuildAll()) . ' day/channel combinations.');
    }

    private function claimAlerts()
    {
        try {
            if (! auth()->user()->can('claims.view')) return collect();
            return Claim::with('order:id,external_order_id')->whereIn('status', Claim::ALERT_STATUSES)->whereNotNull('deadline')
                ->whereDate('deadline', '<=', now()->addDays(Claim::warningDays())->toDateString())->orderBy('deadline')->limit(6)->get();
        } catch (\Throwable) { return collect(); }
    }
}
