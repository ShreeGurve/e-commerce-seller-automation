<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Listing extends Model
{
    use SoftDeletes;
    public const STATUSES = ['DRAFT' => 'Draft', 'LIVE' => 'Live', 'SUPPRESSED' => 'Suppressed', 'INACTIVE' => 'Inactive'];
    public const BADGES = ['DRAFT' => 'secondary', 'LIVE' => 'success', 'SUPPRESSED' => 'danger', 'INACTIVE' => 'dark'];
    protected $fillable = ['product_id', 'marketplace_id', 'status', 'current_version_id', 'published_version_id', 'category_path', 'brand', 'created_by'];
    public function product() { return $this->belongsTo(Product::class)->withTrashed(); }
    public function marketplace() { return $this->belongsTo(Marketplace::class); }
    public function versions() { return $this->hasMany(ListingVersion::class)->orderByDesc('version_no'); }
    public function current() { return $this->belongsTo(ListingVersion::class, 'current_version_id'); }
    public function published() { return $this->belongsTo(ListingVersion::class, 'published_version_id'); }
}
