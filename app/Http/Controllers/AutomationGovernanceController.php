<?php
namespace App\Http\Controllers;
use App\Models\ApprovalRequest;
use App\Models\ApprovalSlaPolicy;
use App\Models\BusinessActivityLog;
use App\Models\BusinessAutomationEvent;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AutomationGovernanceController extends Controller
{
    public function index(Request $request)
    {
        $user=$request->user();
        $events=BusinessAutomationEvent::with(['assignee','creator','rule'])
            ->when(!$user->isSuperAdmin(), function($q) use($user) {
                $q->whereIn('created_by',User::where('business_id',$user->business_id)->select('id'));
            })
            ->where('status','OPEN')->orderByRaw("CASE priority WHEN 'urgent' THEN 1 WHEN 'high' THEN 2 WHEN 'normal' THEN 3 ELSE 4 END")
            ->orderByRaw('CASE WHEN due_at IS NULL THEN 1 ELSE 0 END')->orderBy('due_at')->latest()->paginate(20);
        $users=User::query()->when(!$user->isSuperAdmin(),fn($q)=>$q->where('business_id',$user->business_id))->orderBy('name')->get(['id','name']);
        $policies=ApprovalSlaPolicy::orderBy('request_type')->get()->keyBy('request_type');
        $approvals=ApprovalRequest::with('requester')
            ->when(!$user->isSuperAdmin(),fn($q)=>$q->where('business_id',$user->business_id))
            ->where('status','pending')->latest()->limit(100)->get()
            ->filter(function($approval) use($policies) {
                $policy=$policies->get($approval->request_type ?: $approval->type);
                $hours=(int)($policy?->sla_hours ?? 48);
                return !$policy || $policy->is_enabled ? $approval->created_at->lte(now()->subHours(max(1,$hours))) : false;
            })->values();
        $logs=BusinessActivityLog::with('actor')
            ->when(!$user->isSuperAdmin(),fn($q)=>$q->where('business_id',$user->business_id))
            ->latest()->limit(30)->get();
        return view('automation.governance',compact('events','users','policies','approvals','logs'));
    }

    public function assignEvent(Request $request, BusinessAutomationEvent $event)
    {
        $this->authorizeEvent($request,$event);
        $data=$request->validate([
            'assigned_to'=>['nullable','integer','exists:users,id'],
            'due_at'=>['nullable','date'],
            'priority'=>['required','in:low,normal,high,urgent'],
        ]);
        if (!empty($data['assigned_to']) && !$request->user()->isSuperAdmin()) {
            abort_unless(User::where('id',$data['assigned_to'])->where('business_id',$request->user()->business_id)->exists(),422,'Assignee must belong to your business.');
        }
        $before=['assigned_to'=>$event->assigned_to,'due_at'=>$event->due_at?->toDateTimeString(),'priority'=>$event->priority];
        $event->update($data+['assigned_at'=>!empty($data['assigned_to'])?now():null]);
        $this->log($request,'automation.alert.updated',$event,['before'=>$before,'after'=>$data]);
        return back()->with('success','Alert work item updated.');
    }

    public function resolveEvent(Request $request, BusinessAutomationEvent $event)
    {
        $this->authorizeEvent($request,$event);
        abort_unless($event->status==='OPEN',422,'Only open alerts can be resolved.');
        $event->update(['status'=>'RESOLVED','resolved_at'=>now()]);
        $this->log($request,'automation.alert.resolved',$event,['title'=>$event->title]);
        return back()->with('success','Alert resolved and recorded in the activity trail.');
    }

    public function saveSlaPolicy(Request $request)
    {
        abort_unless($request->user()->isSuperAdmin()||$request->user()->hasPermission('settings.manage'),403);
        $data=$request->validate([
            'request_type'=>'required|in:expense,purchase,price_change,refund,inventory_adjustment,other',
            'sla_hours'=>'required|integer|between:1,720',
            'is_enabled'=>'required|boolean',
        ]);
        ApprovalSlaPolicy::updateOrCreate(['request_type'=>$data['request_type']],$data);
        $this->log($request,'approval.sla_policy.updated',null,$data);
        return back()->with('success','Approval SLA policy saved. Existing requests are evaluated against the current policy.');
    }

    private function authorizeEvent(Request $request,BusinessAutomationEvent $event): void
    {
        $user=$request->user();
        if($user->isSuperAdmin()) return;
        abort_unless($event->created_by && User::where('id',$event->created_by)->where('business_id',$user->business_id)->exists(),404);
        abort_unless($user->hasPermission('purchases.edit')||$user->hasPermission('reports.export'),403);
    }

    private function log(Request $request,string $action,$subject,array $details=[]): void
    {
        BusinessActivityLog::create([
            'business_id'=>$request->user()->business_id??null,
            'actor_id'=>$request->user()->id,
            'action'=>$action,
            'subject_type'=>$subject?get_class($subject):null,
            'subject_id'=>$subject?->id,
            'details'=>$details,
            'ip_address'=>$request->ip(),
        ]);
    }
}
