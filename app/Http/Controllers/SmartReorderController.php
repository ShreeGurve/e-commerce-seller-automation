<?php
namespace App\Http\Controllers;

use App\Models\InventoryDemandForecast;
use App\Models\Product;
use App\Models\ProcurementRequisition;
use App\Models\SmartReorderRun;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SmartReorderController extends Controller
{
    private function suggestions()
    {
        $latestIds = InventoryDemandForecast::query()
            ->selectRaw('MAX(id) as id')
            ->groupBy('product_id');

        return InventoryDemandForecast::query()
            ->whereIn('id', $latestIds)
            ->with(['product.currentCosts','product.inventory'])
            ->where('suggested_reorder_qty','>',0)
            ->get()
            ->filter(fn($f) => $f->product && $f->product->status === 'active')
            ->values();
    }

    public function index()
    {
        $suggestions = $this->suggestions();
        $runs = SmartReorderRun::with('requisition')->latest()->limit(20)->get();
        return view('reorders.smart', [
            'suggestions'=>$suggestions,
            'runs'=>$runs,
            'suggestedUnits'=>$suggestions->sum('suggested_reorder_qty'),
            'estimatedValue'=>$suggestions->sum(fn($f) => (float)$f->suggested_reorder_qty * (float)$f->product->currentCost('COGS')),
        ]);
    }

    public function generate(Request $request)
    {
        $suggestions = $this->suggestions();
        abort_if($suggestions->isEmpty(), 422, 'No positive forecast reorder suggestions are available. Generate a fresh inventory forecast first.');

        $activeStatuses = ['DRAFT','SUBMITTED','UNDER_REVIEW','APPROVED','AWARDED'];
        $activeSkus = DB::table('procurement_requisition_items')
            ->join('procurement_requisitions','procurement_requisitions.id','=','procurement_requisition_items.requisition_id')
            ->whereIn('procurement_requisitions.status',$activeStatuses)
            ->whereNotNull('procurement_requisition_items.sku')
            ->pluck('procurement_requisition_items.sku')->map(fn($v)=>mb_strtolower(trim($v)))->all();
        $activeSkus = array_flip($activeSkus);

        $lines = $suggestions->filter(function($f) use($activeSkus) {
            $sku = mb_strtolower(trim((string)$f->product->sku));
            return $sku !== '' && !isset($activeSkus[$sku]);
        })->values();

        abort_if($lines->isEmpty(), 422, 'All forecast suggestions already appear on open procurement requisitions.');

        $run = DB::transaction(function() use($request,$suggestions,$lines) {
            $req = ProcurementRequisition::create([
                'request_number'=>'PR-'.now()->format('ymd').'-'.Str::upper(Str::random(5)),
                'title'=>'Smart reorder recommendation — '.now()->format('d M Y'),
                'purpose'=>'Generated from the latest saved inventory demand forecast. Review quantities and supplier quotes before any purchase order is created.',
                'required_by'=>now()->addDays((int)$lines->max('lead_time_days'))->toDateString(),
                'priority'=>$lines->contains(fn($f)=>(int)$f->stock_position <= 0) ? 'URGENT' : 'HIGH',
                'status'=>'SUBMITTED',
                'requested_by'=>$request->user()->id,
            ]);
            foreach($lines as $forecast) {
                $product=$forecast->product;
                $req->items()->create([
                    'item_name'=>$product->name,
                    'sku'=>$product->sku,
                    'quantity'=>(float)$forecast->suggested_reorder_qty,
                    'unit'=>'pcs',
                    'estimated_unit_cost'=>(float)$product->currentCost('COGS'),
                    'specifications'=>'Forecast basis: '.$forecast->lookback_days.' day lookback; daily demand '.$forecast->average_daily_demand.'; stock position '.$forecast->stock_position.'.',
                ]);
            }
            return SmartReorderRun::create([
                'run_number'=>'SR-'.now()->format('ymdHis').'-'.Str::upper(Str::random(3)),
                'products_considered'=>$suggestions->count(),
                'items_added'=>$lines->count(),
                'requisition_id'=>$req->id,
                'generated_by'=>$request->user()->id,
                'notes'=>'Internal requisition only; no supplier order was placed.',
            ]);
        });

        return redirect()->route('reorders.smart.index')->with('success','Smart reorder generated '.$run->items_added.' line(s) in requisition '.$run->requisition?->request_number.'. Review it in Procurement.');
    }
}
