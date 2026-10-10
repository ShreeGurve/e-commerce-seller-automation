<?php
namespace App\Http\Controllers;

class ModuleController extends Controller
{
    private const PHASES = [
        'purchases' => ['Purchases', 'Phase 1 (next step)'], 'inventory' => ['Inventory', 'Phase 1 (next step)'],
        'orders' => ['Orders', 'Phase 2'], 'returns' => ['Returns', 'Phase 2'],
        'claims' => ['Claims', 'Phase 6'], 'amazon' => ['Amazon', 'Phase 3'], 'flipkart' => ['Flipkart', 'Phase 3'],
        'advertising' => ['Advertising', 'Phase 5'], 'finance' => ['Finance', 'Phase 4'], 'profit' => ['Profit', 'Phase 4'],
        'listings' => ['Listings', 'Phase 7'], 'keywords' => ['Keywords', 'Phase 7'], 'pricing' => ['Pricing intelligence', 'Phase 7'], 'reports' => ['Reports', 'Phase 8'],
        'automation' => ['Automation', 'Phase 8'], 'notifications' => ['Notifications', 'Phase 8'],
        'approvals' => ['Approvals', 'Phase 8'], 'settings' => ['Settings', 'Phase 1 (next step)'],
    ];

    public function __invoke(string $module)
    {
        if ($module === 'approvals') return redirect()->route('approvals.index');
        abort_unless(isset(self::PHASES[$module]), 404);
        [$title, $phase] = self::PHASES[$module];
        return view('module', compact('title', 'phase'));
    }
}
