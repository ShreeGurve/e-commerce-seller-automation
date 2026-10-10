<?php
namespace App\Http\Controllers;use App\Models\{AutomationLog,AutomationRule};use App\Services\AutomationService;use Illuminate\Http\Request;use Illuminate\Validation\Rule;
class AutomationController extends Controller {
 public function index(){return view('automation.index',['rules'=>AutomationRule::withCount('logs')->orderByDesc('is_active')->orderBy('name')->get(),'logs'=>AutomationLog::with('rule')->latest()->limit(30)->get(),'triggers'=>AutomationRule::TRIGGERS]);}
 public function store(Request $r){$d=$r->validate(['name'=>['required','string','max:120'],'trigger_key'=>['required',Rule::in(array_keys(AutomationRule::TRIGGERS))],'threshold'=>['required','numeric','min:0','max:999999999']]);$d['created_by']=$r->user()->id;$d['is_active']=$r->boolean('is_active');AutomationRule::create($d);return back()->with('success','Automation rule created.');}
 public function toggle(AutomationRule $automationRule){$automationRule->update(['is_active'=>!$automationRule->is_active]);return back()->with('success','Rule status updated.');}
 public function destroy(AutomationRule $automationRule){$automationRule->delete();return back()->with('success','Rule deleted.');}
 public function run(AutomationService $service){$x=$service->run(request()->user());return back()->with('success',"Run complete: {$x['rules']} active rules, {$x['matched']} matches, {$x['created']} new alerts.");}
}
