<?php
namespace App\Http\Controllers;

use App\Models\BusinessKpiTarget;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class BusinessPerformanceController extends Controller
{
    private const METRICS = [
        'sales_value'=>['label'=>'Order sales value','unit'=>'currency','direction'=>'higher'],
        'orders'=>['label'=>'Non-test orders','unit'=>'number','direction'=>'higher'],
        'fulfilled_orders'=>['label'=>'Delivered / closed orders','unit'=>'number','direction'=>'higher'],
        'approved_requisitions'=>['label'=>'Approved / awarded requisitions','unit'=>'number','direction'=>'higher'],
        'supplier_payments'=>['label'=>'Confirmed supplier payments','unit'=>'currency','direction'=>'higher'],
    ];

    private function actuals(string $start,string $end): array
    {
        $orders=Schema::hasTable('orders') ? DB::table('orders')->whereBetween('order_date',[$start.' 00:00:00',$end.' 23:59:59'])->where('source','<>','TEST')->whereNull('deleted_at') : null;
        $orderStatuses=['CONFIRMED','PACKED','SHIPPED','DELIVERED','CLOSED'];
        $sales=0; $count=0; $fulfilled=0;
        if($orders){
            $sales=(float)(clone $orders)->whereIn('status',$orderStatuses)->sum('total_amount');
            $count=(int)(clone $orders)->whereIn('status',$orderStatuses)->count();
            $fulfilled=(int)(clone $orders)->whereIn('status',['DELIVERED','CLOSED'])->count();
        }
        $requisitions=Schema::hasTable('procurement_requisitions') ? (int)DB::table('procurement_requisitions')->whereBetween('approved_at',[$start.' 00:00:00',$end.' 23:59:59'])->whereIn('status',['APPROVED','AWARDED'])->count() : 0;
        $payments=Schema::hasTable('supplier_invoice_payments') ? (float)DB::table('supplier_invoice_payments')->whereBetween('payment_date',[$start,$end])->where('status','CONFIRMED')->sum('amount') : 0;
        return ['sales_value'=>$sales,'orders'=>$count,'fulfilled_orders'=>$fulfilled,'approved_requisitions'=>$requisitions,'supplier_payments'=>$payments];
    }

    public function index(Request $request)
    {
        $month=$request->query('month',now()->format('Y-m'));
        if(!preg_match('/^\d{4}-\d{2}$/',$month)) $month=now()->format('Y-m');
        $start=$month.'-01'; $end=date('Y-m-t',strtotime($start));
        $actuals=$this->actuals($start,$end);
        $targets=BusinessKpiTarget::whereDate('target_month',$start)->get()->keyBy('metric_key');
        $rows=[];
        foreach(self::METRICS as $key=>$meta){
            $target=(float)($targets[$key]->target_value??0); $actual=(float)$actuals[$key];
            $rows[]=(object)['key'=>$key,'label'=>$meta['label'],'unit'=>$meta['unit'],'actual'=>$actual,'target'=>$target,'attainment'=>$target>0?round(($actual/$target)*100,1):null,'notes'=>$targets[$key]->notes??null];
        }
        return view('business-performance.index',compact('month','rows'));
    }

    public function storeTarget(Request $request)
    {
        $data=$request->validate([
            'target_month'=>'required|date_format:Y-m',
            'metric_key'=>'required|in:sales_value,orders,fulfilled_orders,approved_requisitions,supplier_payments',
            'target_value'=>'required|numeric|min:0|max:99999999999999',
            'notes'=>'nullable|string|max:1000',
        ]);
        $start=$data['target_month'].'-01';
        $unit=in_array($data['metric_key'],['sales_value','supplier_payments'],true)?'currency':'number';
        BusinessKpiTarget::updateOrCreate(
            ['target_month'=>$start,'metric_key'=>$data['metric_key']],
            ['target_value'=>$data['target_value'],'unit'=>$unit,'notes'=>$data['notes']??null,'created_by'=>$request->user()->id]
        );
        return redirect()->route('business-performance.index',['month'=>$data['target_month']])->with('success','KPI target saved.');
    }
}
