<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class AdvertisingDaily extends Model
{
    protected $table = 'advertising_daily';
    /** Which level to trust for TOTALS when several are imported for the same period (campaign report is the most complete). */
    public const TOTAL_PRIORITY = ['CAMPAIGN', 'PRODUCT', 'KEYWORD', 'TERM'];
    protected $fillable = ['marketplace_id', 'date', 'level', 'campaign_id', 'group_id', 'keyword_id', 'search_term_id', 'product_id',
        'impressions', 'clicks', 'spend', 'sales', 'orders', 'units', 'row_key', 'import_id'];
    protected $casts = ['date' => 'date', 'spend' => 'decimal:2', 'sales' => 'decimal:2'];
    public function campaign() { return $this->belongsTo(AdvertisingCampaign::class, 'campaign_id'); }
}
