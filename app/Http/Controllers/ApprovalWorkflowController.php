<?php
namespace App\Http\Controllers;
use App\Models\ApprovalWorkflowPolicy;
use Illuminate\Http\Request;

class ApprovalWorkflowController extends Controller
{
    public function index()
    {
        $policies=ApprovalWorkflowPolicy::orderBy('request_type')->get();
        return view('approvals.workflows',compact('policies'));
    }
    public function store(Request $request)
    {
        $data=$request->validate([
            'request_type'=>'required|in:expense,purchase,price_change,refund,inventory_adjustment,other',
            'min_amount'=>'nullable|numeric|min:0|max:999999999999',
            'max_amount'=>'nullable|numeric|min:0|max:999999999999',
            'approval_levels'=>'required|integer|between:1,5',
            'reviewer_permission'=>'required|in:settings.manage,users.manage',
            'is_enabled'=>'required|boolean',
        ]);
        if (isset($data['min_amount'], $data['max_amount']) && (float)$data['max_amount'] < (float)$data['min_amount']) {
            return back()->withErrors(['max_amount'=>'Maximum amount must be greater than or equal to minimum amount.'])->withInput();
        }
        ApprovalWorkflowPolicy::updateOrCreate(['request_type'=>$data['request_type']],$data);
        return back()->with('success','Approval workflow policy saved. It applies to new requests only.');
    }
    public function destroy(ApprovalWorkflowPolicy $policy)
    {
        $policy->delete();
        return back()->with('success','Workflow policy removed. Existing approval steps are unchanged.');
    }
}
