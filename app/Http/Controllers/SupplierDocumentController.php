<?php

namespace App\Http\Controllers;

use App\Models\Supplier;
use App\Models\SupplierDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class SupplierDocumentController extends Controller
{
    private const TYPES = ['GST_CERTIFICATE', 'PAN_CARD', 'BUSINESS_LICENSE', 'BANK_PROOF', 'ADDRESS_PROOF', 'AGREEMENT', 'QUALITY_CERTIFICATE', 'OTHER'];

    public function index(Request $request)
    {
        $suppliers = Supplier::query()->orderBy('name')->get(['id', 'name']);
        $query = SupplierDocument::with(['supplier:id,name', 'uploader:id,name', 'verifier:id,name'])->latest();
        if ($request->filled('supplier_id')) $query->where('supplier_id', $request->integer('supplier_id'));
        if ($request->filled('status') && in_array($request->status, ['PENDING', 'APPROVED', 'REJECTED'], true)) $query->where('status', $request->status);
        if ($request->filled('type') && in_array($request->type, self::TYPES, true)) $query->where('document_type', $request->type);
        if ($request->filled('q')) $query->where(function ($q) use ($request) { $q->where('title', 'like', '%'.$request->q.'%')->orWhere('reference_number', 'like', '%'.$request->q.'%')->orWhere('original_filename', 'like', '%'.$request->q.'%'); });

        $documents = $query->paginate(15)->withQueryString();
        $totalCount = SupplierDocument::count();
        $pendingCount = SupplierDocument::where('status', 'PENDING')->count();
        $expiredCount = SupplierDocument::whereNotNull('expires_at')->whereDate('expires_at', '<', today())->count();
        $expiringCount = SupplierDocument::whereNotNull('expires_at')->whereBetween('expires_at', [today(), today()->addDays(30)])->count();
        return view('suppliers.documents', compact('suppliers', 'documents', 'totalCount', 'pendingCount', 'expiredCount', 'expiringCount'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'supplier_id' => ['required', 'exists:suppliers,id'],
            'document_type' => ['required', Rule::in(self::TYPES)],
            'title' => ['required', 'string', 'max:180'],
            'file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240'],
            'reference_number' => ['nullable', 'string', 'max:120'],
            'issued_at' => ['nullable', 'date', 'before_or_equal:today'],
            'expires_at' => ['nullable', 'date', 'after_or_equal:issued_at'],
        ]);
        $file = $request->file('file');
        $path = $file->store('supplier-documents/'.date('Y/m'), 'local');
        SupplierDocument::create([
            'supplier_id' => $validated['supplier_id'], 'document_type' => $validated['document_type'],
            'title' => $validated['title'], 'original_filename' => $file->getClientOriginalName(),
            'disk' => 'local', 'stored_path' => $path, 'mime_type' => $file->getMimeType(),
            'file_size' => $file->getSize(), 'reference_number' => $validated['reference_number'] ?? null,
            'issued_at' => $validated['issued_at'] ?? null, 'expires_at' => $validated['expires_at'] ?? null,
            'status' => 'PENDING', 'uploaded_by' => $request->user()->id,
        ]);
        return redirect()->route('supplier-documents.index')->with('success', 'Supplier document uploaded securely and submitted for review.');
    }

    public function download(SupplierDocument $document)
    {
        $disk = Storage::disk($document->disk);
        abort_unless($disk->exists($document->stored_path), 404);
        return $disk->download($document->stored_path, $document->original_filename);
    }

    public function review(Request $request, SupplierDocument $document)
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(['APPROVED', 'REJECTED', 'PENDING'])],
            'review_notes' => ['nullable', 'string', 'max:5000'],
        ]);
        $document->status = $validated['status'];
        $document->review_notes = $validated['review_notes'] ?? null;
        $document->verified_by = $request->user()->id;
        $document->verified_at = now();
        $document->save();
        return redirect()->route('supplier-documents.index')->with('success', 'Supplier document review saved.');
    }
}
