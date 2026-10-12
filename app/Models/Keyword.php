<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Keyword extends Model
{
    public const TYPES = ['PRIMARY' => 'Primary', 'SECONDARY' => 'Secondary', 'LONG_TAIL' => 'Long-tail', 'BACKEND' => 'Backend', 'AEO' => 'AEO (answer engines)', 'SEO' => 'SEO', 'GEO' => 'GEO (generative engines)'];
    public const COMPETITION = ['LOW' => 'Low', 'MEDIUM' => 'Medium', 'HIGH' => 'High'];
    protected $fillable = ['product_id', 'marketplace_id', 'keyword', 'key_hash', 'keyword_type', 'search_volume', 'competition', 'relevance', 'current_rank', 'rank_checked_on', 'source', 'group_id', 'notes', 'created_by'];
    protected $casts = ['rank_checked_on' => 'date'];
    public static function hash(string $kw): string { return sha1(mb_strtolower(trim($kw))); }
    public function product() { return $this->belongsTo(Product::class)->withTrashed(); }
    public function marketplace() { return $this->belongsTo(Marketplace::class); }
    public function group() { return $this->belongsTo(KeywordGroup::class, 'group_id'); }
    public function rankings() { return $this->hasMany(KeywordRanking::class)->orderByDesc('checked_on'); }
}
