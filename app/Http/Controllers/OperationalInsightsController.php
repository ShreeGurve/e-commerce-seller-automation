<?php

namespace App\Http\Controllers;

use App\Models\ApprovalRequest;
use App\Models\BusinessActivityLog;
use App\Models\BusinessAutomationEvent;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OperationalInsightsController extends Controller
{
    private function scopedEvents(Request $request)
    {
        $user = $request->user();
        return BusinessAutomationEvent::query()->when(
            !$user->isSuperAdmin(),
            fn ($query) => $query->whereIn('created_by', User::where('business_id', $user->business_id)->select('id'))
        );
    }

    private function scopedApprovals(Request $request)
    {
        $user = $request->user();
        return ApprovalRequest::query()->when(
            !$user->isSuperAdmin(),
            fn ($query) => $query->where('business_id', $user->business_id)
        );
    }

    private function scopedLogs(Request $request)
    {
        $user = $request->user();
        return BusinessActivityLog::query()->when(
            !$user->isSuperAdmin(),
            fn ($query) => $query->where('business_id', $user->business_id)
        );
    }

    private function dateFilters(Request $request): array
    {
        return $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);
    }

    public function index(Request $request)
    {
        $filters = $this->dateFilters($request);
        $from = $filters['from'] ?? now()->startOfMonth()->toDateString();
        $to = $filters['to'] ?? now()->toDateString();

        $events = $this->scopedEvents($request)->whereBetween('created_at', [$from.' 00:00:00', $to.' 23:59:59']);
        $eventStats = (clone $events)->selectRaw("
            COUNT(*) as total,
            SUM(CASE WHEN status = 'OPEN' THEN 1 ELSE 0 END) as open_count,
            SUM(CASE WHEN status = 'RESOLVED' THEN 1 ELSE 0 END) as resolved_count,
            SUM(CASE WHEN status = 'OPEN' AND due_at IS NOT NULL AND due_at < NOW() THEN 1 ELSE 0 END) as overdue_count,
            AVG(CASE WHEN resolved_at IS NOT NULL THEN TIMESTAMPDIFF(HOUR, created_at, resolved_at) END) as avg_resolution_hours
        ")->first();

        $byPriority = (clone $events)->select('priority', DB::raw('COUNT(*) as total'))->groupBy('priority')->orderBy('priority')->get();
        $byAssignee = $this->scopedEvents($request)
            ->whereBetween('business_automation_events.created_at', [$from.' 00:00:00', $to.' 23:59:59'])
            ->leftJoin('users as assignees','business_automation_events.assigned_to','=','assignees.id')
            ->selectRaw("COALESCE(assignees.name, 'Unassigned') as assignee_name, COUNT(*) as total, SUM(CASE WHEN business_automation_events.status='RESOLVED' THEN 1 ELSE 0 END) as resolved")
            ->groupBy('business_automation_events.assigned_to','assignees.name')->orderByDesc('total')->limit(12)->get();

        $approvals = $this->scopedApprovals($request)->whereBetween('created_at', [$from.' 00:00:00', $to.' 23:59:59']);
        $approvalStats = (clone $approvals)->selectRaw("
            COUNT(*) as total,
            SUM(CASE WHEN status='pending' THEN 1 ELSE 0 END) as pending_count,
            SUM(CASE WHEN status='approved' THEN 1 ELSE 0 END) as approved_count,
            SUM(CASE WHEN status='rejected' THEN 1 ELSE 0 END) as rejected_count,
            AVG(CASE WHEN reviewed_at IS NOT NULL THEN TIMESTAMPDIFF(HOUR, created_at, reviewed_at) END) as avg_turnaround_hours
        ")->first();
        $approvalTypes = (clone $approvals)->select('request_type', DB::raw('COUNT(*) as total'))
            ->selectRaw("SUM(CASE WHEN status='approved' THEN 1 ELSE 0 END) as approved, SUM(CASE WHEN status='rejected' THEN 1 ELSE 0 END) as rejected, SUM(CASE WHEN status='pending' THEN 1 ELSE 0 END) as pending")
            ->groupBy('request_type')->orderByDesc('total')->get();

        $logs = $this->scopedLogs($request)->whereBetween('created_at', [$from.' 00:00:00', $to.' 23:59:59']);
        $logCount = (clone $logs)->count();
        $recentLogs = (clone $logs)->with('actor')->latest()->limit(15)->get();

        return view('automation.operational-insights', compact(
            'from','to','eventStats','byPriority','byAssignee','approvalStats','approvalTypes','logCount','recentLogs'
        ));
    }

    public function export(Request $request): StreamedResponse
    {
        abort_unless($request->user()->isSuperAdmin() || $request->user()->hasPermission('reports.export'), 403);
        $filters = $this->dateFilters($request);
        $from = $filters['from'] ?? now()->startOfMonth()->toDateString();
        $to = $filters['to'] ?? now()->toDateString();
        $dataset = $request->validate(['dataset' => ['required','in:alerts,approvals,audit']])['dataset'];
        $filename = 'seller-automation-'.$dataset.'-'.$from.'-to-'.$to.'.csv';

        return response()->streamDownload(function () use ($request,$dataset,$from,$to) {
            $out = fopen('php://output','w');
            fwrite($out, "\xEF\xBB\xBF");
            if ($dataset === 'alerts') {
                fputcsv($out,['ID','Title','Status','Priority','Severity','Assigned User ID','Due At','Created At','Resolved At']);
                $this->scopedEvents($request)->whereBetween('created_at',[$from.' 00:00:00',$to.' 23:59:59'])
                    ->orderBy('id')->chunk(500,function($rows) use($out) {
                        foreach($rows as $row) fputcsv($out,[$row->id,$row->title,$row->status,$row->priority,$row->severity,$row->assigned_to,$row->due_at,$row->created_at,$row->resolved_at]);
                    });
            } elseif ($dataset === 'approvals') {
                fputcsv($out,['ID','Title','Request Type','Reference','Amount','Status','Requested By','Reviewed By','Created At','Reviewed At']);
                $this->scopedApprovals($request)->whereBetween('created_at',[$from.' 00:00:00',$to.' 23:59:59'])
                    ->orderBy('id')->chunk(500,function($rows) use($out) {
                        foreach($rows as $row) fputcsv($out,[$row->id,$row->title,$row->request_type,$row->reference,$row->amount,$row->status,$row->requested_by,$row->reviewed_by,$row->created_at,$row->reviewed_at]);
                    });
            } else {
                fputcsv($out,['ID','Actor ID','Action','Subject Type','Subject ID','Details','IP Address','Created At']);
                $this->scopedLogs($request)->whereBetween('created_at',[$from.' 00:00:00',$to.' 23:59:59'])
                    ->orderBy('id')->chunk(500,function($rows) use($out) {
                        foreach($rows as $row) fputcsv($out,[$row->id,$row->actor_id,$row->action,$row->subject_type,$row->subject_id,json_encode($row->details),$row->ip_address,$row->created_at]);
                    });
            }
            fclose($out);
        },$filename,['Content-Type'=>'text/csv; charset=UTF-8']);
    }
}
