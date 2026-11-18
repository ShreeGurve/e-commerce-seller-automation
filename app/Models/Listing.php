<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Listing extends Model
{
    use SoftDeletes;
    public const STATUSES = ['DRAFT' => 'Draft', 'LIVE' => 'Live', 'SUPPRESSED' => 'Suppressed', 'INACTIVE' => 'Inactive'];
    public const BADGES = ['DRAFT' => 'secondary', 'LIVE' => 'success', 'SUPPRESSED' => 'danger', 'INACTIVE' => 'dark'];
    protected $fillable = ['product_id', 'marketplace_id', 'external_listing_id', 'status', 'current_version_id', 'published_version_id', 'category_path', 'brand', 'created_by',
        // Virtual legacy fields accepted by calculateScore() for older integrations; these are not persisted.
        'title', 'bullet_points', 'bullets', 'description', 'backend_keywords', 'image_urls', 'image_count', 'has_a_plus_content', 'aplus_content'];
    public function product() { return $this->belongsTo(Product::class)->withTrashed(); }
    public function marketplace() { return $this->belongsTo(Marketplace::class); }
    public function versions() { return $this->hasMany(ListingVersion::class)->orderByDesc('version_no'); }
    public function current() { return $this->belongsTo(ListingVersion::class, 'current_version_id'); }
    public function published() { return $this->belongsTo(ListingVersion::class, 'published_version_id'); }
    public function reviews() { return $this->hasMany(ListingReview::class)->latest('id'); }
    public function latestReview() { return $this->hasOne(ListingReview::class)->latestOfMany(); }

    /**
     * Backward-compatible score API for older listing integrations.
     * New listing versions use ListingScoreService; this adapter keeps legacy
     * callers working without changing the versioned scoring rules.
     */
    public function calculateScore(): array
    {
        $title = (string) ($this->title ?? '');
        $bullets = (array) ($this->bullet_points ?? $this->bullets ?? []);
        $description = (string) ($this->description ?? '');
        $backend = (string) ($this->backend_keywords ?? '');
        $images = $this->image_urls ?? [];
        $imageCount = is_array($images) ? count($images) : (int) ($this->image_count ?? 0);
        $hasAplus = (bool) ($this->has_a_plus_content ?? !empty($this->aplus_content));

        $checks = [
            'title' => [mb_strlen(trim($title)) >= 50 && mb_strlen(trim($title)) <= 200, 'Title'],
            'bullets' => [count(array_filter($bullets, fn ($v) => trim((string) $v) !== '')) > 0
                && max(array_map(fn ($v) => mb_strlen(trim((string) $v)), $bullets ?: [''])) >= 100, 'Bullet points'],
            'description' => [mb_strlen(trim($description)) >= 300, 'Description'],
            'backend' => [strlen(trim($backend)) >= 50, 'Backend keywords'],
            'images' => [$imageCount >= 5, 'Images'],
            'aplus' => [$hasAplus, 'A+ content'],
        ];
        $breakdown = [];
        $earned = 0;
        foreach ($checks as $key => [$passed, $label]) {
            $points = $passed ? 1 : 0;
            $breakdown[$key] = ['label' => $label, 'points' => $points, 'max' => 1, 'tips' => $passed ? [] : ["Improve {$label}."]];
            $earned += $points;
        }
        $score = (int) round(($earned / max(count($checks), 1)) * 100);
        return ['score' => $score, 'breakdown' => $breakdown];
    }

}
