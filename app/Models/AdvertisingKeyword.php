<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class AdvertisingKeyword extends Model
{
    protected $fillable = ['campaign_id', 'group_id', 'keyword', 'match_type', 'key_hash'];
    public function campaign() { return $this->belongsTo(AdvertisingCampaign::class, 'campaign_id'); }
}
