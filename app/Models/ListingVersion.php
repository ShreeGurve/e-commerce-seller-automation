<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ListingVersion extends Model { protected $fillable=['listing_id','version','snapshot','created_by']; protected $casts=['snapshot'=>'array']; public function listing(){return $this->belongsTo(Listing::class);} public function user(){return $this->belongsTo(User::class,'created_by');} }
