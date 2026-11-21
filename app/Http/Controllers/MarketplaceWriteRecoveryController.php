<?php

namespace App\Http\Controllers;

use App\Services\MarketplaceWriteRecoveryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MarketplaceWriteRecoveryController extends Controller
{
    public function index(Request $request, MarketplaceWriteRecoveryService $service)
    {
        $businessId = (int) $request->user()->business_id;
        $data = $service->dashboard($request->user());
        $data['steps'] = DB::table('marketplace_write_recovery_steps as s')
            ->join('marketplace_write_recovery_cases as c', 'c.id', '=', 's.recovery_case_id')
            ->where('c.business_id', $businessId)
            ->select('s.*')->orderBy('s.sort_order')->get()->groupBy('recovery_case_id');
        $data['evidence'] = DB::table('marketplace_write_evidence')->where('business_id', $businessId)
            ->where('subject_type', 'RECOVERY')->orderByDesc('created_at')->get()->groupBy('subject_id');
        $data['closureReviews'] = DB::table('marketplace_write_recovery_closure_reviews as r')
            ->leftJoin('users as requester', 'requester.id', '=', 'r.requested_by')
            ->leftJoin('users as reviewer', 'reviewer.id', '=', 'r.reviewed_by')
            ->where('r.business_id', $businessId)
            ->select('r.*', 'requester.name as requester_name', 'reviewer.name as reviewer_name')
            ->orderByDesc('r.created_at')->get()->groupBy('recovery_case_id');

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

    public function close(Request $request, int $case)
    {
        return back()->withErrors(['case' => 'Direct closure is disabled. Submit a closure request for independent review.']);
    }
}
