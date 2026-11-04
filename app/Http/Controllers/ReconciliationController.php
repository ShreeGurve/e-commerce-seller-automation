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
        $riskBase = ReconciliationIssue::query()->where('status', 'OPEN')->where(function ($q) {
            $q->whereNull('order_id')->orWhereHas('order', fn ($o) => $o->where('source', '<>', 'TEST'));
        });
        $risk = [
            'openCount' => (clone $riskBase)->count(),
            'exposure' => (float) (clone $riskBase)->sum(DB::raw('ABS(COALESCE(difference, 0))')),
            'criticalCount' => (clone $riskBase)->where(function ($q) {
                $q->whereRaw('ABS(COALESCE(difference, 0)) >= ?', [1000])->orWhere('detected_at', '<=', now()->subDays(7));
            })->count(),
            'agedCount' => (clone $riskBase)->whereNotNull('detected_at')->where('detected_at', '<=', now()->subDays(7))->count(),
        ];
        $marketplaceRisk = DB::table('reconciliation_issues as ri')
            ->leftJoin('settlements as s', 's.id', '=', 'ri.settlement_id')
            ->leftJoin('marketplaces as m', 'm.id', '=', 's.marketplace_id')
            ->leftJoin('orders as o', 'o.id', '=', 'ri.order_id')
            ->where('ri.status', 'OPEN')->where(function ($q) { $q->whereNull('ri.order_id')->orWhere('o.source', '<>', 'TEST'); })
            ->selectRaw("COALESCE(m.name, 'Unlinked / unknown') as marketplace, COUNT(*) as issue_count, SUM(ABS(COALESCE(ri.difference,0))) as exposure")
            ->groupBy('m.name')->orderByDesc('exposure')->limit(8)->get();
        return view('reconciliation.index', [
            'issues' => $issues, 'status' => $status, 'risk' => $risk, 'marketplaceRisk' => $marketplaceRisk,
            'byType' => ReconciliationIssue::where('status', 'OPEN')->select('issue_type', DB::raw('count(*) c'), DB::raw('SUM(difference) d'))->groupBy('issue_type')->get()->keyBy('issue_type'),
            'orderStatus' => Order::where('status', '!=', 'CANCELLED')->select('recon_status', DB::raw('count(*) c'))->groupBy('recon_status')->pluck('c', 'recon_status'),
        ]);
    }

    public function export(Request $request)
    {
        $status = $request->query('status', 'OPEN');
        $rows = ReconciliationIssue::with(['order:id,order_number,source', 'settlement:id,external_settlement_id'])
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->when($request->query('type'), fn ($q, $v) => $q->where('issue_type', $v))
            ->where(function ($q) { $q->whereNull('order_id')->orWhereHas('order', fn ($o) => $o->where('source', '<>', 'TEST')); })
            ->latest('detected_at')->get();
        $filename = 'reconciliation-risk-' . now()->format('Y-m-d-His') . '.csv';
        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w'); fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Detected At', 'Issue Type', 'Status', 'Order', 'Settlement', 'Expected', 'Actual', 'Difference', 'Message', 'Note']);
            foreach ($rows as $i) fputcsv($out, [$i->detected_at, $i->issue_type, $i->status, $i->order?->order_number ?? $i->external_order_id, $i->settlement?->external_settlement_id, $i->expected_amount, $i->actual_amount, $i->difference, $i->message, $i->note]);
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
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
