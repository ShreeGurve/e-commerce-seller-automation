<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class ListingScoreRule extends Model
{
    /** criterion => [label, default max points, default params, param labels]. Used when no rule row exists. */
    public const DEFAULTS = [
        'title' => ['Title', 20, ['min_len' => 80, 'max_len' => 200, 'front_chars' => 80]],
        'bullets' => ['Bullet points', 20, ['count' => 5, 'min_len' => 100, 'max_len' => 500]],
        'description' => ['Description', 10, ['min_len' => 1000, 'max_len' => 2000]],
        'keywords' => ['Keyword coverage', 15, []],
        'images' => ['Images', 15, ['target' => 7]],
        'aplus' => ['A+ content', 10, ['min_len' => 200]],
        'features' => ['Special features', 5, ['target' => 3]],
        'backend' => ['Backend search terms', 5, ['min_bytes' => 150, 'max_bytes' => 250]],
    ];
    public const PARAM_LABELS = ['min_len' => 'Minimum characters', 'max_len' => 'Maximum characters', 'front_chars' => 'Key words must appear in first N characters', 'count' => 'Number of bullets',
        'target' => 'Target count', 'min_bytes' => 'Minimum bytes', 'max_bytes' => 'Maximum bytes'];
    protected $fillable = ['marketplace_id', 'criterion', 'max_points', 'params', 'is_active'];
    protected $casts = ['params' => 'array', 'max_points' => 'decimal:2', 'is_active' => 'boolean'];
    public function marketplace() { return $this->belongsTo(Marketplace::class); }
}
