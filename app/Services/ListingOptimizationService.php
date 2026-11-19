<?php

namespace App\Services;

use App\Models\{Keyword, Listing, ListingOptimizationAudit, ListingVersion};
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Advisory content QA. It does not claim to validate official marketplace category schemas or policies. */
class ListingOptimizationService
{
    public function analyze(ListingVersion $version, array $keywords = []): array
    {
        $title = trim((string) $version->title);
        $bullets = array_values(array_filter(array_map(fn ($v) => trim((string) $v), (array) $version->bullets), fn ($v) => $v !== ''));
        $description = trim((string) $version->description);
        $backend = trim((string) $version->backend_keywords);
        $allText = mb_strtolower($title.' '.implode(' ', $bullets).' '.$description.' '.$backend);
        $issues = [];
        $add = function (string $code, string $severity, string $field, string $message, string $recommendation) use (&$issues): void {
            $issues[] = compact('code', 'severity', 'field', 'message', 'recommendation');
        };

        $titleLength = mb_strlen($title);
        if ($titleLength < 50) $add('TITLE_SHORT', 'WARNING', 'title', "Title contains {$titleLength} characters.", 'Add useful product type, key feature, size or compatibility details without keyword stuffing.');
        if ($titleLength > 200) $add('TITLE_LONG', 'WARNING', 'title', "Title contains {$titleLength} characters.", 'Shorten the title and verify the current marketplace-specific title limit before publishing.');
        if (preg_match('/[!$]{2,}|[★☆✓✔►◆]/u', $title)) $add('TITLE_DECORATION', 'WARNING', 'title', 'Title contains decorative symbols or repeated punctuation.', 'Use plain, readable product wording and confirm allowed characters for the destination marketplace.');
        if (preg_match('/\b( best | #1 | no\.?\s*1 | cheapest )/iu', ' '.$title.' ')) $add('TITLE_SUPERLATIVE', 'WARNING', 'title', 'Title may contain an unverifiable superlative claim.', 'Remove or substantiate comparative claims and check marketplace policy.');

        if (count($bullets) < 5) $add('BULLETS_FEW', 'WARNING', 'bullets', count($bullets).' non-empty bullet points found.', 'Add distinct, factual feature and benefit bullets where the channel supports them.');
        $normalizedBullets = array_map(fn ($v) => preg_replace('/\W+/u', '', mb_strtolower($v)), $bullets);
        if (count($normalizedBullets) !== count(array_unique($normalizedBullets))) $add('BULLETS_DUPLICATE', 'WARNING', 'bullets', 'Two or more bullet points appear identical after normalization.', 'Replace repeated bullets with separate, specific product facts.');
        foreach ($bullets as $i => $bullet) {
            if (mb_strlen($bullet) > 500) { $add('BULLET_LONG_'.($i + 1), 'WARNING', 'bullets', 'Bullet '.($i + 1).' exceeds 500 characters.', 'Shorten this bullet and confirm the marketplace field limit.'); }
        }

        if ($description === '') $add('DESCRIPTION_MISSING', 'WARNING', 'description', 'No product description is saved.', 'Add a clear description covering material, dimensions, use and included items when applicable.');
        if ($description !== '' && mb_strlen($description) < 100) $add('DESCRIPTION_SHORT', 'INFO', 'description', 'Description is relatively brief.', 'Consider adding useful, verifiable product details rather than filler.');

        $tokens = preg_split('/[^\pL\pN]+/u', mb_strtolower($title), -1, PREG_SPLIT_NO_EMPTY);
        $counts = array_count_values($tokens ?: []);
        $repeated = array_keys(array_filter($counts, fn ($count, $word) => $count >= 3 && mb_strlen($word) > 2, ARRAY_FILTER_USE_BOTH));
        if ($repeated) $add('TITLE_KEYWORD_STUFFING', 'WARNING', 'title', 'Title repeats terms: '.implode(', ', array_slice($repeated, 0, 5)).'.', 'Use each important term naturally; remove unnecessary repetition.');

        $backendTokens = preg_split('/[\s,;]+/u', mb_strtolower($backend), -1, PREG_SPLIT_NO_EMPTY);
        $backendUnique = array_values(array_unique($backendTokens ?: []));
        if (count($backendTokens ?: []) !== count($backendUnique)) $add('BACKEND_DUPLICATES', 'INFO', 'backend_keywords', 'Backend search terms contain duplicate tokens.', 'Remove duplicate terms and use relevant additional search phrases if permitted.');
        $titleTokens = array_flip($tokens ?: []);
        $overlap = array_values(array_filter($backendUnique, fn ($word) => isset($titleTokens[$word]) && mb_strlen($word) > 2));
        if ($overlap) $add('BACKEND_TITLE_OVERLAP', 'INFO', 'backend_keywords', 'Backend terms repeat words already present in the title: '.implode(', ', array_slice($overlap, 0, 8)).'.', 'Prioritize relevant terms not already represented in visible content, subject to channel rules.');

        $claimPatterns = [
            'GUARANTEED' => '/\b(guaranteed|risk[- ]free|100% safe)\b/iu',
            'MEDICAL_CLAIM' => '/\b(cures?|treats?|prevents?\s+(?:all\s+)?(?:disease|infection|cancer))\b/iu',
            'ABSOLUTE_CLAIM' => '/\b(100% effective|permanent results|works instantly)\b/iu',
        ];
        foreach ($claimPatterns as $code => $pattern) {
            if (preg_match($pattern, $allText)) $add($code, 'WARNING', 'content', 'Content contains wording that may require evidence or policy review.', 'Verify the claim is lawful, accurate and permitted for this product and marketplace; remove it if unsupported.');
        }

        if ((int) $version->image_count < 1) $add('IMAGE_MISSING', 'BLOCKER', 'images', 'No product images are recorded for this listing version.', 'Add product images and verify the channel image requirements.');
        if (trim((string) $version->title) === '') $add('TITLE_MISSING', 'BLOCKER', 'title', 'A listing title is required.', 'Add an accurate product title.');

        $keywordList = array_values(array_filter($keywords, fn ($word) => trim((string) $word) !== ''));
        if ($keywordList) {
            $missing = array_values(array_filter($keywordList, fn ($word) => mb_stripos($allText, $word) === false));
            if ($missing) $add('KEYWORDS_MISSING', 'INFO', 'keywords', count($missing).' tracked keyword(s) are not present in listing content.', 'Review relevant missing keywords and add only those that accurately describe the product.');
        }

        $blockers = count(array_filter($issues, fn ($i) => $i['severity'] === 'BLOCKER'));
        $warnings = count(array_filter($issues, fn ($i) => $i['severity'] === 'WARNING'));
        $score = max(0, round(100 - ($blockers * 30) - ($warnings * 8) - (count($issues) - $blockers - $warnings) * 2, 1));
        return ['score' => $score, 'status' => $blockers ? 'BLOCKED' : ($warnings ? 'REVIEW' : 'GOOD'), 'issue_count' => count($issues), 'blocker_count' => $blockers, 'warning_count' => $warnings, 'issues' => $issues];
    }

    public function audit(Listing $listing, ?int $userId = null): ListingOptimizationAudit
    {
        return DB::transaction(function () use ($listing, $userId) {
            $listing = Listing::query()->with(['current'])->lockForUpdate()->findOrFail($listing->id);
            $version = $listing->current;
            if (! $version) throw ValidationException::withMessages(['listing' => 'Save a listing version before running optimization checks.']);
            $keywords = Keyword::query()->where('product_id', $listing->product_id)->where('marketplace_id', $listing->marketplace_id)->pluck('keyword')->all();
            $result = $this->analyze($version, $keywords);
            return ListingOptimizationAudit::create([
                'listing_id' => $listing->id, 'listing_version_id' => $version->id,
                'score' => $result['score'], 'status' => $result['status'], 'issue_count' => $result['issue_count'],
                'blocker_count' => $result['blocker_count'], 'warning_count' => $result['warning_count'],
                'results' => $result['issues'], 'created_by' => $userId,
            ]);
        });
    }
}
