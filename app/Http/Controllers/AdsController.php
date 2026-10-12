<?php
namespace App\Http\Controllers;

use App\Models\{AdvertisingCampaign, Marketplace};
use App\Services\AdsService;
use Illuminate\Http\Request;

class AdsController extends Controller
{
    public function __construct(private AdsService $ads) {}

    private function filters(Request $r): array
    {
        return ['from' => $r->query('from', now()->subDays(29)->toDateString()), 'to' => $r->query('to', now()->toDateString()),
            'marketplace_id' => $r->query('marketplace_id') ?: null, 'campaign_id' => $r->query('campaign_id') ?: null, 'q' => $r->query('q'), 'sort' => $r->query('sort', 'spend')];
    }

    private function common(array $f): array
    {
        return ['f' => $f, 'marketplaces' => Marketplace::active()->get(), 'targetAcos' => (float) \App\Services\SettingsService::get('default_target_acos', 25),
            'wasteMin' => (float) \App\Services\SettingsService::get('ads_waste_min_spend', 200)];
    }

    public function index(Request $request)
    {
        $f = $this->filters($request);
        return view('ads.index', $this->common($f) + $this->ads->summary($f) + ['ads' => $this->ads]);
    }

    public function targets(Request $request, string $level)
    {
        abort_unless(in_array($level, ['keywords', 'terms'], true), 404);
        $f = $this->filters($request);
        return view('ads.targets', $this->common($f) + [
            'rows' => $this->ads->targets($level === 'keywords' ? 'KEYWORD' : 'TERM', $f, true, $f['sort']), 'level' => $level, 'ads' => $this->ads,
            'campaigns' => AdvertisingCampaign::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function insights(Request $request)
    {
        $f = $this->filters($request);
        return view('ads.insights', $this->common($f) + ['i' => $this->ads->insights($f)]);
    }
}
