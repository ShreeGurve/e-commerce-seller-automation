<?php

namespace App\Services;

use App\Models\Listing;
use App\Models\ListingVersion;

class ListingReadinessService
{
    /** Internal content preflight only; not official marketplace schema validation. */
    public function evaluate(Listing $listing): array
    {
        $listing->loadMissing(['product', 'product.brand', 'product.category', 'marketplace', 'current']);
        /** @var ListingVersion|null $version */
        $version = $listing->current;
        $checks = [];
        $add = function (string $label, string $severity, bool $passed, string $detail) use (&$checks): void {
            $checks[] = compact('label', 'severity', 'passed', 'detail');
        };

        $add('Current listing version', 'BLOCKER', (bool) $version,
            $version ? 'A saved listing version is available.' : 'Create and save listing content first.');
        $title = trim((string) ($version?->title ?? ''));
        $add('Product title', 'BLOCKER', $title !== '',
            $title !== '' ? mb_strlen($title) . ' characters saved.' : 'A non-empty title is required.');
        $bullets = array_values(array_filter((array) ($version?->bullets ?? []), fn ($v) => trim((string) $v) !== ''));
        $add('Bullet points', 'WARNING', count($bullets) >= 5,
            count($bullets) . ' non-empty bullet point(s); aim for five or more.');
        $description = trim((string) ($version?->description ?? ''));
        $add('Description', 'WARNING', mb_strlen($description) >= 100,
            mb_strlen($description) . ' characters; add a fuller description where supported.');
        $imageCount = (int) ($version?->image_count ?? 0);
        $add('Product images', 'BLOCKER', $imageCount >= 1,
            $imageCount . ' image(s) recorded; add at least one product image before publishing.');
        $brand = trim((string) ($listing->brand ?: ($listing->product?->brand?->name ?? '')));
        $add('Brand', 'WARNING', $brand !== '', $brand !== '' ? 'Brand information is present.' : 'Brand is not recorded on this listing.');
        $category = trim((string) ($listing->category_path ?: ($listing->product?->category?->name ?? '')));
        $add('Category', 'WARNING', $category !== '', $category !== '' ? 'A category path is recorded.' : 'Choose and record the marketplace category path.');
        $sku = trim((string) ($listing->product?->sku ?? ''));
        $add('Seller SKU', 'BLOCKER', $sku !== '', $sku !== '' ? 'Seller SKU: ' . $sku : 'The linked product has no seller SKU.');
        $score = (float) ($version?->score ?? 0);
        $add('Listing quality score', 'WARNING', $score >= 70,
            number_format($score, 1) . '/100; aim for at least 70 before manual publishing.');

        $blockers = count(array_filter($checks, fn ($c) => $c['severity'] === 'BLOCKER' && ! $c['passed']));
        $warnings = count(array_filter($checks, fn ($c) => $c['severity'] === 'WARNING' && ! $c['passed']));
        return [
            'status' => $blockers > 0 ? 'BLOCKED' : ($warnings > 0 ? 'READY_WITH_WARNINGS' : 'READY'),
            'blockers' => $blockers, 'warnings' => $warnings, 'checks' => $checks, 'version' => $version,
        ];
    }
}
