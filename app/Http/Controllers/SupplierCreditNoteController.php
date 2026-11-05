<?php
namespace App\Http\Controllers;
use App\Models\Supplier;
use App\Models\SupplierCreditNote;
use App\Models\SupplierCreditNoteApplication;
use App\Models\PurchaseInvoiceMatch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
class SupplierCreditNoteController extends Controller {
    public function index() {
        $credits = SupplierCreditNote::with(['supplier','invoiceMatch.purchase','applications'])->latest('credit_date')->paginate(20);
        $suppliers = Supplier::orderBy('name')->get();
        $invoices = PurchaseInvoiceMatch::with('purchase.supplier')->latest()->get();
        return view('purchases.supplier-credit-notes', compact('credits','suppliers','invoices'));
    }
    public function store(Request $request) {
        $data = $request->validate([
            'supplier_id'=>'required|exists:suppliers,id',
            'purchase_invoice_match_id'=>'nullable|exists:purchase_invoice_matches,id',
            'supplier_return_id'=>'nullable|exists:supplier_returns,id',
            'credit_note_number'=>'required|string|max:100',
            'credit_date'=>'required|date',
            'credit_amount'=>'required|numeric|min:0.01',
            'notes'=>'nullable|string|max:2000',
        ]);
        if (empty($data['purchase_invoice_match_id']) && empty($data['supplier_return_id'])) {
            return back()->withErrors(['supplier_return_id'=>'Link the credit note to an invoice or supplier return.'])->withInput();
        }
        if (!empty($data['purchase_invoice_match_id'])) {
            $match = PurchaseInvoiceMatch::with('purchase')->findOrFail($data['purchase_invoice_match_id']);
            if ((int)$match->purchase->supplier_id !== (int)$data['supplier_id']) {
                return back()->withErrors(['purchase_invoice_match_id'=>'Selected invoice belongs to a different supplier.'])->withInput();
            }
        }
        SupplierCreditNote::create($data + ['status'=>'OPEN','created_by'=>$request->user()->id]);
        return back()->with('success','Supplier credit note recorded.');
    }
    public function apply(Request $request, SupplierCreditNote $credit) {
        $data = $request->validate([
            'purchase_invoice_match_id'=>'required|exists:purchase_invoice_matches,id',
            'amount'=>'required|numeric|min:0.01',
            'applied_date'=>'required|date',
            'notes'=>'nullable|string|max:1000',
        ]);
        $match = PurchaseInvoiceMatch::with('purchase')->findOrFail($data['purchase_invoice_match_id']);
        abort_unless((int)$match->purchase->supplier_id === (int)$credit->supplier_id, 422, 'Supplier mismatch.');
        DB::transaction(function () use ($request,$credit,$match,$data) {
            $applied = (float)$credit->applications()->sum('amount');
            $remainingCredit = (float)$credit->credit_amount - $applied;
            $paid = (float)$match->payments()->where('status','CONFIRMED')->sum('amount');
            $alreadyCredited = (float)DB::table('supplier_credit_note_applications')->where('purchase_invoice_match_id',$match->id)->sum('amount');
            $remainingInvoice = (float)$match->invoice_total - $paid - $alreadyCredited;
            if ((float)$data['amount'] > min($remainingCredit,$remainingInvoice) + 0.0001) {
                abort(422,'Application exceeds the available credit or invoice balance.');
            }
            SupplierCreditNoteApplication::create($data + [
                'supplier_credit_note_id'=>$credit->id,'created_by'=>$request->user()->id
            ]);
            $newApplied = $applied + (float)$data['amount'];
            $credit->update(['status'=>$newApplied >= (float)$credit->credit_amount ? 'FULLY_APPLIED' : 'PARTIALLY_APPLIED']);
        });
        return back()->with('success','Credit applied to invoice balance.');
    }
}
