<?php

namespace App\Http\Controllers;

use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Pagination\LengthAwarePaginator;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SupplierRiskController extends Controller
{
    private function scoreSuppliers(Request $request)
    {
        $query = Supplier::query()->with([
            'purchases:id,supplier_id,status,expected_delivery_date,received_at,total_amount',
            'reviews:id,supplier_id,delivery_rating,quality_rating,pricing_rating,reviewed_at',
        ]);

        if ($request->filled('search')) {
            $term = trim((string) $request->input('search'));
            $query->where(function ($q) use ($term) {
                $q->where('name', 'like', "%{$term}%")
                    ->orWhere('contact_person', 'like', "%{$term}%")
                    ->orWhere('email', 'like', "%{$term}%");
            });
        }

        $suppliers = $query->orderBy('name')->get();
        $hasDocuments = DB::getSchemaBuilder()->hasTable('supplier_documents');
        $hasInteractions = DB::getSchemaBuilder()->hasTable('supplier_interactions');

        $documentStats = $hasDocuments
            ? DB::table('supplier_documents')
                ->select('supplier_id',
                    DB::raw("SUM(CASE WHEN status = 'APPROVED' AND (expires_at IS NULL OR expires_at >= CURDATE()) THEN 1 ELSE 0 END) as valid_count"),
                    DB::raw("SUM(CASE WHEN expires_at IS NOT NULL AND expires_at < CURDATE() AND status IN ('PENDING','APPROVED') THEN 1 ELSE 0 END) as expired_count"),
                    DB::raw("COUNT(*) as total_count"))
                ->groupBy('supplier_id')->get()->keyBy('supplier_id')
            : collect();

        $issueStats = $hasInteractions
            ? DB::table('supplier_interactions')
                ->select('supplier_id',
                    DB::raw("SUM(CASE WHEN type = 'ISSUE' AND status IN ('OPEN','IN_PROGRESS') THEN 1 ELSE 0 END) as open_issues"),
                    DB::raw("SUM(CASE WHEN type = 'ISSUE' AND status IN ('OPEN','IN_PROGRESS') AND due_date IS NOT NULL AND due_date < CURDATE() THEN 1 ELSE 0 END) as overdue_issues"))
                ->groupBy('supplier_id')->get()->keyBy('supplier_id')
            : collect();

        return $suppliers->map(function (Supplier $supplier) use ($documentStats, $issueStats) {
            $eligiblePurchases = $supplier->purchases->filter(function ($purchase) {
                return in_array($purchase->status, ['RECEIVED'], true);
            });
            $datedPurchases = $eligiblePurchases->filter(fn ($p) => $p->expected_delivery_date && $p->received_at);
            $onTime = $datedPurchases->filter(fn ($p) => $p->received_at->toDateString() <= $p->expected_delivery_date->toDateString())->count();
            $deliveryRate = $datedPurchases->count() ? ($onTime / $datedPurchases->count()) * 100 : null;

            $reviewRatings = $supplier->reviews->map(function ($review) {
                return (((int) $review->delivery_rating + (int) $review->quality_rating + (int) $review->pricing_rating) / 15) * 100;
            });
            $reviewScore = $reviewRatings->count() ? $reviewRatings->avg() : null;

            $docs = $documentStats->get($supplier->id);
            $validDocs = (int) ($docs->valid_count ?? 0);
            $expiredDocs = (int) ($docs->expired_count ?? 0);
            $totalDocs = (int) ($docs->total_count ?? 0);
            $complianceScore = $totalDocs > 0
                ? max(0, min(100, (($validDocs / $totalDocs) * 100) - ($expiredDocs * 10)))
                : 50;

            $issues = $issueStats->get($supplier->id);
            $openIssues = (int) ($issues->open_issues ?? 0);
            $overdueIssues = (int) ($issues->overdue_issues ?? 0);
            $issueScore = max(0, 100 - ($openIssues * 15) - ($overdueIssues * 10));

            $deliveryScore = $deliveryRate === null ? 50 : $deliveryRate;
            $reviewScore = $reviewScore === null ? 50 : $reviewScore;
            $score = (int) round(($deliveryScore * .35) + ($reviewScore * .35) + ($complianceScore * .20) + ($issueScore * .10));
            $risk = $score >= 80 ? 'LOW' : ($score >= 60 ? 'MEDIUM' : 'HIGH');

            $supplier->setAttribute('risk_score', $score);
            $supplier->setAttribute('risk_level', $risk);
            $supplier->setAttribute('delivery_rate', $deliveryRate === null ? null : round($deliveryRate, 1));
            $supplier->setAttribute('review_score', $reviewScore === null ? null : round($reviewScore, 1));
            $supplier->setAttribute('valid_documents', $validDocs);
            $supplier->setAttribute('expired_documents', $expiredDocs);
            $supplier->setAttribute('open_issues', $openIssues);
            $supplier->setAttribute('overdue_issues', $overdueIssues);
            $supplier->setAttribute('received_purchase_count', $eligiblePurchases->count());
            return $supplier;
        });
    }

    public function index(Request $request)
    {
        $suppliers = $this->scoreSuppliers($request);
        if ($request->filled('risk') && in_array($request->risk, ['LOW', 'MEDIUM', 'HIGH'], true)) {
            $suppliers = $suppliers->where('risk_level', $request->risk)->values();
        }
        $counts = [
            'low' => $suppliers->where('risk_level', 'LOW')->count(),
            'medium' => $suppliers->where('risk_level', 'MEDIUM')->count(),
            'high' => $suppliers->where('risk_level', 'HIGH')->count(),
        ];
        $perPage = 20;
        $page = max(1, (int) $request->input('page', 1));
        $total = $suppliers->count();
        $items = $suppliers->forPage($page, $perPage)->values();
        $suppliers = new LengthAwarePaginator($items, $total, $perPage, $page, [
            'path' => $request->url(),
            'query' => $request->query(),
        ]);
        return view('suppliers.risk', compact('suppliers', 'counts'));
    }

    public function export(Request $request): StreamedResponse
    {
        $suppliers = $this->scoreSuppliers($request);
        if ($request->filled('risk') && in_array($request->risk, ['LOW', 'MEDIUM', 'HIGH'], true)) {
            $suppliers = $suppliers->where('risk_level', $request->risk)->values();
        }

        return response()->streamDownload(function () use ($suppliers) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Supplier', 'Risk Score', 'Risk Level', 'Delivery Rate %', 'Review Score %', 'Valid Documents', 'Expired Documents', 'Open Issues', 'Overdue Issues', 'Received Purchases']);
            foreach ($suppliers as $supplier) {
                fputcsv($out, [$supplier->name, $supplier->risk_score, $supplier->risk_level, $supplier->delivery_rate, $supplier->review_score, $supplier->valid_documents, $supplier->expired_documents, $supplier->open_issues, $supplier->overdue_issues, $supplier->received_purchase_count]);
            }
            fclose($out);
        }, 'supplier-risk-report.csv', ['Content-Type' => 'text/csv']);
    }
}
