<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class AdvertisingSearchTerm extends Model
{
    protected $fillable = ['campaign_id', 'group_id', 'keyword_id', 'search_term', 'target_hash', 'key_hash'];
    public function campaign() { return $this->belongsTo(AdvertisingCampaign::class, 'campaign_id'); }
}
