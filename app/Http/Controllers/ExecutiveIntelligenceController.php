<?php
namespace App\Http\Controllers;
use App\Models\BusinessAutomationRule;
use App\Models\BusinessAutomationEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExecutiveIntelligenceController extends Controller
{
 public function index()
 {
  $metrics = [
   'invoice_count'=>Schema::hasTable('purchase_invoice_matches') ? DB::table('purchase_invoice_matches')->count() : 0,
   'invoice_value'=>Schema::hasTable('purchase_invoice_matches') ? (float)DB::table('purchase_invoice_matches')->sum('invoice_total') : 0,
   'payments_confirmed'=>Schema::hasTable('supplier_invoice_payments') ? (float)DB::table('supplier_invoice_payments')->where('status','CONFIRMED')->sum('amount') : 0,
   'payments_count'=>Schema::hasTable('supplier_invoice_payments') ? DB::table('supplier_invoice_payments')->where('status','CONFIRMED')->count() : 0,
   'requisitions_open'=>Schema::hasTable('procurement_requisitions') ? DB::table('procurement_requisitions')->whereIn('status',['DRAFT','SUBMITTED','UNDER_REVIEW'])->count() : 0,
   'quotes_received'=>Schema::hasTable('procurement_supplier_quotes') ? DB::table('procurement_supplier_quotes')->where('status','RECEIVED')->count() : 0,
   'warehouse_skus'=>Schema::hasTable('warehouse_stock_balances') ? DB::table('warehouse_stock_balances')->distinct('sku')->count('sku') : 0,
   'warehouse_units'=>Schema::hasTable('warehouse_stock_balances') ? (float)DB::table('warehouse_stock_balances')->sum('quantity') : 0,
   'transfers_in_transit'=>Schema::hasTable('warehouse_transfers') ? DB::table('warehouse_transfers')->where('status','IN_TRANSIT')->count() : 0,
   'stock_count_drafts'=>Schema::hasTable('warehouse_stock_counts') ? DB::table('warehouse_stock_counts')->where('status','DRAFT')->count() : 0,
  ];
  $events=BusinessAutomationEvent::with('rule')->where('status','OPEN')->latest()->limit(15)->get();
  $rules=BusinessAutomationRule::orderBy('id')->get();
  return view('executive.intelligence',compact('metrics','events','rules'));
 }
 public function saveRule(Request $request)
 {
  $d=$request->validate(['rule_key'=>'required|in:low_stock,overdue_requisition,invoice_review','threshold_value'=>'required|numeric|min:0|max:999999999',
   'is_enabled'=>'required|boolean']);
  $defaults=[
   'low_stock'=>['name'=>'Low warehouse stock','description'=>'Create an alert when an internal warehouse SKU balance is at or below the threshold.'],
   'overdue_requisition'=>['name'=>'Overdue procurement request','description'=>'Create an alert for open requisitions whose required-by date has passed.'],
   'invoice_review'=>['name'=>'Supplier invoice needs review','description'=>'Create an alert when an invoice match is marked REVIEW_REQUIRED.'],
  ];
  BusinessAutomationRule::updateOrCreate(['rule_key'=>$d['rule_key']],$defaults[$d['rule_key']]+$d);
  return back()->with('success','Automation rule saved. Run evaluation to check current records.');
 }
 public function evaluate(Request $request)
 {
  $created=0;
  $rules=BusinessAutomationRule::where('is_enabled',true)->get()->keyBy('rule_key');
  if(isset($rules['low_stock']) && Schema::hasTable('warehouse_stock_balances')){
   $rule=$rules['low_stock'];
   DB::table('warehouse_stock_balances')->where('quantity','<=',(float)$rule->threshold_value)->orderBy('id')->chunk(200,function($rows)use($rule,&$created){
    foreach($rows as $row){$fp='low-stock:'.$row->id.':'.now()->toDateString();
     $created+=BusinessAutomationEvent::firstOrCreate(['event_fingerprint'=>$fp],['rule_id'=>$rule->id,'event_key'=>'LOW_STOCK','severity'=>'WARNING','title'=>'Low stock: '.$row->sku,'message'=>$row->item_name.' ('.$row->sku.') has '.$row->quantity.' '.$row->unit.' remaining.','source_type'=>'warehouse_stock_balances','source_id'=>$row->id])->wasRecentlyCreated?1:0;
    }
   });
  }
  if(isset($rules['overdue_requisition']) && Schema::hasTable('procurement_requisitions')){
   $rule=$rules['overdue_requisition'];
   $rows=DB::table('procurement_requisitions')->whereNotNull('required_by')->whereDate('required_by','<',today())->whereIn('status',['DRAFT','SUBMITTED','UNDER_REVIEW'])->get();
   foreach($rows as $row){$fp='overdue-requisition:'.$row->id.':'.now()->toDateString();
    $created+=BusinessAutomationEvent::firstOrCreate(['event_fingerprint'=>$fp],['rule_id'=>$rule->id,'event_key'=>'OVERDUE_REQUISITION','severity'=>'HIGH','title'=>'Overdue requisition: '.$row->request_number,'message'=>$row->title.' was required by '.$row->required_by.'.','source_type'=>'procurement_requisitions','source_id'=>$row->id])->wasRecentlyCreated?1:0;
   }
  }
  if(isset($rules['invoice_review']) && Schema::hasTable('purchase_invoice_matches')){
   $rule=$rules['invoice_review'];
   $rows=DB::table('purchase_invoice_matches')->where('match_status','REVIEW_REQUIRED')->get();
   foreach($rows as $row){$fp='invoice-review:'.$row->id;
    $created+=BusinessAutomationEvent::firstOrCreate(['event_fingerprint'=>$fp],['rule_id'=>$rule->id,'event_key'=>'INVOICE_REVIEW','severity'=>'WARNING','title'=>'Invoice requires review','message'=>'Supplier invoice '.$row->supplier_invoice_number.' is awaiting reconciliation review.','source_type'=>'purchase_invoice_matches','source_id'=>$row->id])->wasRecentlyCreated?1:0;
   }
  }
  foreach($rules as $rule)$rule->update(['last_evaluated_at'=>now()]);
  return back()->with('success',"Evaluation completed. {$created} new alert(s) created.");
 }
 public function resolveEvent(BusinessAutomationEvent $event)
 {
  if($event->status==='OPEN')$event->update(['status'=>'RESOLVED','resolved_at'=>now()]);
  return back()->with('success','Alert marked as resolved.');
 }
 public function export(): StreamedResponse
 {
  $rows=[
   ['Metric','Value'],
   ['Supplier invoice records',Schema::hasTable('purchase_invoice_matches')?DB::table('purchase_invoice_matches')->count():0],
   ['Supplier invoice total value',Schema::hasTable('purchase_invoice_matches')?DB::table('purchase_invoice_matches')->sum('invoice_total'):0],
   ['Confirmed supplier payments',Schema::hasTable('supplier_invoice_payments')?DB::table('supplier_invoice_payments')->where('status','CONFIRMED')->sum('amount'):0],
   ['Open requisitions',Schema::hasTable('procurement_requisitions')?DB::table('procurement_requisitions')->whereIn('status',['DRAFT','SUBMITTED','UNDER_REVIEW'])->count():0],
   ['Distinct warehouse SKUs',Schema::hasTable('warehouse_stock_balances')?DB::table('warehouse_stock_balances')->distinct('sku')->count('sku'):0],
   ['Internal warehouse quantity',Schema::hasTable('warehouse_stock_balances')?DB::table('warehouse_stock_balances')->sum('quantity'):0],
   ['Transfers in transit',Schema::hasTable('warehouse_transfers')?DB::table('warehouse_transfers')->where('status','IN_TRANSIT')->count():0],
   ['Open automation alerts',BusinessAutomationEvent::where('status','OPEN')->count()],
  ];
  return response()->streamDownload(function()use($rows){$out=fopen('php://output','w');foreach($rows as $row)fputcsv($out,$row);fclose($out);},'executive-snapshot-'.now()->format('Ymd-His').'.csv',['Content-Type'=>'text/csv']);
 }
}
