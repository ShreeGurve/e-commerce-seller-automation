<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class ClaimEvent extends Model
{
    const UPDATED_AT = null;
    protected $fillable = ['claim_id', 'event', 'from_status', 'to_status', 'amount', 'note', 'user_id'];
    protected $casts = ['amount' => 'decimal:2'];
    public function user() { return $this->belongsTo(User::class); }
}
