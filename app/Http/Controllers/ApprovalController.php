<?php
namespace App\Http\Controllers;

use App\Models\ApprovalRequest;
use App\Models\ApprovalWorkflowPolicy;
use App\Models\BusinessActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ApprovalController extends Controller
{
    public function index(Request $request)
    {
        $query = ApprovalRequest::query()->with(['requester','reviewer','workflowSteps.reviewer']);
        $user = $request->user();
        if (!$user->isSuperAdmin()) $query->where('business_id', $user->business_id);
        if ($request->filled('status') && in_array($request->status, ['pending','approved','rejected','cancelled'], true)) $query->where('status', $request->status);
        if ($request->filled('q')) {
            $term=trim($request->q);
            $query->where(fn($q)=>$q->where('title','like',"%{$term}%")->orWhere('reference','like',"%{$term}%")->orWhere('request_type','like',"%{$term}%")->orWhere('type','like',"%{$term}%"));
        }
        $requests=$query->latest()->paginate(15)->withQueryString();
        $countQuery=ApprovalRequest::query();
        if (!$user->isSuperAdmin()) $countQuery->where('business_id',$user->business_id);
        $counts=$countQuery->selectRaw("status, COUNT(*) as total")->groupBy('status')->pluck('total','status');
        return view('approvals.index',compact('requests','counts'));
    }

    public function store(Request $request)
    {
        $data=$request->validate([
            'title'=>['required','string','max:180'],
            'request_type'=>['required','in:expense,purchase,price_change,refund,inventory_adjustment,other'],
            'reference'=>['nullable','string','max:100'],
            'amount'=>['nullable','numeric','min:0','max:999999999'],
            'description'=>['required','string','max:5000'],
        ]);
        DB::transaction(function()use($request,$data){
            $data['requested_by']=$request->user()->id;
            $data['business_id']=$request->user()->business_id??null;
            $data['type']=$data['request_type']; $data['source']='USER'; $data['status']='pending';
            $approval=ApprovalRequest::create($data);
            $amount=(float)($data['amount']??0);
            $policy=ApprovalWorkflowPolicy::where('request_type',$data['request_type'])->where('is_enabled',true)
                ->where(fn($q)=>$q->whereNull('min_amount')->orWhere('min_amount','<=',$amount))
                ->where(fn($q)=>$q->whereNull('max_amount')->orWhere('max_amount','>=',$amount))
                ->first();
            if($policy){
                for($step=1;$step<=(int)$policy->approval_levels;$step++){
                    $approval->workflowSteps()->create(['step_number'=>$step,'status'=>'pending','required_permission'=>$policy->reviewer_permission]);
                }
            }
        });
        return redirect()->route('approvals.index')->with('success','Approval request submitted. Any configured approval levels must be completed in sequence.');
    }

    public function approve(Request $request, ApprovalRequest $approvalRequest)
    {
        $this->ensureReviewer($request); $this->ensureSameBusiness($request,$approvalRequest);
        $data=$request->validate(['decision_note'=>['nullable','string','max:2000']]);
        DB::transaction(function()use($approvalRequest,$request,$data){
            $locked=ApprovalRequest::whereKey($approvalRequest->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->status==='pending',422,'Only pending requests can be reviewed.');
            $step=$locked->workflowSteps()->where('status','pending')->orderBy('step_number')->lockForUpdate()->first();
            if($step){
                $this->ensureStepPermission($request,$step->required_permission);
                $step->update(['status'=>'approved','reviewed_by'=>$request->user()->id,'reviewed_at'=>now(),'decision_note'=>$data['decision_note']??null]);
                $next=$locked->workflowSteps()->where('status','pending')->exists();
                if(!$next) $locked->update(['status'=>'approved','reviewed_by'=>$request->user()->id,'reviewed_at'=>now(),'decision_note'=>$data['decision_note']??null]);
            } else {
                $locked->update(['status'=>'approved','reviewed_by'=>$request->user()->id,'reviewed_at'=>now(),'decision_note'=>$data['decision_note']??null]);
            }
        });
        BusinessActivityLog::create(['business_id'=>$request->user()->business_id??null,'actor_id'=>$request->user()->id,'action'=>'approval.request.approved','subject_type'=>ApprovalRequest::class,'subject_id'=>$approvalRequest->id,'details'=>['decision_note'=>$data['decision_note']??null,'status'=>$approvalRequest->fresh()->status],'ip_address'=>$request->ip()]);
        return back()->with('success','Approval decision recorded. Multi-level requests remain pending until every configured level approves.');
    }

    public function reject(Request $request, ApprovalRequest $approvalRequest)
    {
        $this->ensureReviewer($request); $this->ensureSameBusiness($request,$approvalRequest);
        $data=$request->validate(['decision_note'=>['required','string','max:2000']]);
        DB::transaction(function()use($approvalRequest,$request,$data){
            $locked=ApprovalRequest::whereKey($approvalRequest->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->status==='pending',422,'Only pending requests can be reviewed.');
            $step=$locked->workflowSteps()->where('status','pending')->orderBy('step_number')->lockForUpdate()->first();
            if($step){
                $this->ensureStepPermission($request,$step->required_permission);
                $step->update(['status'=>'rejected','reviewed_by'=>$request->user()->id,'reviewed_at'=>now(),'decision_note'=>$data['decision_note']]);
            }
            $locked->update(['status'=>'rejected','reviewed_by'=>$request->user()->id,'reviewed_at'=>now(),'decision_note'=>$data['decision_note']]);
        });
        BusinessActivityLog::create(['business_id'=>$request->user()->business_id??null,'actor_id'=>$request->user()->id,'action'=>'approval.request.rejected','subject_type'=>ApprovalRequest::class,'subject_id'=>$approvalRequest->id,'details'=>['decision_note'=>$data['decision_note']],'ip_address'=>$request->ip()]);
        return back()->with('success','Request rejected.');
    }

    public function cancel(Request $request, ApprovalRequest $approvalRequest)
    {
        $this->ensureSameBusiness($request,$approvalRequest);
        abort_unless((int)$approvalRequest->requested_by===(int)$request->user()->id||$request->user()->isSuperAdmin(),403);
        abort_unless($approvalRequest->status==='pending',422,'Only pending requests can be cancelled.');
        $approvalRequest->update(['status'=>'cancelled']);
        BusinessActivityLog::create(['business_id'=>$request->user()->business_id??null,'actor_id'=>$request->user()->id,'action'=>'approval.request.cancelled','subject_type'=>ApprovalRequest::class,'subject_id'=>$approvalRequest->id,'details'=>[],'ip_address'=>$request->ip()]);
        return back()->with('success','Approval request cancelled.');
    }

    private function ensureSameBusiness(Request $request, ApprovalRequest $approvalRequest): void
    {
        $user=$request->user();
        if($user->isSuperAdmin()) return;
        abort_unless($approvalRequest->business_id!==null&&$user->business_id!==null&&(int)$approvalRequest->business_id===(int)$user->business_id,404);
    }
    private function ensureReviewer(Request $request): void
    {
        abort_unless($request->user()->isSuperAdmin()||$request->user()->hasPermission('settings.manage')||$request->user()->hasPermission('users.manage'),403,'You do not have permission to review approval requests.');
    }
    private function ensureStepPermission(Request $request,string $permission): void
    {
        $user=$request->user();
        abort_unless($user->isSuperAdmin()||$user->hasPermission($permission),403,'You do not have permission for this approval level.');
    }
}
