<?php
namespace App\Http\Controllers;

use App\Models\{Category, Marketplace, Order};
use App\Services\ProfitService;
use Illuminate\Http\Request;

class ProfitController extends Controller
{
    public function index(Request $request, ProfitService $profit)
    {
        $f = [
            'from' => $request->query('from', now()->subDays(29)->toDateString()), 'to' => $request->query('to', now()->toDateString()),
            'marketplace_id' => $request->query('marketplace_id'), 'category_id' => $request->query('category_id'), 'sort' => $request->query('sort', 'profit_desc'),
        ];
        $r = $profit->report($f);
        return view('profit.index', $r + ['f' => $f, 'marketplaces' => Marketplace::active()->get()->keyBy('id'), 'categories' => Category::orderBy('name')->get(),
            'sorts' => array_keys(ProfitService::SORTS)]);
    }

    public function recalculate(ProfitService $profit)
    {
        if (Order::count() > 5000) return back()->with('error', 'Many orders: run  php artisan profit:recalculate  in the project folder instead.');
        set_time_limit(300);
        return back()->with('success', 'Profit recalculated for ' . number_format($profit->recalcAll()) . ' orders.');
    }
}
