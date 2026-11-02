<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class ListingVersion extends Model
{
    const UPDATED_AT = null;
    protected $fillable = ['listing_id', 'version_no', 'title', 'bullets', 'description', 'backend_keywords', 'special_features', 'aplus_content', 'image_count', 'has_video',
        'score', 'score_breakdown', 'change_note', 'created_by'];
    protected $casts = ['bullets' => 'array', 'score_breakdown' => 'array', 'has_video' => 'boolean', 'score' => 'decimal:2'];
    public function listing() { return $this->belongsTo(Listing::class); }
    public function author() { return $this->belongsTo(User::class, 'created_by'); }
    /** the editable content fields, used for "did anything change?" and for copying a version */
    public function content(): array
    {
        return ['title' => $this->title, 'bullets' => array_values($this->bullets ?? []), 'description' => $this->description, 'backend_keywords' => $this->backend_keywords,
            'special_features' => $this->special_features, 'aplus_content' => $this->aplus_content, 'image_count' => (int) $this->image_count, 'has_video' => (bool) $this->has_video];
    }
}
