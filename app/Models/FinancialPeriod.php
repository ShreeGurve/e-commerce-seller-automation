<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class FinancialPeriod extends Model
{
    protected $guarded = [];
    protected $casts = ['closed_at' => 'datetime', 'reopened_at' => 'datetime'];
}
