<?php
namespace App\Http\Controllers;

use App\Models\Supplier;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SupplierController extends Controller
{
    public function index(Request $request)
    {
        $suppliers = Supplier::withCount('purchases')
            ->when($request->q, fn ($q, $s) => $q->where(fn ($w) => $w->where('name', 'like', "%$s%")->orWhere('phone', 'like', "%$s%")->orWhere('gstin', 'like', "%$s%")))
            ->orderBy('name')->paginate(20)->withQueryString();
        return view('suppliers.index', compact('suppliers'));
    }

    public function create() { return view('suppliers.create', ['supplier' => new Supplier(['is_active' => true])]); }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $supplier = Supplier::create($data);
        ActivityLogger::log('supplier.created', $supplier, null, ['name' => $supplier->name]);
        return redirect()->route('suppliers.index')->with('success', 'Supplier added.');
    }

    public function edit(Supplier $supplier) { return view('suppliers.edit', compact('supplier')); }

    public function update(Request $request, Supplier $supplier)
    {
        $data = $this->validated($request, $supplier);
        $supplier->fill($data);
        $dirty = $supplier->getDirty();
        $old = array_intersect_key($supplier->getOriginal(), $dirty);
        $supplier->save();
        if ($dirty) ActivityLogger::log('supplier.updated', $supplier, $old, $dirty);
        return redirect()->route('suppliers.index')->with('success', 'Supplier updated.');
    }

    public function destroy(Supplier $supplier)
    {
        if ($supplier->purchases()->exists()) {
            return back()->with('error', 'Cannot delete: supplier has purchases. Mark it inactive instead.');
        }
        $supplier->delete();
        ActivityLogger::log('supplier.deleted', $supplier, ['name' => $supplier->name]);
        return redirect()->route('suppliers.index')->with('success', 'Supplier deleted.');
    }

    private function validated(Request $request, ?Supplier $s = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('suppliers', 'name')->ignore($s?->id)->whereNull('deleted_at')],
            'contact_person' => 'nullable|string|max:120', 'phone' => 'nullable|string|max:30',
            'email' => 'nullable|email|max:255', 'gstin' => 'nullable|string|max:20',
            'address' => 'nullable|string|max:500', 'city' => 'nullable|string|max:80', 'state' => 'nullable|string|max:80',
            'payment_terms' => 'nullable|string|max:120', 'notes' => 'nullable|string|max:2000',
        ]);
        $data['is_active'] = $request->boolean('is_active');
        return $data;
    }
}
