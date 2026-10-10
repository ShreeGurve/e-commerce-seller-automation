<?php
namespace App\Services;

use App\Models\{Claim, ClaimDocument, ClaimEvent, Marketplace, Order, ProductReturn, User};
use App\Support\Money;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\{DB, Storage};
use Illuminate\Validation\ValidationException;

class ClaimService
{
    /** What the return inspection tells us to claim: type, amount (price of the units that did not come back sellable), product. */
    public function suggestFromReturn(ProductReturn $r): array
    {
        $r->loadMissing(['order.marketplace', 'items.orderItem', 'items.product']);
        $paise = 0; $first = null; $missing = false;
        foreach ($r->items as $it) {
            $lost = $it->quantity - $it->restocked_qty;                     // units that are not back in sellable stock
            if ($lost <= 0) continue;
            $paise += $lost * Money::p($it->orderItem->unit_price);
            $first ??= $it->product_id;
            if ($it->condition === 'MISSING' || $it->received_qty < $it->quantity) $missing = true;
        }
        $code = $r->order->marketplace->code ?? '';
        $specific = $missing ? 'MISSING_ITEM' : (in_array($r->return_type, ['WRONG_PRODUCT', 'DIFFERENT_PRODUCT'], true) ? 'WRONG_ITEM' : 'DAMAGED_ITEM');
        return ['marketplace_id' => $r->marketplace_id, 'order_id' => $r->order_id, 'return_id' => $r->id, 'product_id' => $first,
            'claim_type' => $code === 'amazon' ? 'SAFE_T' : ($code === 'flipkart' ? 'FLIPKART_CLAIM' : $specific),
            'claim_amount' => Money::m($paise), 'notes' => "Return {$r->return_number} ({$specific}): " . ($r->reason_text ?: (ProductReturn::TYPES[$r->return_type] ?? ''))];
    }

    public function candidates(): Collection
    {
        return ProductReturn::with(['order', 'marketplace'])->where('claim_eligible', true)
            ->whereNotIn('id', Claim::whereNotNull('return_id')->select('return_id'))->latest('id')->limit(20)->get();
    }

    public function create(array $d, User $user): Claim
    {
        return DB::transaction(function () use ($d, $user) {
            $mp = Marketplace::findOrFail($d['marketplace_id']);
            $order = ! empty($d['order_id']) ? Order::findOrFail($d['order_id']) : null;
            $return = ! empty($d['return_id']) ? ProductReturn::findOrFail($d['return_id']) : null;
            if ($return && ! $order) $order = $return->order;
            if ($order && $order->marketplace_id !== $mp->id) throw ValidationException::withMessages(['order_ref' => 'That order belongs to another channel.']);
            if ($return && $order && $return->order_id !== $order->id) throw ValidationException::withMessages(['return_id' => 'That return belongs to another order.']);
            $amount = Money::p($d['claim_amount']);
            if ($amount <= 0) throw ValidationException::withMessages(['claim_amount' => 'Claim amount must be more than zero.']);

            if ($order) {
                $dup = Claim::where('order_id', $order->id)->where('claim_type', $d['claim_type'])->where('status', '!=', 'CLOSED')->first();
                if ($dup) throw ValidationException::withMessages(['claim_type' => "A {$dup->status} claim of this type already exists for this order ({$dup->claim_number})."]);
            }
            $ext = ($d['external_claim_id'] ?? '') ?: null;
            if ($ext && Claim::withTrashed()->where('marketplace_id', $mp->id)->where('external_claim_id', $ext)->exists()) {
                throw ValidationException::withMessages(['external_claim_id' => 'This marketplace claim id is already tracked.']);
            }
            $deadline = $d['deadline'] ?? null;
            if (! $deadline) {                                              // filing window comes from Settings, counted from the return (or order) date
                $ref = $return?->return_date ?? $order?->order_date ?? now();
                $deadline = \Carbon\Carbon::parse($ref)->addDays((int) SettingsService::get('claim_filing_window_days', 30))->toDateString();
            }
            $claim = Claim::create(['claim_number' => 'TMP' . bin2hex(random_bytes(8)), 'marketplace_id' => $mp->id, 'external_claim_id' => $ext, 'order_id' => $order?->id,
                'return_id' => $return?->id, 'product_id' => $d['product_id'] ?? null, 'claim_type' => $d['claim_type'], 'status' => 'DRAFT', 'claim_amount' => Money::m($amount),
                'deadline' => $deadline, 'notes' => $d['notes'] ?? null, 'created_by' => $user->id]);
            $claim->update(['claim_number' => sprintf('CL-%s-%06d', now()->format('Y'), $claim->id)]);
            ClaimEvent::create(['claim_id' => $claim->id, 'event' => 'CREATED', 'to_status' => 'DRAFT', 'amount' => Money::m($amount), 'note' => 'Claim created', 'user_id' => $user->id]);
            ActivityLogger::log('claim.created', $claim, null, ['claim' => $claim->claim_number, 'amount' => Money::m($amount), 'type' => $claim->claim_type]);
            return $claim;
        });
    }

    /** Notes, deadline and marketplace claim id can change any time; money and links only while still a draft. */
    public function update(Claim $claim, array $d): Claim
    {
        return DB::transaction(function () use ($claim, $d) {
            $c = Claim::whereKey($claim->id)->lockForUpdate()->firstOrFail();
            if ($c->status === 'CLOSED') throw ValidationException::withMessages(['status' => 'Closed claims cannot be edited.']);
            $upd = ['notes' => $d['notes'] ?? null, 'deadline' => $d['deadline'] ?? null];
            $ext = ($d['external_claim_id'] ?? '') ?: null;
            if ($ext !== $c->external_claim_id && $ext && Claim::withTrashed()->where('marketplace_id', $c->marketplace_id)->where('external_claim_id', $ext)->where('id', '!=', $c->id)->exists()) {
                throw ValidationException::withMessages(['external_claim_id' => 'This marketplace claim id is already tracked.']);
            }
            $upd['external_claim_id'] = $ext;
            if ($c->status === 'DRAFT') {
                $amount = Money::p($d['claim_amount'] ?? $c->claim_amount);
                if ($amount <= 0) throw ValidationException::withMessages(['claim_amount' => 'Claim amount must be more than zero.']);
                $upd += ['claim_amount' => Money::m($amount), 'claim_type' => $d['claim_type'] ?? $c->claim_type, 'product_id' => $d['product_id'] ?? $c->product_id];
            }
            $c->fill($upd); $dirty = $c->getDirty(); $old = array_intersect_key($c->getOriginal(), $dirty); $c->save();
            if ($dirty) { ClaimEvent::create(['claim_id' => $c->id, 'event' => 'NOTE', 'note' => 'Details edited: ' . implode(', ', array_keys($dirty)), 'user_id' => auth()->id()]); ActivityLogger::log('claim.updated', $c, $old, $dirty); }
            return $c;
        });
    }

    public function transition(Claim $claim, string $to, array $d, User $user): Claim
    {
        return DB::transaction(function () use ($claim, $to, $d, $user) {
            $c = Claim::whereKey($claim->id)->lockForUpdate()->firstOrFail();
            if (! in_array($to, $c->nextStatuses(), true)) throw ValidationException::withMessages(['status' => "A {$c->status} claim cannot move to {$to}."]);
            $claimP = Money::p($c->claim_amount); $upd = ['status' => $to]; $eventAmount = null;
            $decided = fn () => $d['decision_date'] ?? now()->toDateString();

            switch ($to) {
                case 'SUBMITTED':
                    $upd['submitted_at'] = $d['submitted_at'] ?? now()->toDateString();
                    if (! empty($d['external_claim_id'])) {
                        if (Claim::withTrashed()->where('marketplace_id', $c->marketplace_id)->where('external_claim_id', $d['external_claim_id'])->where('id', '!=', $c->id)->exists()) {
                            throw ValidationException::withMessages(['external_claim_id' => 'This marketplace claim id is already tracked.']);
                        }
                        $upd['external_claim_id'] = $d['external_claim_id'];
                    }
                    break;
                case 'APPROVED':
                case 'PARTIALLY_APPROVED':
                    $a = isset($d['approved_amount']) && $d['approved_amount'] !== '' ? Money::p($d['approved_amount']) : ($to === 'APPROVED' ? $claimP : 0);
                    if ($a <= 0 || $a > $claimP || ($to === 'PARTIALLY_APPROVED' && $a >= $claimP)) {
                        throw ValidationException::withMessages(['approved_amount' => $to === 'APPROVED' ? 'Approved amount must be more than 0 and at most the claim amount.' : 'Partial approval must be more than 0 and less than the claim amount.']);
                    }
                    $upd += ['approved_amount' => Money::m($a), 'rejected_amount' => Money::m($claimP - $a), 'decision_date' => $decided()]; $eventAmount = $a;
                    break;
                case 'REJECTED':
                    $upd += ['approved_amount' => '0.00', 'rejected_amount' => Money::m($claimP), 'decision_date' => $decided()];
                    break;
                case 'APPEAL':
                    $upd['appeal_count'] = $c->appeal_count + 1;
                    if (! empty($d['deadline'])) $upd['deadline'] = $d['deadline'];
                    break;
            }
            $from = $c->status;
            $c->update($upd);
            ClaimEvent::create(['claim_id' => $c->id, 'event' => 'STATUS', 'from_status' => $from, 'to_status' => $to, 'amount' => $eventAmount === null ? null : Money::m($eventAmount),
                'note' => $d['note'] ?? null, 'user_id' => $user->id]);
            ActivityLogger::log('claim.status_changed', $c, ['status' => $from], ['status' => $to, 'approved' => (string) $c->approved_amount]);
            if (in_array($to, ['APPROVED', 'PARTIALLY_APPROVED', 'REJECTED'], true) && $c->order_id && ($o = Order::find($c->order_id))) {
                app(ProfitService::class)->safe($o);                        // approved money counts as recovery in profit until the payout shows in a settlement
            }
            return $c;
        });
    }

    public function addDocuments(Claim $claim, array $files, User $user): int
    {
        $n = 0;
        foreach ($files as $f) {
            $claim->documents()->create(['path' => $f->store("claims/{$claim->id}", 'local'), 'original_name' => $f->getClientOriginalName(),
                'mime' => $f->getMimeType(), 'size' => $f->getSize(), 'uploaded_by' => $user->id]);
            $n++;
        }
        if ($n) ClaimEvent::create(['claim_id' => $claim->id, 'event' => 'DOCUMENT', 'note' => "{$n} evidence file(s) added", 'user_id' => $user->id]);
        return $n;
    }

    /** Copies the photos/videos taken at return inspection into the claim's evidence (the return keeps its own copies). */
    public function attachReturnEvidence(Claim $claim, ProductReturn $return, User $user): int
    {
        if ($claim->return_id !== $return->id) throw ValidationException::withMessages(['claim' => 'This claim is not linked to that return.']);
        $disk = Storage::disk('local'); $n = 0;
        foreach ($return->media as $m) {
            if (! $disk->exists($m->path)) continue;
            $new = "claims/{$claim->id}/" . bin2hex(random_bytes(6)) . '-' . basename($m->path);
            $disk->copy($m->path, $new);
            $claim->documents()->create(['path' => $new, 'original_name' => $m->original_name, 'mime' => $m->mime, 'size' => $m->size, 'uploaded_by' => $user->id]);
            $n++;
        }
        if ($n) ClaimEvent::create(['claim_id' => $claim->id, 'event' => 'DOCUMENT', 'note' => "{$n} file(s) copied from {$return->return_number}", 'user_id' => $user->id]);
        return $n;
    }
}
