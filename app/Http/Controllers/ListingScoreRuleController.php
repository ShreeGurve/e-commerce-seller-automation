<?php
namespace App\Http\Controllers;

use App\Models\ListingScoreRule;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;

class ListingScoreRuleController extends Controller
{
    public function index()
    {
        $rows = ListingScoreRule::whereNull('marketplace_id')->get()->keyBy('criterion');
        return view('listings.rules', ['rows' => $rows]);
    }

    public function update(Request $request)
    {
        $request->validate(['max' => 'required|array', 'max.*' => 'required|numeric|min:0|max:100', 'params' => 'nullable|array', 'params.*.*' => 'nullable|integer|min:0|max:100000']);
        $changed = [];
        foreach (ListingScoreRule::DEFAULTS as $c => [$label, $defMax, $defParams]) {
            $params = [];
            foreach ($defParams as $k => $def) $params[$k] = (int) ($request->input("params.$c.$k", $def));
            if (($params['min_len'] ?? 0) > ($params['max_len'] ?? PHP_INT_MAX) || ($params['min_bytes'] ?? 0) > ($params['max_bytes'] ?? PHP_INT_MAX)) {
                return back()->withInput()->withErrors(["params.$c" => "{$label}: minimum cannot be above maximum."]);
            }
            $rule = ListingScoreRule::firstOrNew(['marketplace_id' => null, 'criterion' => $c]);
            $new = ['max_points' => (float) $request->input("max.$c", $defMax), 'params' => $params, 'is_active' => $request->boolean("active.$c")];
            $rule->fill($new);
            if ($rule->isDirty()) { $changed[$c] = $new; $rule->save(); }
        }
        if ($changed) ActivityLogger::log('listing_score_rules.updated', null, null, $changed);
        return back()->with('success', $changed ? 'Score rules saved. Press "Recalculate score" on a listing to apply them to it.' : 'Nothing changed.');
    }
}
