<?php

namespace App\Http\Controllers;

use App\Models\Listing;
use App\Models\Marketplace;
use App\Services\ListingReadinessService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ListingReadinessController extends Controller
{
    public function index(Request $request, ListingReadinessService $readiness)
    {
        $listings = Listing::query()
            ->with(['product:id,sku,name,brand_id,category_id', 'product.brand:id,name', 'product.category:id,name',
                'marketplace:id,name,code', 'current:id,listing_id,version_no,title,score,image_count'])
            ->when($request->filled('marketplace_id'), fn ($q) => $q->where('marketplace_id', $request->integer('marketplace_id')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')->toString()))
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $request->string('q')->toString()) . '%';
                $q->whereHas('product', fn ($p) => $p->where('sku', 'like', $term)->orWhere('name', 'like', $term));
            })
            ->latest('updated_at')->paginate(25)->withQueryString();

        $listings->setCollection($listings->getCollection()->map(function (Listing $listing) use ($readiness) {
            $listing->setAttribute('preflight', $readiness->evaluate($listing));
            return $listing;
        }));

        return view('listing-readiness.index', [
            'listings' => $listings,
            'marketplaces' => Marketplace::active()->orderBy('name')->get(['id', 'name']),
            'filters' => $request->only(['marketplace_id', 'status', 'q']),
        ]);
    }

    public function export(Request $request, ListingReadinessService $readiness): StreamedResponse
    {
        $query = Listing::query()->with(['product.brand:id,name', 'product.category:id,name', 'marketplace:id,name,code', 'current'])
            ->when($request->filled('marketplace_id'), fn ($q) => $q->where('marketplace_id', $request->integer('marketplace_id')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')->toString()))
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $request->string('q')->toString()) . '%';
                $q->whereHas('product', fn ($p) => $p->where('sku', 'like', $term)->orWhere('name', 'like', $term));
            })->orderBy('marketplace_id')->orderBy('product_id');

        $filename = 'marketplace-listing-preflight-' . now()->format('Ymd-His') . '.csv';
        return response()->streamDownload(function () use ($query, $readiness) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Marketplace', 'Marketplace Code', 'Seller SKU', 'Product', 'Listing Status', 'External Listing ID',
                'Current Version', 'Title', 'Bullets', 'Description', 'Image Count', 'Brand', 'Category', 'Quality Score',
                'Preflight Status', 'Blockers', 'Warnings', 'Failed Checks']);
            $query->chunk(200, function ($batch) use ($out, $readiness) {
                foreach ($batch as $listing) {
                    $result = $readiness->evaluate($listing);
                    $failed = collect($result['checks'])->where('passed', false)
                        ->map(fn ($check) => $check['label'] . ': ' . $check['detail'])->implode(' | ');
                    $version = $result['version'];
                    fputcsv($out, [$listing->marketplace?->name, $listing->marketplace?->code, $listing->product?->sku,
                        $listing->product?->name, $listing->status, $listing->external_listing_id, $version?->version_no,
                        $version?->title, implode(' | ', $version?->bullets ?? []), $version?->description, $version?->image_count,
                        $listing->brand ?: $listing->product?->brand?->name,
                        $listing->category_path ?: $listing->product?->category?->name, $version?->score,
                        $result['status'], $result['blockers'], $result['warnings'], $failed]);
                }
            });
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
