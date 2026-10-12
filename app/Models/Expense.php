<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Expense extends Model
{
    use SoftDeletes;
    protected $fillable = ['expense_category_id', 'expense_date', 'amount', 'marketplace_id', 'product_id', 'invoice_number', 'notes', 'attachment_path', 'attachment_name', 'created_by'];
    protected $casts = ['expense_date' => 'date', 'amount' => 'decimal:2'];
    public function category() { return $this->belongsTo(ExpenseCategory::class, 'expense_category_id'); }
    public function marketplace() { return $this->belongsTo(Marketplace::class); }
    public function product() { return $this->belongsTo(Product::class)->withTrashed(); }
}
