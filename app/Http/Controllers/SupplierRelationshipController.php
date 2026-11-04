<?php

namespace App\Http\Controllers;

use App\Models\Supplier;
use App\Models\SupplierInteraction;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SupplierRelationshipController extends Controller
{
    public function index(Request $request)
    {
        $suppliers = Supplier::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']);
        $query = SupplierInteraction::with(['supplier:id,name', 'creator:id,name'])->latest();

        if ($request->filled('supplier_id')) {
            $query->where('supplier_id', $request->integer('supplier_id'));
        }
        if ($request->filled('type') && in_array($request->type, ['NOTE', 'FOLLOW_UP', 'DOCUMENT', 'ISSUE'], true)) {
            $query->where('type', $request->type);
        }
        if ($request->filled('status') && in_array($request->status, ['OPEN', 'IN_PROGRESS', 'DONE', 'CANCELLED'], true)) {
            $query->where('status', $request->status);
        }

        $interactions = $query->paginate(20)->withQueryString();
        $openCount = SupplierInteraction::whereIn('status', ['OPEN', 'IN_PROGRESS'])->count();
        $overdueCount = SupplierInteraction::whereIn('status', ['OPEN', 'IN_PROGRESS'])->whereDate('due_date', '<', today())->count();
        $documentCount = SupplierInteraction::where('type', 'DOCUMENT')->count();

        return view('suppliers.relationship', compact('suppliers', 'interactions', 'openCount', 'overdueCount', 'documentCount'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'supplier_id' => ['required', 'exists:suppliers,id'],
            'type' => ['required', Rule::in(['NOTE', 'FOLLOW_UP', 'DOCUMENT', 'ISSUE'])],
            'subject' => ['required', 'string', 'max:180'],
            'details' => ['nullable', 'string', 'max:10000'],
            'reference_number' => ['nullable', 'string', 'max:100'],
            'due_date' => ['nullable', 'date'],
        ]);

        $validated['created_by'] = $request->user()->id;
        SupplierInteraction::create($validated);

        return redirect()->route('supplier-relationships.index')->with('success', 'Supplier record added.');
    }

    public function updateStatus(Request $request, SupplierInteraction $interaction)
    {
        $validated = $request->validate(['status' => ['required', Rule::in(['OPEN', 'IN_PROGRESS', 'DONE', 'CANCELLED'])]]);
        $interaction->status = $validated['status'];
        $interaction->completed_at = $validated['status'] === 'DONE' ? now() : null;
        $interaction->save();

        return redirect()->route('supplier-relationships.index')->with('success', 'Supplier record status updated.');
    }
}
