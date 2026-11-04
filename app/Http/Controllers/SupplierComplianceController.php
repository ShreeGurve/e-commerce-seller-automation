<?php

namespace App\Http\Controllers;

use App\Models\Supplier;
use App\Models\SupplierDocument;
use Illuminate\Http\Request;

class SupplierComplianceController extends Controller
{
    public function index(Request $request)
    {
        $today = today();
        $query = SupplierDocument::with('supplier:id,name')
            ->whereNotNull('expires_at')
            ->whereIn('status', ['PENDING', 'APPROVED']);

        if ($request->filled('supplier_id')) {
            $query->where('supplier_id', $request->integer('supplier_id'));
        }
        if ($request->filled('state') && in_array($request->state, ['expired', '7', '30', '60'], true)) {
            if ($request->state === 'expired') {
                $query->whereDate('expires_at', '<', $today->toDateString());
            } else {
                $query->whereBetween('expires_at', [$today->toDateString(), $today->copy()->addDays((int) $request->state)->toDateString()]);
            }
        }

        $documents = $query->orderBy('expires_at')->paginate(20)->withQueryString();
        $suppliers = Supplier::orderBy('name')->get(['id', 'name']);
        $counts = [
            'expired' => SupplierDocument::whereIn('status', ['PENDING', 'APPROVED'])->whereNotNull('expires_at')->whereDate('expires_at', '<', $today)->count(),
            'seven' => SupplierDocument::whereIn('status', ['PENDING', 'APPROVED'])->whereNotNull('expires_at')->whereBetween('expires_at', [$today, $today->copy()->addDays(7)])->count(),
            'thirty' => SupplierDocument::whereIn('status', ['PENDING', 'APPROVED'])->whereNotNull('expires_at')->whereBetween('expires_at', [$today, $today->copy()->addDays(30)])->count(),
            'sixty' => SupplierDocument::whereIn('status', ['PENDING', 'APPROVED'])->whereNotNull('expires_at')->whereBetween('expires_at', [$today, $today->copy()->addDays(60)])->count(),
        ];

        return view('suppliers.compliance', compact('documents', 'suppliers', 'counts'));
    }
}
