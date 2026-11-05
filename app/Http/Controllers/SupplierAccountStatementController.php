<?php
namespace App\Http\Controllers;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
class SupplierAccountStatementController extends Controller {
    public function index(Request $request) {
        $query = DB::table('purchase_invoice_matches as im')
            ->join('purchases as p','p.id','=','im.purchase_id')
            ->join('suppliers as s','s.id','=','p.supplier_id')
            ->select('s.id as supplier_id','s.name as supplier_name','p.po_number','im.id as invoice_id',
                'im.supplier_invoice_number','im.supplier_invoice_date','im.invoice_total')
            ->selectSub(DB::table('supplier_invoice_payments as sp')->selectRaw('COALESCE(SUM(sp.amount),0)')
                ->whereColumn('sp.purchase_invoice_match_id','im.id')->where('sp.status','CONFIRMED'),'paid_amount')
            ->selectSub(DB::table('supplier_credit_note_applications as ca')->selectRaw('COALESCE(SUM(ca.amount),0)')
                ->whereColumn('ca.purchase_invoice_match_id','im.id'),'credit_applied')
            ->orderBy('s.name')->orderByDesc('im.supplier_invoice_date');
        if ($request->filled('supplier_id')) $query->where('s.id',$request->integer('supplier_id'));
        $rows = $query->paginate(30)->withQueryString();
        $suppliers = DB::table('suppliers')->select('id','name')->orderBy('name')->get();
        return view('purchases.supplier-account-statements', compact('rows','suppliers'));
    }
}
