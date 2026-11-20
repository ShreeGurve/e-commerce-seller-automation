<?php

namespace App\Http\Controllers;

use App\Services\MarketplaceWriteEvidenceService;
use Illuminate\Http\Request;

class MarketplaceWriteEvidenceController extends Controller
{
    public function __construct(private MarketplaceWriteEvidenceService $service) {}

    public function index(Request $request)
    {
        $filters = $request->validate([
            'type' => ['nullable', 'in:PAYLOAD,RESPONSE,SCREENSHOT,APPROVAL,INVOICE,OTHER'],
            'subject_type' => ['nullable', 'in:GENERAL,ATTEMPT,INCIDENT,RECOVERY'],
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        return view('marketplaces.write-evidence', [
            'evidence' => $this->service->listFor($request->user(), $filters),
            'filters' => $filters,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'subject_type' => ['required', 'in:GENERAL,ATTEMPT,INCIDENT,RECOVERY'],
            'subject_id' => ['nullable', 'integer', 'min:1'],
            'evidence_type' => ['required', 'in:PAYLOAD,RESPONSE,SCREENSHOT,APPROVAL,INVOICE,OTHER'],
            'title' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:5000'],
            'evidence_url' => ['nullable', 'url', 'max:2000'],
            'reference_code' => ['nullable', 'string', 'max:120'],
        ]);

        $id = $this->service->create($request->user(), $data);

        return redirect()->route('marketplaces.writes.evidence.index')
            ->with('success', "Evidence record #{$id} created.");
    }

    public function show(Request $request, int $evidence)
    {
        return view('marketplaces.write-evidence-show', [
            'item' => $this->service->findFor($request->user(), $evidence),
        ]);
    }
}
