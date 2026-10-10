<?php
namespace App\Http\Controllers;

use App\Models\{Expense, ExpenseCategory, Marketplace, Product};
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ExpenseController extends Controller
{
    public function index(Request $request)
    {
        $from = $request->query('from', now()->startOfMonth()->toDateString()); $to = $request->query('to', now()->toDateString());
        $q = Expense::query()->whereBetween('expense_date', [$from, $to])
            ->when($request->category_id, fn ($q, $v) => $q->where('expense_category_id', $v))
            ->when($request->marketplace_id, fn ($q, $v) => $q->where('marketplace_id', $v))
            ->when($request->q, fn ($q, $s) => $q->where(fn ($w) => $w->where('notes', 'like', "%$s%")->orWhere('invoice_number', 'like', "%$s%")));
        return view('expenses.index', [
            'expenses' => (clone $q)->with(['category', 'marketplace', 'product'])->latest('expense_date')->latest('id')->paginate(25)->withQueryString(),
            'total' => (float) (clone $q)->sum('amount'),
            'byCategory' => (clone $q)->selectRaw('expense_category_id, SUM(amount) total')->groupBy('expense_category_id')->orderByDesc('total')->pluck('total', 'expense_category_id'),
            'categories' => ExpenseCategory::orderBy('name')->get(), 'marketplaces' => Marketplace::active()->get(), 'from' => $from, 'to' => $to,
        ]);
    }

    public function create() { return view('expenses.create', $this->form(new Expense(['expense_date' => now()]))); }

    public function store(Request $request)
    {
        $d = $this->validated($request);
        if ($request->hasFile('attachment')) { $f = $request->file('attachment'); $d['attachment_path'] = $f->store('expenses/' . now()->format('Y-m'), 'local'); $d['attachment_name'] = $f->getClientOriginalName(); }
        $e = Expense::create($d + ['created_by' => $request->user()->id]);
        ActivityLogger::log('expense.created', $e, null, ['amount' => (string) $e->amount, 'category' => $e->category->name]);
        return redirect()->route('expenses.index')->with('success', 'Expense saved.');
    }

    public function edit(Expense $expense) { return view('expenses.edit', $this->form($expense)); }

    public function update(Request $request, Expense $expense)
    {
        $d = $this->validated($request);
        if ($request->hasFile('attachment')) {
            $f = $request->file('attachment');
            $d['attachment_path'] = $f->store('expenses/' . now()->format('Y-m'), 'local'); $d['attachment_name'] = $f->getClientOriginalName();
            if ($expense->attachment_path) Storage::disk('local')->delete($expense->attachment_path);
        }
        $expense->fill($d);
        $dirty = $expense->getDirty(); $old = array_intersect_key($expense->getOriginal(), $dirty);
        $expense->save();
        if ($dirty) ActivityLogger::log('expense.updated', $expense, $old, $dirty);
        return redirect()->route('expenses.index')->with('success', 'Expense updated.');
    }

    public function destroy(Expense $expense)
    {
        $expense->delete();   // soft delete: financial history stays recoverable and in the audit log
        ActivityLogger::log('expense.deleted', $expense, ['amount' => (string) $expense->amount, 'date' => $expense->expense_date->toDateString()]);
        return redirect()->route('expenses.index')->with('success', 'Expense deleted.');
    }

    public function attachment(Expense $expense)
    {
        abort_unless($expense->attachment_path && Storage::disk('local')->exists($expense->attachment_path), 404);
        return Storage::disk('local')->response($expense->attachment_path, $expense->attachment_name);
    }

    public function storeCategory(Request $request)
    {
        $d = $request->validate(['name' => 'required|string|max:80|unique:expense_categories,name']);
        $c = ExpenseCategory::create(['name' => $d['name'], 'is_system' => false]);
        ActivityLogger::log('expense_category.created', $c, null, ['name' => $c->name]);
        return back()->with('success', 'Category added.');
    }

    private function form(Expense $e): array
    {
        return ['expense' => $e, 'categories' => ExpenseCategory::orderBy('name')->get(), 'marketplaces' => Marketplace::active()->get(), 'products' => Product::orderBy('sku')->get(['id', 'sku', 'name'])];
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'expense_category_id' => 'required|exists:expense_categories,id', 'expense_date' => 'required|date|before_or_equal:today',
            'amount' => 'required|numeric|gt:0|max:9999999999', 'marketplace_id' => 'nullable|exists:marketplaces,id', 'product_id' => 'nullable|exists:products,id',
            'invoice_number' => 'nullable|string|max:60', 'notes' => 'nullable|string|max:2000',
            'attachment' => 'nullable|file|mimes:pdf,jpg,jpeg,png,webp|max:10240',
        ]);
    }
}
