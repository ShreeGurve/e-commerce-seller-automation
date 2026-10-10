<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class KeywordRanking extends Model { protected $fillable=['keyword_id','observed_on','ranking','source']; protected $casts=['observed_on'=>'date']; public function keyword(){return $this->belongsTo(Keyword::class);} }
