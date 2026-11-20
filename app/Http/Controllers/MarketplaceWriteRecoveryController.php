<?php

namespace App\Http\Controllers;

use App\Services\MarketplaceWriteRecoveryService;
use Illuminate\Http\Request;

class MarketplaceWriteRecoveryController extends Controller
{
    public function index(Request $request, MarketplaceWriteRecoveryService $service)
    {
        $data = $service->dashboard($request->user());
        $data['steps'] = \Illuminate\Support\Facades\DB::table('marketplace_write_recovery_steps as s')
            ->join('marketplace_write_recovery_cases as c', 'c.id', '=', 's.recovery_case_id')
            ->where('c.business_id', (int) $request->user()->business_id)
            ->select('s.*')->orderBy('s.sort_order')->get()->groupBy('recovery_case_id');
        return view('marketplaces.write-recovery', $data);
    }

    public function store(Request $request, MarketplaceWriteRecoveryService $service)
    {
        $id = $service->create($request->user(), $request->all());
        return redirect()->route('marketplaces.writes.recovery.index')->with('success', 'Recovery case created (#'.$id.').');
    }

    public function updateStep(Request $request, int $case, int $step, MarketplaceWriteRecoveryService $service)
    {
        $service->updateStep($request->user(), $case, $step, $request->all());
        return back()->with('success', 'Recovery step updated.');
    }

    public function close(Request $request, int $case, MarketplaceWriteRecoveryService $service)
    {
        $service->close($request->user(), $case, $request->all());
        return back()->with('success', 'Recovery case closed.');
    }
}
