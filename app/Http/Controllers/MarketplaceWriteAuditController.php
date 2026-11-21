<?php

namespace App\Http\Controllers;

use App\Services\MarketplaceWriteAuditService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MarketplaceWriteAuditController extends Controller
{
    public function index(Request $request, MarketplaceWriteAuditService $service)
    {
        $filters = $service->normalizeFilters($request->only(['status', 'operation', 'marketplace', 'from', 'to']));

        return view('marketplaces.write-audit', $service->dashboard((int) $request->user()->business_id, $filters));
    }

    public function export(Request $request, MarketplaceWriteAuditService $service): StreamedResponse
    {
        $filters = $service->normalizeFilters($request->only(['status', 'operation', 'marketplace', 'from', 'to']));
        $rows = $service->exportRows((int) $request->user()->business_id, $filters);

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Attempt ID', 'Marketplace', 'Operation', 'SKU', 'Submission Status', 'Verification Status', 'Issue Code', 'Prepared By', 'Approved By', 'Verified By', 'Created At', 'Submitted At', 'Verified At', 'Submission Reference', 'Error']);
            foreach ($rows as $row) {
                $values = [$row->id, $row->marketplace_code, $row->operation, $row->marketplace_sku, $row->status,
                    $row->verification_status, $row->issue_code, $row->prepared_by_name, $row->approved_by_name,
                    $row->verified_by_name, $row->created_at, $row->submitted_at, $row->verified_at,
                    $row->external_submission_id, $row->error_message];
                fputcsv($out, array_map([$this, 'safeCsvValue'], $values));
            }
            fclose($out);
        }, 'marketplace-write-audit-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function safeCsvValue($value): string
    {
        $value = (string) ($value ?? '');

        return preg_match('/^[\s]*[=+@\-]/u', $value) ? "'".$value : $value;
    }
}
