<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Supplier extends Model
{
    use SoftDeletes;
    protected $fillable = ['name', 'contact_person', 'phone', 'email', 'gstin', 'address', 'city', 'state', 'payment_terms', 'notes', 'is_active'];
    protected $casts = ['is_active' => 'boolean'];
    public function purchases() { return $this->hasMany(Purchase::class); }
}
