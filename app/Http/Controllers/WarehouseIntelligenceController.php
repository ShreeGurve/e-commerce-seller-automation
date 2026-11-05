<?php
namespace App\Http\Controllers;
use App\Models\WarehouseLocation;
use App\Models\WarehouseStockBalance;
use App\Models\WarehouseTransfer;
use App\Models\WarehouseStockCount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
class WarehouseIntelligenceController extends Controller {
 public function index(){
  $locations=WarehouseLocation::withCount('balances')->with(['balances'=>fn($q)=>$q->orderBy('sku')])->orderBy('name')->get();
  $transfers=WarehouseTransfer::with(['source','destination','items'])->latest()->limit(30)->get();
  $counts=WarehouseStockCount::with(['location','items'])->latest()->limit(20)->get();
  return view('inventory.warehouse-intelligence',compact('locations','transfers','counts'));
 }
 public function storeLocation(Request $r){
  $d=$r->validate(['code'=>'required|string|max:40|unique:warehouse_locations,code','name'=>'required|string|max:150',
   'location_type'=>'required|in:WAREHOUSE,STORE,3PL,TRANSIT,OTHER','address'=>'nullable|string|max:500',
   'contact_person'=>'nullable|string|max:120','contact_phone'=>'nullable|string|max:40','notes'=>'nullable|string|max:2000']);
  WarehouseLocation::create($d+['is_active'=>true]);
  return back()->with('success','Warehouse/location created.');
 }
 public function updateLocation(Request $r,WarehouseLocation $location){
  $d=$r->validate(['name'=>'required|string|max:150','location_type'=>'required|in:WAREHOUSE,STORE,3PL,TRANSIT,OTHER',
   'address'=>'nullable|string|max:500','contact_person'=>'nullable|string|max:120','contact_phone'=>'nullable|string|max:40',
   'notes'=>'nullable|string|max:2000','is_active'=>'required|boolean']);
  $location->update($d);return back()->with('success','Location updated.');
 }
 public function storeBalance(Request $r,WarehouseLocation $location){
  $d=$r->validate(['sku'=>'required|string|max:100','item_name'=>'required|string|max:180','quantity'=>'required|numeric|min:0',
   'unit'=>'required|string|max:30']);
  DB::transaction(function()use($location,$d){
   $row=WarehouseStockBalance::where('warehouse_location_id',$location->id)->where('sku',$d['sku'])->lockForUpdate()->first();
   if($row)$row->update(['item_name'=>$d['item_name'],'quantity'=>$d['quantity'],'unit'=>$d['unit']]);
   else WarehouseStockBalance::create($d+['warehouse_location_id'=>$location->id]);
  });
  return back()->with('success','Opening/manual stock balance saved. This does not change marketplace inventory.');
 }
 public function storeTransfer(Request $r){
  $d=$r->validate(['source_location_id'=>'required|exists:warehouse_locations,id','destination_location_id'=>'required|different:source_location_id|exists:warehouse_locations,id',
   'transfer_date'=>'required|date','notes'=>'nullable|string|max:2000','items'=>'required|array|min:1',
   'items.*.sku'=>'required|string|max:100','items.*.item_name'=>'required|string|max:180','items.*.quantity'=>'required|numeric|min:0.001','items.*.unit'=>'required|string|max:30']);
  $source=WarehouseLocation::findOrFail($d['source_location_id']);$dest=WarehouseLocation::findOrFail($d['destination_location_id']);
  abort_unless($source->is_active&&$dest->is_active,422,'Both locations must be active.');
  DB::transaction(function()use($r,$d){
   $transfer=WarehouseTransfer::create(['transfer_number'=>'WT-'.now()->format('ymd').'-'.Str::upper(Str::random(5)),
    'source_location_id'=>$d['source_location_id'],'destination_location_id'=>$d['destination_location_id'],
    'transfer_date'=>$d['transfer_date'],'status'=>'DRAFT','notes'=>$d['notes']??null,'created_by'=>$r->user()->id]);
   foreach($d['items'] as $item)$transfer->items()->create($item);
  });
  return back()->with('success','Transfer draft created. Dispatch it when stock is ready to leave the source.');
 }
 public function dispatchTransfer(Request $r,WarehouseTransfer $transfer){
  DB::transaction(function()use($r,$transfer){
   $t=WarehouseTransfer::with('items')->lockForUpdate()->findOrFail($transfer->id);
   abort_unless($t->status==='DRAFT',422,'Only draft transfers can be dispatched.');
   foreach($t->items as $item){
    $balance=WarehouseStockBalance::where('warehouse_location_id',$t->source_location_id)->where('sku',$item->sku)->lockForUpdate()->first();
    abort_if(!$balance||(float)$balance->quantity+0.0001<(float)$item->quantity,422,"Insufficient source stock for SKU {$item->sku}.");
   }
   foreach($t->items as $item){
    $balance=WarehouseStockBalance::where('warehouse_location_id',$t->source_location_id)->where('sku',$item->sku)->lockForUpdate()->first();
    $balance->decrement('quantity',(float)$item->quantity);
   }
   $t->update(['status'=>'IN_TRANSIT','dispatched_by'=>$r->user()->id,'dispatched_at'=>now()]);
  });
  return back()->with('success','Transfer dispatched. Source stock reduced; destination stock will increase on receipt.');
 }
 public function receiveTransfer(Request $r,WarehouseTransfer $transfer){
  DB::transaction(function()use($r,$transfer){
   $t=WarehouseTransfer::with('items')->lockForUpdate()->findOrFail($transfer->id);
   abort_unless($t->status==='IN_TRANSIT',422,'Only in-transit transfers can be received.');
   foreach($t->items as $item){
    $balance=WarehouseStockBalance::where('warehouse_location_id',$t->destination_location_id)->where('sku',$item->sku)->lockForUpdate()->first();
    if($balance)$balance->increment('quantity',(float)$item->quantity);
    else WarehouseStockBalance::create(['warehouse_location_id'=>$t->destination_location_id,'sku'=>$item->sku,'item_name'=>$item->item_name,'quantity'=>$item->quantity,'unit'=>$item->unit]);
   }
   $t->update(['status'=>'RECEIVED','received_by'=>$r->user()->id,'received_at'=>now()]);
  });
  return back()->with('success','Transfer received. Destination stock updated.');
 }
 public function storeCount(Request $r){
  $d=$r->validate(['warehouse_location_id'=>'required|exists:warehouse_locations,id','count_date'=>'required|date','notes'=>'nullable|string|max:2000',
   'items'=>'required|array|min:1','items.*.sku'=>'required|string|max:100','items.*.item_name'=>'required|string|max:180',
   'items.*.counted_quantity'=>'required|numeric|min:0','items.*.unit'=>'required|string|max:30','items.*.notes'=>'nullable|string|max:1000']);
  DB::transaction(function()use($r,$d){
   $count=WarehouseStockCount::create(['count_number'=>'SC-'.now()->format('ymd').'-'.Str::upper(Str::random(5)),
    'warehouse_location_id'=>$d['warehouse_location_id'],'count_date'=>$d['count_date'],'status'=>'DRAFT','notes'=>$d['notes']??null,'created_by'=>$r->user()->id]);
   foreach($d['items'] as $line){
    $system=(float)(WarehouseStockBalance::where('warehouse_location_id',$d['warehouse_location_id'])->where('sku',$line['sku'])->value('quantity')??0);
    $count->items()->create($line+['system_quantity'=>$system,'variance_quantity'=>round((float)$line['counted_quantity']-$system,3)]);
   }
  });
  return back()->with('success','Stock count saved as draft. Review variances before finalizing.');
 }
 public function finalizeCount(Request $r,WarehouseStockCount $count){
  DB::transaction(function()use($r,$count){
   $c=WarehouseStockCount::with('items')->lockForUpdate()->findOrFail($count->id);
   abort_unless($c->status==='DRAFT',422,'Only draft counts can be finalized.');
   foreach($c->items as $line){
    $balance=WarehouseStockBalance::where('warehouse_location_id',$c->warehouse_location_id)->where('sku',$line->sku)->lockForUpdate()->first();
    if($balance)$balance->update(['item_name'=>$line->item_name,'quantity'=>$line->counted_quantity,'unit'=>$line->unit]);
    else WarehouseStockBalance::create(['warehouse_location_id'=>$c->warehouse_location_id,'sku'=>$line->sku,'item_name'=>$line->item_name,'quantity'=>$line->counted_quantity,'unit'=>$line->unit]);
   }
   $c->update(['status'=>'FINALIZED','finalized_by'=>$r->user()->id,'finalized_at'=>now()]);
  });
  return back()->with('success','Stock count finalized and counted quantities applied to internal warehouse balances.');
 }
}
