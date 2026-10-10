<?php
namespace App\Http\Controllers;

use App\Models\{ActivityLog, Category, Claim, Marketplace, Product};

class DashboardController extends Controller
{
    public function __invoke()
    {
        return view('dashboard', [
            'productCount' => Product::count(),
            'activeCount' => Product::where('status', 'active')->count(),
            'categoryCount' => Category::count(),
            'marketplaceCount' => Marketplace::active()->count(),
            'activity' => ActivityLog::with('user')->latest('id')->limit(8)->get(),
            'claimAlerts' => $this->claimAlerts(),
        ]);
    }

    /** claims where you must act soon; never lets a missing table break the landing page */
    private function claimAlerts()
    {
        try {
            if (! auth()->user()->can('claims.view')) return collect();
            return Claim::with('order:id,external_order_id')->whereIn('status', Claim::ALERT_STATUSES)->whereNotNull('deadline')
                ->whereDate('deadline', '<=', now()->addDays(Claim::warningDays())->toDateString())->orderBy('deadline')->limit(6)->get();
        } catch (\Throwable) { return collect(); }
    }
}
