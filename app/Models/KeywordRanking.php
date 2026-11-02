<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class KeywordRanking extends Model
{
    protected $fillable = ['keyword_id', 'checked_on', 'rank', 'rank_type', 'source', 'created_by'];
    protected $casts = ['checked_on' => 'date'];
}
