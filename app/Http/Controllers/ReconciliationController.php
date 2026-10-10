<?php
namespace App\Http\Controllers;

use App\Models\{Order, ReconciliationIssue};
use App\Services\{ActivityLogger, ReconciliationService};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReconciliationController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->query('status', 'OPEN');
        $issues = ReconciliationIssue::with(['order:id,order_number,external_order_id,marketplace_id', 'settlement:id,external_settlement_id'])
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))->when($request->type, fn ($q, $v) => $q->where('issue_type', $v))
            ->latest('detected_at')->latest('id')->paginate(30)->withQueryString();
        return view('reconciliation.index', [
            'issues' => $issues, 'status' => $status,
            'byType' => ReconciliationIssue::where('status', 'OPEN')->select('issue_type', DB::raw('count(*) c'), DB::raw('SUM(difference) d'))->groupBy('issue_type')->get()->keyBy('issue_type'),
            'orderStatus' => Order::where('status', '!=', 'CANCELLED')->select('recon_status', DB::raw('count(*) c'))->groupBy('recon_status')->pluck('c', 'recon_status'),
        ]);
    }

    public function run(ReconciliationService $recon)
    {
        set_time_limit(300);
        $r = $recon->run();
        return back()->with('success', "Checked {$r['scanned']} orders. {$r['new']} new issue(s), {$r['open']} open in total" . ($r['relinked'] ? ", {$r['relinked']} order(s) linked to earlier payouts." : '.'));
    }

    public function ignore(Request $request, ReconciliationIssue $reconciliationIssue)
    {
        $d = $request->validate(['note' => 'nullable|string|max:500']);
        $reconciliationIssue->update(['status' => 'IGNORED', 'note' => $d['note'] ?? null, 'resolved_by' => $request->user()->id, 'resolved_at' => now()]);
        ActivityLogger::log('reconciliation.ignored', $reconciliationIssue, ['status' => 'OPEN'], ['status' => 'IGNORED', 'note' => $d['note'] ?? null]);
        return back()->with('success', 'Issue ignored. It will not be reopened automatically.');
    }

    public function reopen(ReconciliationIssue $reconciliationIssue)
    {
        $reconciliationIssue->update(['status' => 'OPEN', 'resolved_by' => null, 'resolved_at' => null]);
        ActivityLogger::log('reconciliation.reopened', $reconciliationIssue, ['status' => 'IGNORED'], ['status' => 'OPEN']);
        return back()->with('success', 'Issue reopened.');
    }
}
