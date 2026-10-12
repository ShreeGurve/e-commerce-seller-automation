<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Purchase extends Model
{
    use SoftDeletes;
    public const STATUSES = ['DRAFT', 'ORDERED', 'RECEIVED', 'CANCELLED'];
    protected $fillable = ['supplier_id', 'po_number', 'purchase_date', 'invoice_number', 'invoice_date', 'status', 'payment_status',
        'subtotal', 'gst_amount', 'shipping_charges', 'other_charges', 'total_amount', 'amount_paid', 'received_at', 'notes', 'created_by'];
    protected $casts = ['purchase_date' => 'date', 'invoice_date' => 'date', 'received_at' => 'datetime',
        'subtotal' => 'decimal:2', 'gst_amount' => 'decimal:2', 'shipping_charges' => 'decimal:2',
        'other_charges' => 'decimal:2', 'total_amount' => 'decimal:2', 'amount_paid' => 'decimal:2'];
    public function supplier() { return $this->belongsTo(Supplier::class)->withTrashed(); }
    public function items() { return $this->hasMany(PurchaseItem::class); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
    public function isEditable(): bool { return in_array($this->status, ['DRAFT', 'ORDERED'], true); }
    public function balance(): string { return number_format((float) $this->total_amount - (float) $this->amount_paid, 2, '.', ''); }
}
