<?php
namespace App\Models;

use App\Services\SettingsService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Claim extends Model
{
    use SoftDeletes;
    public const TYPES = [
        'SAFE_T' => 'SAFE-T claim', 'AMAZON_REIMBURSEMENT' => 'Amazon reimbursement', 'FLIPKART_CLAIM' => 'Flipkart claim', 'WRONG_ITEM' => 'Wrong item received',
        'DAMAGED_ITEM' => 'Damaged item', 'MISSING_ITEM' => 'Missing item', 'WEIGHT_DISPUTE' => 'Weight dispute', 'SHIPPING_DISPUTE' => 'Shipping dispute', 'RETURN_DISPUTE' => 'Return dispute',
    ];
    public const STATUSES = ['DRAFT', 'SUBMITTED', 'UNDER_REVIEW', 'APPROVED', 'PARTIALLY_APPROVED', 'REJECTED', 'APPEAL', 'CLOSED'];
    public const LABELS = ['DRAFT' => 'Draft', 'SUBMITTED' => 'Submitted', 'UNDER_REVIEW' => 'Under review', 'APPROVED' => 'Approved', 'PARTIALLY_APPROVED' => 'Partially approved',
        'REJECTED' => 'Rejected', 'APPEAL' => 'Appeal', 'CLOSED' => 'Closed'];
    public const BADGES = ['DRAFT' => 'secondary', 'SUBMITTED' => 'info', 'UNDER_REVIEW' => 'primary', 'APPROVED' => 'success', 'PARTIALLY_APPROVED' => 'warning',
        'REJECTED' => 'danger', 'APPEAL' => 'warning', 'CLOSED' => 'dark'];
    public const TRANSITIONS = [
        'DRAFT' => ['SUBMITTED', 'CLOSED'], 'SUBMITTED' => ['UNDER_REVIEW', 'APPROVED', 'PARTIALLY_APPROVED', 'REJECTED', 'CLOSED'],
        'UNDER_REVIEW' => ['APPROVED', 'PARTIALLY_APPROVED', 'REJECTED', 'CLOSED'], 'APPROVED' => ['CLOSED'],
        'PARTIALLY_APPROVED' => ['APPEAL', 'CLOSED'], 'REJECTED' => ['APPEAL', 'CLOSED'],
        'APPEAL' => ['UNDER_REVIEW', 'APPROVED', 'PARTIALLY_APPROVED', 'REJECTED', 'CLOSED'], 'CLOSED' => [],
    ];
    /** statuses where YOU still have to act before the deadline (file it, or appeal it) */
    public const ALERT_STATUSES = ['DRAFT', 'REJECTED', 'PARTIALLY_APPROVED', 'APPEAL'];

    protected $fillable = ['claim_number', 'marketplace_id', 'external_claim_id', 'order_id', 'return_id', 'product_id', 'claim_type', 'status', 'claim_amount',
        'approved_amount', 'rejected_amount', 'submitted_at', 'deadline', 'decision_date', 'appeal_count', 'notes', 'created_by'];
    protected $casts = ['claim_amount' => 'decimal:2', 'approved_amount' => 'decimal:2', 'rejected_amount' => 'decimal:2',
        'submitted_at' => 'date', 'deadline' => 'date', 'decision_date' => 'date'];

    public function marketplace() { return $this->belongsTo(Marketplace::class); }
    public function order() { return $this->belongsTo(Order::class); }
    public function productReturn() { return $this->belongsTo(ProductReturn::class, 'return_id'); }
    public function product() { return $this->belongsTo(Product::class)->withTrashed(); }
    public function documents() { return $this->hasMany(ClaimDocument::class); }
    public function events() { return $this->hasMany(ClaimEvent::class)->latest('id'); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
    public function nextStatuses(): array { return self::TRANSITIONS[$this->status] ?? []; }

    public static function urgentDays(): int { return max((int) SettingsService::get('claim_urgent_days', 3), 0); }
    public static function warningDays(): int { return max((int) SettingsService::get('claim_warning_days', 7), self::urgentDays()); }

    /** EXPIRED | URGENT | WARNING | OK | null (no action needed) */
    public function deadlineState(): ?string
    {
        if (! $this->deadline || ! in_array($this->status, self::ALERT_STATUSES, true)) return null;
        $days = (int) now()->startOfDay()->diffInDays($this->deadline->copy()->startOfDay(), false);
        return match (true) { $days < 0 => 'EXPIRED', $days <= self::urgentDays() => 'URGENT', $days <= self::warningDays() => 'WARNING', default => 'OK' };
    }

    public function daysLeft(): ?int
    {
        return $this->deadline ? (int) now()->startOfDay()->diffInDays($this->deadline->copy()->startOfDay(), false) : null;
    }

    /** claims that need attention now: overdue or inside the urgent window */
    public static function urgentCount(): int
    {
        return static::whereIn('status', self::ALERT_STATUSES)->whereNotNull('deadline')->whereDate('deadline', '<=', now()->addDays(self::urgentDays())->toDateString())->count();
    }
}
