<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Keyword extends Model { protected $fillable=['product_id','marketplace_id','keyword','keyword_type','search_volume','competition','relevance','ranking','source','group_name']; public function product(){return $this->belongsTo(Product::class);} public function marketplace(){return $this->belongsTo(Marketplace::class);} public function rankings(){return $this->hasMany(KeywordRanking::class);} }
