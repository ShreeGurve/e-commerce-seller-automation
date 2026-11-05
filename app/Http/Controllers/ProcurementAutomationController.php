<?php
namespace App\Http\Controllers;
use App\Models\ProcurementRequisition;
use App\Models\ProcurementSupplierQuote;
use App\Models\ProcurementSupplierQuoteItem;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
class ProcurementAutomationController extends Controller {
 public function index(){
  $requisitions=ProcurementRequisition::with(['items','quotes.supplier','awardedQuote.supplier'])->latest()->paginate(20);
  $suppliers=Supplier::orderBy('name')->get();
  return view('purchases.procurement.index',compact('requisitions','suppliers'));
 }
 public function storeRequisition(Request $r){
  $d=$r->validate([
   'title'=>'required|string|max:180','purpose'=>'nullable|string|max:3000','required_by'=>'nullable|date',
   'priority'=>'required|in:LOW,NORMAL,HIGH,URGENT',
   'items'=>'required|array|min:1','items.*.item_name'=>'required|string|max:180',
   'items.*.sku'=>'nullable|string|max:100','items.*.quantity'=>'required|numeric|min:0.001',
   'items.*.unit'=>'nullable|string|max:30','items.*.estimated_unit_cost'=>'nullable|numeric|min:0',
   'items.*.specifications'=>'nullable|string|max:1000'
  ]);
  DB::transaction(function()use($r,$d){
   $req=ProcurementRequisition::create([
    'request_number'=>'PR-'.now()->format('ymd').'-'.Str::upper(Str::random(5)),
    'title'=>$d['title'],'purpose'=>$d['purpose']??null,'required_by'=>$d['required_by']??null,
    'priority'=>$d['priority'],'status'=>'SUBMITTED','requested_by'=>$r->user()->id
   ]);
   foreach($d['items'] as $item)$req->items()->create($item);
  });
  return back()->with('success','Purchase requisition submitted for review.');
 }
 public function approveRequisition(Request $r, ProcurementRequisition $requisition){
  abort_unless($requisition->status==='SUBMITTED',422,'Only submitted requisitions can be approved.');
  $d=$r->validate(['decision'=>'required|in:APPROVE,REJECT','approval_notes'=>'nullable|string|max:2000']);
  $requisition->update(['status'=>$d['decision']==='APPROVE'?'APPROVED':'REJECTED','approved_by'=>$r->user()->id,'approved_at'=>now(),'approval_notes'=>$d['approval_notes']??null]);
  return back()->with('success','Requisition review saved.');
 }
 public function storeQuote(Request $r, ProcurementRequisition $requisition){
  abort_unless($requisition->status==='APPROVED',422,'Quotes can only be recorded for approved requisitions.');
  $d=$r->validate([
   'supplier_id'=>'required|exists:suppliers,id','quote_reference'=>'nullable|string|max:100',
   'quote_date'=>'required|date','valid_until'=>'nullable|date|after_or_equal:quote_date',
   'shipping_cost'=>'nullable|numeric|min:0','tax_amount'=>'nullable|numeric|min:0','other_cost'=>'nullable|numeric|min:0',
   'lead_time_days'=>'nullable|integer|min:0','terms'=>'nullable|string|max:2000','notes'=>'nullable|string|max:2000',
   'items'=>'required|array|min:1','items.*.requisition_item_id'=>'required|exists:procurement_requisition_items,id',
   'items.*.unit_cost'=>'required|numeric|min:0','items.*.quantity'=>'required|numeric|min:0.001',
   'items.*.availability'=>'required|in:AVAILABLE,PARTIAL,UNAVAILABLE'
  ]);
  $validIds=$requisition->items()->pluck('id')->map(fn($v)=>(int)$v)->all();
  $seen=[];
  foreach($d['items'] as $item){
   if(!in_array((int)$item['requisition_item_id'],$validIds,true)||in_array((int)$item['requisition_item_id'],$seen,true))
    return back()->withErrors(['items'=>'Quote lines must refer once to items in this requisition.'])->withInput();
   $seen[]=(int)$item['requisition_item_id'];
  }
  DB::transaction(function()use($r,$requisition,$d){
   $quote=$requisition->quotes()->create([
    'supplier_id'=>$d['supplier_id'],'quote_reference'=>$d['quote_reference']??null,'quote_date'=>$d['quote_date'],
    'valid_until'=>$d['valid_until']??null,'shipping_cost'=>$d['shipping_cost']??0,'tax_amount'=>$d['tax_amount']??0,
    'other_cost'=>$d['other_cost']??0,'lead_time_days'=>$d['lead_time_days']??null,'terms'=>$d['terms']??null,
    'notes'=>$d['notes']??null,'recorded_by'=>$r->user()->id,'status'=>'RECEIVED'
   ]);
   $linesTotal=0;
   foreach($d['items'] as $item){
    $line=round((float)$item['unit_cost']*(float)$item['quantity'],2);$linesTotal+=$line;
    $quote->items()->create($item+['line_total'=>$line]);
   }
   $quote->update(['quoted_total'=>round($linesTotal+(float)$quote->shipping_cost+(float)$quote->tax_amount+(float)$quote->other_cost,2)]);
  });
  return back()->with('success','Supplier quotation recorded for comparison.');
 }
 public function awardQuote(Request $r, ProcurementRequisition $requisition, ProcurementSupplierQuote $quote){
  abort_unless($requisition->status==='APPROVED' && (int)$quote->requisition_id===(int)$requisition->id,422,'Invalid requisition or quote.');
  if($quote->valid_until && $quote->valid_until->isPast()) return back()->withErrors(['quote'=>'This quotation has expired.']);
  $d=$r->validate(['award_reason'=>'required|string|max:2000']);
  DB::transaction(function()use($r,$requisition,$quote,$d){
   DB::table('procurement_award_reviews')->insert([
    'requisition_id'=>$requisition->id,'quote_id'=>$quote->id,'reviewed_by'=>$r->user()->id,
    'decision'=>'AWARDED','reason'=>$d['award_reason'],'reviewed_at'=>now(),'created_at'=>now(),'updated_at'=>now()
   ]);
   $requisition->update(['status'=>'AWARDED','awarded_quote_id'=>$quote->id,'awarded_by'=>$r->user()->id,'awarded_at'=>now(),'award_reason'=>$d['award_reason']]);
   $quote->update(['status'=>'AWARDED']);
   $requisition->quotes()->where('id','!=',$quote->id)->update(['status'=>'NOT_SELECTED']);
  });
  return back()->with('success','Quote award recorded. No purchase order was sent to the supplier.');
 }
}
