<?php
namespace App\Http\Controllers;
use App\Models\PurchaseInvoiceMatch;
use App\Models\SupplierInvoicePayment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
class SupplierPaymentController extends Controller {
    public function index() {
        $invoices = PurchaseInvoiceMatch::with(['purchase.supplier','payments','creditApplications'])
            ->latest('supplier_invoice_date')->paginate(20);
        return view('purchases.supplier-payments', compact('invoices'));
    }
    public function store(Request $request, PurchaseInvoiceMatch $match) {
        $data = $request->validate([
            'amount'=>'required|numeric|min:0.01',
            'payment_date'=>'required|date',
            'payment_method'=>'required|in:BANK_TRANSFER,UPI,CASH,CHEQUE,CARD,OTHER',
            'reference_number'=>'nullable|string|max:120',
            'notes'=>'nullable|string|max:2000',
        ]);
        $paid = (float) $match->payments()->where('status','CONFIRMED')->sum('amount');
        $credited = (float) DB::table('supplier_credit_note_applications')->where('purchase_invoice_match_id',$match->id)->sum('amount');
        $due = max(0, (float)$match->invoice_total - $paid - $credited);
        if ((float)$data['amount'] > $due + 0.0001) {
            return back()->withErrors(['amount'=>'Payment exceeds the remaining invoice balance. Record supplier credits separately.'])->withInput();
        }
        SupplierInvoicePayment::create($data + [
            'purchase_invoice_match_id'=>$match->id, 'status'=>'CONFIRMED', 'created_by'=>$request->user()->id
        ]);
        return back()->with('success','Supplier payment recorded. No bank transfer was initiated.');
    }
}
