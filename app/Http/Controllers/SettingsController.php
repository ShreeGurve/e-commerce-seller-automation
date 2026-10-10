<?php
namespace App\Http\Controllers;

use App\Models\{Business, Marketplace};
use App\Services\{ActivityLogger, SettingsService};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SettingsController extends Controller
{
    /** key => [label, group, type, validation, help] */
    private const DEFAULTS = [
        'default_marketplace' => ['Default marketplace', 'general', 'string', 'nullable|exists:marketplaces,code', 'Pre-selected channel in forms and filters'],
        'fee_gst_rate' => ['GST on marketplace fees %', 'finance', 'decimal', 'required|numeric|between:0,100', 'Added to fee cost. Set 0 if you claim input tax credit on it'],
        'settlement_wait_days' => ['Days to wait for a payout', 'finance', 'int', 'required|integer|min:1|max:120', 'Shipped orders with no settlement line after this many days are flagged in Reconciliation'],
        'recon_fee_tolerance_percent' => ['Fee tolerance %', 'finance', 'decimal', 'required|numeric|between:0,100', 'Actual fees may differ from your fee rules by this much before they are flagged'],
        'ads_waste_min_spend' => ['Wasted keyword threshold (₹ spent, no sale)', 'advertising', 'decimal', 'required|numeric|min:0|max:1000000', 'Keywords / search terms that spent at least this much and never sold are listed as wasted'],
        'claim_filing_window_days' => ['Claim filing window (days)', 'claims', 'int', 'required|integer|min:1|max:365', 'New claims get a deadline this many days after the return date (editable per claim)'],
        'claim_urgent_days' => ['Claim URGENT alert (days left)', 'claims', 'int', 'required|integer|min:0|max:60', 'Claims at or under this many days to their deadline are flagged urgent'],
        'claim_warning_days' => ['Claim WARNING alert (days left)', 'claims', 'int', 'required|integer|min:0|max:120', 'Early warning window, should be larger than the urgent one'],
        'default_profit_margin' => ['Target profit margin %', 'finance', 'decimal', 'required|numeric|between:0,100', 'Used by pricing and loss alerts'],
        'default_minimum_stock' => ['Default minimum stock (units)', 'inventory', 'int', 'required|integer|min:0|max:1000000', 'Applied to NEW products when their stock row is created'],
        'default_target_acos' => ['Target ACOS %', 'advertising', 'decimal', 'required|numeric|between:0,100', 'Above this, high-ACOS alerts fire'],
        'default_return_threshold' => ['Return-rate alert threshold %', 'returns', 'decimal', 'required|numeric|between:0,100', 'Above this, return investigation alerts fire'],
    ];

    public function index()
    {
        $values = [];
        foreach (self::DEFAULTS as $k => $d) $values[$k] = SettingsService::get($k);
        return view('settings.index', ['business' => Business::first(), 'defaults' => self::DEFAULTS, 'values' => $values, 'marketplaces' => Marketplace::active()->get()]);
    }

    public function update(Request $request)
    {
        $rules = [
            'name' => 'required|string|max:255', 'legal_name' => 'nullable|string|max:255',
            'gstin' => ['nullable', 'string', 'size:15', 'regex:/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z][1-9A-Z]Z[0-9A-Z]$/i'],
        ];
        foreach (self::DEFAULTS as $k => $d) $rules[$k] = $d[3];
        $data = $request->validate($rules, ['gstin.regex' => 'GSTIN format is invalid.', 'gstin.size' => 'GSTIN must be 15 characters.']);

        DB::transaction(function () use ($data) {
            $b = Business::firstOrFail();
            $b->fill(['name' => $data['name'], 'legal_name' => $data['legal_name'] ?? null, 'gstin' => isset($data['gstin']) ? strtoupper($data['gstin']) : null]);
            $dirty = $b->getDirty(); $old = array_intersect_key($b->getOriginal(), $dirty);
            $b->save();
            if ($dirty) ActivityLogger::log('settings.business_changed', $b, $old, $dirty);

            $oldS = []; $newS = [];
            foreach (self::DEFAULTS as $k => [$label, $group, $type]) {
                $before = SettingsService::get($k); $after = $data[$k] ?? null;
                if ((string) $before !== (string) $after) { $oldS[$k] = $before; $newS[$k] = $after; }
                SettingsService::set($k, $after, $group, $type);
            }
            if ($newS) ActivityLogger::log('settings.defaults_changed', $b, $oldS, $newS);
        });
        return back()->with('success', 'Settings saved.');
    }
}
