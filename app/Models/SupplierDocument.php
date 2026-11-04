<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupplierDocument extends Model
{
    protected $fillable = [
        'supplier_id', 'document_type', 'title', 'original_filename', 'disk', 'stored_path',
        'mime_type', 'file_size', 'reference_number', 'issued_at', 'expires_at', 'status',
        'verified_by', 'verified_at', 'review_notes', 'uploaded_by',
    ];

    protected $casts = [
        'issued_at' => 'date',
        'expires_at' => 'date',
        'verified_at' => 'datetime',
        'file_size' => 'integer',
    ];

    public function supplier() { return $this->belongsTo(Supplier::class); }
    public function uploader() { return $this->belongsTo(User::class, 'uploaded_by'); }
    public function verifier() { return $this->belongsTo(User::class, 'verified_by'); }

    public function getExpiryStateAttribute(): string
    {
        if (!$this->expires_at) return 'NO_EXPIRY';
        if ($this->expires_at->lt(today())) return 'EXPIRED';
        if ($this->expires_at->lte(today()->addDays(30))) return 'EXPIRING';
        return 'VALID';
    }
}
