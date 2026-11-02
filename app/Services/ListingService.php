<?php
namespace App\Services;

use App\Models\{Listing, ListingVersion, Marketplace, Product, User};
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Listing copy workspace. Every save is a NEW immutable version. Nothing is ever pushed to a marketplace from here. */
class ListingService
{
    public function __construct(private ListingScoreService $scorer) {}

    public static function normalize(array $d): array
    {
        $clean = fn ($v) => ($v = trim((string) $v)) === '' ? null : $v;
        return ['title' => trim((string) ($d['title'] ?? '')), 'bullets' => array_values(array_filter(array_map(fn ($b) => trim((string) $b), (array) ($d['bullets'] ?? [])), fn ($b) => $b !== '')),
            'description' => $clean($d['description'] ?? null), 'backend_keywords' => $clean($d['backend_keywords'] ?? null), 'special_features' => $clean($d['special_features'] ?? null),
            'aplus_content' => $clean($d['aplus_content'] ?? null), 'image_count' => max((int) ($d['image_count'] ?? 0), 0), 'has_video' => (bool) ($d['has_video'] ?? false)];
    }

    /** Create the listing on first save, then add a new version. Saving identical content is refused (no empty versions). */
    public function save(Product $product, Marketplace $mp, array $input, User $user, ?string $note = null): Listing
    {
        $c = self::normalize($input);
        if ($c['title'] === '') throw ValidationException::withMessages(['title' => 'Title is required.']);
        return DB::transaction(function () use ($product, $mp, $c, $user, $note, $input) {
            $listing = Listing::withTrashed()->firstOrCreate(['product_id' => $product->id, 'marketplace_id' => $mp->id],
                ['status' => 'DRAFT', 'created_by' => $user->id, 'brand' => $product->brand->name ?? null, 'category_path' => $product->category->name ?? null]);
            if ($listing->trashed()) $listing->restore();
            $listing = Listing::whereKey($listing->id)->lockForUpdate()->first();

            $current = $listing->current_version_id ? ListingVersion::find($listing->current_version_id) : null;
            if ($current && $current->content() == $c) throw ValidationException::withMessages(['title' => 'Nothing changed since version ' . $current->version_no . '.']);

            $s = $this->scorer->score($product->id, $mp->id, $c);
            $v = ListingVersion::create($c + ['listing_id' => $listing->id, 'version_no' => (int) ListingVersion::where('listing_id', $listing->id)->max('version_no') + 1,
                'score' => $s['total'], 'score_breakdown' => $s['breakdown'], 'change_note' => $note ? mb_substr($note, 0, 255) : null, 'created_by' => $user->id]);
            $upd = ['current_version_id' => $v->id];
            foreach (['category_path', 'brand'] as $f) if (! empty($input[$f])) $upd[$f] = mb_substr($input[$f], 0, 255);
            $listing->update($upd);
            ActivityLogger::log('listing.version_saved', $listing, $current ? ['version' => $current->version_no, 'score' => (string) $current->score] : null, ['version' => $v->version_no, 'score' => (string) $v->score]);
            return $listing;
        });
    }

    /** Re-score the current version with today's keywords / rules (a new version is NOT created). */
    public function rescore(Listing $listing): ?ListingVersion
    {
        $v = $listing->current_version_id ? ListingVersion::find($listing->current_version_id) : null;
        if (! $v) return null;
        $s = $this->scorer->score($listing->product_id, $listing->marketplace_id, $v->content());
        $v->update(['score' => $s['total'], 'score_breakdown' => $s['breakdown']]);
        return $v;
    }

    /** "I applied this version on the marketplace." Records which version is live; changes nothing outside this system. */
    public function publish(Listing $listing, ListingVersion $v): Listing
    {
        if ($v->listing_id !== $listing->id) throw ValidationException::withMessages(['version' => 'That version belongs to another listing.']);
        $old = $listing->published_version_id;
        $listing->update(['published_version_id' => $v->id, 'status' => 'LIVE']);
        ActivityLogger::log('listing.published', $listing, ['published_version' => $old], ['published_version' => $v->id]);
        return $listing;
    }

    public function restore(Listing $listing, ListingVersion $v, User $user): Listing
    {
        if ($v->listing_id !== $listing->id) throw ValidationException::withMessages(['version' => 'That version belongs to another listing.']);
        return $this->save($listing->product, $listing->marketplace, $v->content(), $user, "Restored from version {$v->version_no}");
    }
}
