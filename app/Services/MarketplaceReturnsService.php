<?php

namespace App\Services;

use App\Models\Marketplace;
use App\Models\MarketplaceRefundImport;
use App\Models\MarketplaceReturnEvent;
use App\Models\MarketplaceReturnImport;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProductReturn;
use App\Models\Refund;
use App\Models\ReturnInspection;
use App\Models\ReturnItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/** Stages externally supplied return/refund records; marketplace polling and writes are deliberately out of scope. */
class MarketplaceReturnsService
{
    private const RETURN_TYPES = ['CHANGED_MIND', 'WRONG_PRODUCT', 'DAMAGED', 'USED', 'MISSING_PART', 'EMPTY_PACKAGE', 'DIFFERENT_PRODUCT', 'SELLER_FAULT', 'COURIER_DAMAGE', 'UNKNOWN'];

    private const CONDITIONS = ['SELLABLE', 'USED', 'DAMAGED', 'SCRAP', 'MISSING'];

    public function stageReturn(Marketplace $marketplace, array $data): MarketplaceReturnImport
    {
        $externalReturnId = trim((string) ($data['external_return_id'] ?? ''));
        $externalOrderId = trim((string) ($data['external_order_id'] ?? ''));
        if ($externalReturnId === '' || strlen($externalReturnId) > 80) {
            throw ValidationException::withMessages(['external_return_id' => 'A valid marketplace return ID is required.']);
        }
        if ($externalOrderId === '' || strlen($externalOrderId) > 60) {
            throw ValidationException::withMessages(['external_order_id' => 'A valid marketplace order ID is required.']);
        }
        $date = $data['return_date'] ?? null;
        try {
            $date = $date ? now()->parse($date)->toDateString() : null;
        } catch (Throwable) {
            $date = null;
        }
        if (! $date) {
            throw ValidationException::withMessages(['return_date' => 'A valid return date is required.']);
        }
        $items = $data['items'] ?? [];
        if (! is_array($items) || $items === []) {
            throw ValidationException::withMessages(['items' => 'At least one return line is required.']);
        }
        $cleanItems = [];
        foreach ($items as $index => $item) {
            if (! is_array($item)) {
                continue;
            }
            $lineId = trim((string) ($item['external_line_id'] ?? ''));
            $sku = trim((string) ($item['marketplace_sku'] ?? ''));
            $quantity = filter_var($item['quantity'] ?? null, FILTER_VALIDATE_INT);
            if (($lineId === '' && $sku === '') || ! $quantity || $quantity < 1) {
                throw ValidationException::withMessages(["items.{$index}" => 'Each return line needs an order line ID or marketplace SKU and a positive quantity.']);
            }
            $cleanItems[] = ['external_line_id' => $lineId ?: null, 'marketplace_sku' => $sku ?: null, 'quantity' => $quantity];
        }
        if ($cleanItems === []) {
            throw ValidationException::withMessages(['items' => 'No valid return lines were provided.']);
        }
        $type = strtoupper((string) ($data['return_type'] ?? 'UNKNOWN'));
        if (! in_array($type, self::RETURN_TYPES, true)) {
            $type = 'UNKNOWN';
        }
        $payload = ['return_date' => $date, 'return_type' => $type, 'reason_text' => Str::limit(trim((string) ($data['reason_text'] ?? '')), 500, ''), 'items' => $cleanItems];

        $existing = MarketplaceReturnImport::query()->where('marketplace_id', $marketplace->id)->where('external_return_id', $externalReturnId)->first();
        if ($existing) {
            if (in_array($existing->status, ['IMPORTED', 'REJECTED'], true)) {
                return $existing;
            }
            $existing->update(['external_order_id' => $externalOrderId, 'payload' => $payload, 'validation_errors' => [], 'status' => 'PENDING']);

            return $existing->fresh();
        }

        return MarketplaceReturnImport::create(['marketplace_id' => $marketplace->id, 'external_return_id' => $externalReturnId,
            'external_order_id' => $externalOrderId, 'status' => 'PENDING', 'payload' => $payload, 'validation_errors' => []]);
    }

    public function approveReturn(MarketplaceReturnImport $candidate, User $reviewer): ProductReturn
    {
        return DB::transaction(function () use ($candidate, $reviewer) {
            $candidate = MarketplaceReturnImport::query()->lockForUpdate()->findOrFail($candidate->id);
            if ($candidate->status === 'IMPORTED' && $candidate->return_id) {
                return ProductReturn::with('items')->findOrFail($candidate->return_id);
            }
            if ($candidate->status !== 'PENDING') {
                throw ValidationException::withMessages(['status' => 'Only pending marketplace returns can be imported.']);
            }
            $order = Order::query()->where('marketplace_id', $candidate->marketplace_id)->where('external_order_id', $candidate->external_order_id)->first();
            if (! $order) {
                throw ValidationException::withMessages(['order' => 'The matching marketplace order must be imported before this return.']);
            }
            $payload = $candidate->payload ?? [];
            $resolved = [];
            foreach (($payload['items'] ?? []) as $index => $line) {
                $query = OrderItem::query()->where('order_id', $order->id);
                $orderItem = ! empty($line['external_line_id']) ? (clone $query)->where('external_line_id', $line['external_line_id'])->first() : null;
                if (! $orderItem && ! empty($line['marketplace_sku'])) {
                    $orderItem = (clone $query)->where('marketplace_sku', $line['marketplace_sku'])->first();
                }
                if (! $orderItem) {
                    throw ValidationException::withMessages(["items.{$index}" => 'Return line could not be matched to an order item.']);
                }
                $previousQty = (int) ReturnItem::query()->where('order_item_id', $orderItem->id)->sum('quantity');
                $qty = (int) $line['quantity'];
                if ($qty + $previousQty > (int) $orderItem->quantity) {
                    throw ValidationException::withMessages(["items.{$index}" => 'Returned quantity exceeds the ordered quantity after previous returns.']);
                }
                $resolved[] = ['order_item' => $orderItem, 'quantity' => $qty];
            }
            $return = ProductReturn::create(['order_id' => $order->id, 'marketplace_id' => $candidate->marketplace_id,
                'return_number' => 'TMP'.Str::upper(Str::random(12)), 'external_return_id' => $candidate->external_return_id,
                'return_date' => $payload['return_date'], 'return_type' => $payload['return_type'] ?? 'UNKNOWN', 'reason_text' => $payload['reason_text'] ?? null,
                'status' => 'REQUESTED', 'claim_eligible' => false, 'notes' => 'Imported from marketplace after manual review.', 'created_by' => $reviewer->id]);
            $return->update(['return_number' => sprintf('RT-%s-%06d', now()->format('Y'), $return->id)]);
            foreach ($resolved as $line) {
                ReturnItem::create(['return_id' => $return->id, 'order_item_id' => $line['order_item']->id, 'product_id' => $line['order_item']->product_id,
                    'quantity' => $line['quantity'], 'received_qty' => 0, 'restocked_qty' => 0]);
            }
            $candidate->update(['status' => 'IMPORTED', 'return_id' => $return->id, 'reviewed_by' => $reviewer->id, 'reviewed_at' => now()]);
            $this->event($return, 'IMPORTED', null, 'REQUESTED', ['external_return_id' => $candidate->external_return_id], $reviewer->id);

            return $return->fresh('items');
        });
    }

    /** Records inspection/disposition. Restock quantities are informational only; this method never changes inventory. */
    public function inspect(ProductReturn $return, array $lines, User $inspector): ProductReturn
    {
        return DB::transaction(function () use ($return, $lines, $inspector) {
            $return = ProductReturn::query()->lockForUpdate()->findOrFail($return->id);
            if (! in_array($return->status, ['REQUESTED', 'INSPECTED'], true)) {
                throw ValidationException::withMessages(['status' => 'Only requested or previously inspected returns can be inspected.']);
            }
            $from = $return->status;
            $claimEligible = false;
            $processed = [];
            foreach ($lines as $index => $line) {
                $item = ReturnItem::query()->where('return_id', $return->id)->whereKey($line['return_item_id'] ?? 0)->lockForUpdate()->first();
                if (! $item) {
                    throw ValidationException::withMessages(["items.{$index}" => 'Return item does not belong to this return.']);
                }
                $received = filter_var($line['received_qty'] ?? null, FILTER_VALIDATE_INT);
                $condition = strtoupper((string) ($line['condition'] ?? ''));
                $restock = filter_var($line['restock_qty'] ?? 0, FILTER_VALIDATE_INT);
                if ($received === false || $received < 0 || $received > $item->quantity) {
                    throw ValidationException::withMessages(["items.{$index}.received_qty" => 'Received quantity must be between zero and expected quantity.']);
                }
                if (! in_array($condition, self::CONDITIONS, true)) {
                    throw ValidationException::withMessages(["items.{$index}.condition" => 'Unsupported return condition.']);
                }
                if ($restock === false || $restock < 0 || $restock > $received || ($restock > 0 && $condition !== 'SELLABLE')) {
                    throw ValidationException::withMessages(["items.{$index}.restock_qty" => 'Only received sellable units can be marked for restock.']);
                }
                $disposition = match ($condition) {
                    'SELLABLE' => ($restock === $received ? 'RESTOCK' : 'REVIEW'), 'USED' => 'QUARANTINE', 'DAMAGED' => 'QUARANTINE', 'SCRAP' => 'SCRAP', default => 'MISSING'
                };
                $item->update(['received_qty' => $received, 'condition' => $condition, 'restocked_qty' => $restock, 'note' => Str::limit((string) ($line['note'] ?? ''), 500, '')]);
                ReturnInspection::create(['return_id' => $return->id, 'return_item_id' => $item->id, 'received_qty' => $received, 'condition' => $condition,
                    'restock_qty' => $restock, 'disposition' => $disposition, 'note' => $line['note'] ?? null, 'inspected_by' => $inspector->id]);
                if ($received < $item->quantity || $restock < $item->quantity || in_array($condition, ['USED', 'DAMAGED', 'SCRAP', 'MISSING'], true)) {
                    $claimEligible = true;
                }
                $processed[] = ['return_item_id' => $item->id, 'received_qty' => $received, 'condition' => $condition, 'restock_qty' => $restock, 'disposition' => $disposition];
            }
            if ($processed === []) {
                throw ValidationException::withMessages(['items' => 'At least one return item must be inspected.']);
            }
            if (count($processed) !== ReturnItem::query()->where('return_id', $return->id)->count()) {
                throw ValidationException::withMessages(['items' => 'Inspect every line on the return before completing the inspection.']);
            }
            $return->update(['status' => 'INSPECTED', 'inspected_at' => now(), 'claim_eligible' => $claimEligible]);
            $this->event($return, 'INSPECTED', $from, 'INSPECTED', ['lines' => $processed, 'inventory_changed' => false], $inspector->id);

            return $return->fresh(['items', 'media']);
        });
    }

    public function stageRefund(Marketplace $marketplace, array $data): MarketplaceRefundImport
    {
        $externalId = trim((string) ($data['external_refund_id'] ?? ''));
        $orderId = trim((string) ($data['external_order_id'] ?? ''));
        $amount = $data['amount'] ?? null;
        $currency = strtoupper((string) ($data['currency'] ?? 'INR'));
        $date = $data['refund_date'] ?? null;
        try {
            $date = $date ? now()->parse($date)->toDateString() : null;
        } catch (Throwable) {
            $date = null;
        }
        if ($externalId === '' || strlen($externalId) > 80) {
            throw ValidationException::withMessages(['external_refund_id' => 'A valid external refund ID is required.']);
        }
        if ($orderId === '' || strlen($orderId) > 60) {
            throw ValidationException::withMessages(['external_order_id' => 'A valid marketplace order ID is required.']);
        }
        if (! is_numeric($amount) || (float) $amount <= 0) {
            throw ValidationException::withMessages(['amount' => 'Refund amount must be greater than zero.']);
        }
        if ($currency !== 'INR') {
            throw ValidationException::withMessages(['currency' => 'Only INR refund reconciliation is supported by the current ledger.']);
        }
        if (! $date) {
            throw ValidationException::withMessages(['refund_date' => 'A valid refund date is required.']);
        }
        $existing = MarketplaceRefundImport::query()->where('marketplace_id', $marketplace->id)->where('external_refund_id', $externalId)->first();
        if ($existing) {
            return $existing;
        }

        return MarketplaceRefundImport::create(['marketplace_id' => $marketplace->id, 'external_refund_id' => $externalId, 'external_order_id' => $orderId,
            'external_return_id' => $data['external_return_id'] ?? null, 'amount' => number_format((float) $amount, 2, '.', ''), 'currency' => 'INR',
            'refund_date' => $date, 'reason' => Str::limit((string) ($data['reason'] ?? ''), 180, ''), 'status' => 'PENDING']);
    }

    public function reconcileRefund(MarketplaceRefundImport $candidate, User $reviewer): Refund
    {
        return DB::transaction(function () use ($candidate, $reviewer) {
            $candidate = MarketplaceRefundImport::query()->lockForUpdate()->findOrFail($candidate->id);
            if ($candidate->status === 'RECONCILED' && $candidate->refund_id) {
                return Refund::findOrFail($candidate->refund_id);
            }
            if ($candidate->status !== 'PENDING') {
                throw ValidationException::withMessages(['status' => 'Only pending refund imports can be reconciled.']);
            }
            $order = Order::query()->where('marketplace_id', $candidate->marketplace_id)->where('external_order_id', $candidate->external_order_id)->first();
            if (! $order) {
                throw ValidationException::withMessages(['order' => 'The matching marketplace order was not found.']);
            }
            $returnId = null;
            if ($candidate->external_return_id) {
                $returnId = ProductReturn::query()->where('marketplace_id', $candidate->marketplace_id)->where('external_return_id', $candidate->external_return_id)->value('id');
                if (! $returnId) {
                    throw ValidationException::withMessages(['return_id' => 'Import the matching return before reconciling this refund.']);
                }
            }
            $refund = app(RefundService::class)->record($order, ['external_refund_id' => $candidate->external_refund_id, 'amount' => $candidate->amount,
                'refund_date' => $candidate->refund_date->toDateString(), 'reason' => $candidate->reason, 'return_id' => $returnId,
                'notes' => 'Reconciled from marketplace refund import.'], $reviewer);
            $candidate->update(['status' => 'RECONCILED', 'refund_id' => $refund->id, 'reviewed_by' => $reviewer->id, 'reviewed_at' => now()]);

            return $refund;
        });
    }

    public function rejectReturn(MarketplaceReturnImport $candidate, User $reviewer, string $note): MarketplaceReturnImport
    {
        if (trim($note) === '') {
            throw ValidationException::withMessages(['note' => 'A rejection reason is required.']);
        }
        if ($candidate->status !== 'PENDING') {
            throw ValidationException::withMessages(['status' => 'Only pending return imports can be rejected.']);
        }
        $candidate->update(['status' => 'REJECTED', 'reviewed_by' => $reviewer->id, 'reviewed_at' => now(), 'review_note' => Str::limit($note, 1000, '')]);

        return $candidate->fresh();
    }

    private function event(ProductReturn $return, string $type, ?string $from, ?string $to, array $details, ?int $userId): void
    {
        MarketplaceReturnEvent::create(['return_id' => $return->id, 'event_type' => $type, 'from_status' => $from, 'to_status' => $to, 'details' => $details, 'user_id' => $userId, 'created_at' => now()]);
    }
}
