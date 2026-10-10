<?php
namespace App\Http\Controllers;

use App\Models\ApprovalRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ApprovalController extends Controller
{
    public function index(Request $request)
    {
        $query = ApprovalRequest::query()->with(['requester','reviewer']);
        $businessId = $request->user()->business_id ?? null;
        if ($businessId) {
            $query->where('business_id', $businessId);
        }
        if ($request->filled('status') && in_array($request->status, ['pending','approved','rejected','cancelled'], true)) {
            $query->where('status', $request->status);
        }
        if ($request->filled('q')) {
            $term = trim($request->q);
            $query->where(fn($q) => $q->where('title','like',"%{$term}%")->orWhere('reference','like',"%{$term}%")->orWhere('request_type','like',"%{$term}%")->orWhere('type','like',"%{$term}%"));
        }
        $requests = $query->latest()->paginate(15)->withQueryString();
        $countQuery = ApprovalRequest::query();
        if ($businessId) { $countQuery->where('business_id', $businessId); }
        $counts = $countQuery->selectRaw("status, COUNT(*) as total")->groupBy('status')->pluck('total','status');
        return view('approvals.index', compact('requests','counts'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => ['required','string','max:180'],
            'request_type' => ['required','in:expense,purchase,price_change,refund,inventory_adjustment,other'],
            'reference' => ['nullable','string','max:100'],
            'amount' => ['nullable','numeric','min:0','max:999999999'],
            'description' => ['required','string','max:5000'],
        ]);
        $data['requested_by'] = $request->user()->id;
        $data['business_id'] = $request->user()->business_id ?? null;
        $data['type'] = $data['request_type'];
        $data['source'] = 'USER';
        $data['status'] = 'pending';
        $approval = ApprovalRequest::create($data);
        return redirect()->route('approvals.index')->with('success', 'Approval request #'.$approval->id.' submitted for review.');
    }

    public function approve(Request $request, ApprovalRequest $approvalRequest)
    {
        $this->ensureReviewer($request);
        $this->ensureSameBusiness($request, $approvalRequest);
        $data = $request->validate(['decision_note'=>['nullable','string','max:2000']]);
        abort_unless($approvalRequest->status === 'pending', 422, 'Only pending requests can be reviewed.');
        DB::transaction(function () use ($approvalRequest, $request, $data) {
            $locked = ApprovalRequest::whereKey($approvalRequest->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->status === 'pending', 422, 'This request has already been reviewed.');
            $locked->update(['status'=>'approved','reviewed_by'=>$request->user()->id,'reviewed_at'=>now(),'decision_note'=>$data['decision_note'] ?? null]);
        });
        return back()->with('success', 'Request approved. This records the decision; it does not automatically change marketplace or financial data.');
    }

    public function reject(Request $request, ApprovalRequest $approvalRequest)
    {
        $this->ensureReviewer($request);
        $this->ensureSameBusiness($request, $approvalRequest);
        $data = $request->validate(['decision_note'=>['required','string','max:2000']]);
        abort_unless($approvalRequest->status === 'pending', 422, 'Only pending requests can be reviewed.');
        DB::transaction(function () use ($approvalRequest, $request, $data) {
            $locked = ApprovalRequest::whereKey($approvalRequest->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->status === 'pending', 422, 'This request has already been reviewed.');
            $locked->update(['status'=>'rejected','reviewed_by'=>$request->user()->id,'reviewed_at'=>now(),'decision_note'=>$data['decision_note']]);
        });
        return back()->with('success', 'Request rejected.');
    }

    public function cancel(Request $request, ApprovalRequest $approvalRequest)
    {
        $this->ensureSameBusiness($request, $approvalRequest);
        abort_unless((int)$approvalRequest->requested_by === (int)$request->user()->id || $request->user()->isSuperAdmin(), 403);
        abort_unless($approvalRequest->status === 'pending', 422, 'Only pending requests can be cancelled.');
        $approvalRequest->update(['status'=>'cancelled']);
        return back()->with('success', 'Approval request cancelled.');
    }

    private function ensureSameBusiness(Request $request, ApprovalRequest $approvalRequest): void
    {
        $businessId = $request->user()->business_id ?? null;
        abort_unless(!$businessId || (int) $approvalRequest->business_id === (int) $businessId, 404);
    }

    private function ensureReviewer(Request $request): void
    {
        abort_unless($request->user()->isSuperAdmin() || $request->user()->hasPermission('settings.manage') || $request->user()->hasPermission('users.manage'), 403, 'You do not have permission to review approval requests.');
    }
}
