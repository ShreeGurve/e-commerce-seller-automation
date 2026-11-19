<?php

namespace App\Services;

use App\Models\MarketplaceBankPayout;
use App\Models\Settlement;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MarketplacePayoutReconciliationService
{
    /** Create an imported/manual bank credit. Duplicate bank references are rejected. */
    public function record(array $data, ?int $userId = null): MarketplaceBankPayout
    {
        return DB::transaction(function () use ($data, $userId) {
            if (strtoupper($data['currency'] ?? 'INR') !== 'INR') {
                throw ValidationException::withMessages(['currency' => 'Only INR bank payouts are supported in this workflow.']);
            }
            if ((float) $data['bank_amount'] <= 0) {
                throw ValidationException::withMessages(['bank_amount' => 'Bank credit amount must be greater than zero.']);
            }

            if (MarketplaceBankPayout::where('marketplace_id', $data['marketplace_id'])
                ->where('bank_reference', trim($data['bank_reference']))->exists()) {
                throw ValidationException::withMessages(['bank_reference' => 'This bank reference is already recorded for the selected marketplace.']);
            }

            $settlement = null;
            if (!empty($data['settlement_id'])) {
                $settlement = Settlement::query()->lockForUpdate()->findOrFail($data['settlement_id']);
                if ((int) $settlement->marketplace_id !== (int) $data['marketplace_id']) {
                    throw ValidationException::withMessages(['settlement_id' => 'Selected settlement belongs to another marketplace.']);
                }
                if (MarketplaceBankPayout::where('settlement_id', $settlement->id)->exists()) {
                    throw ValidationException::withMessages(['settlement_id' => 'This settlement already has a bank payout record. Split payouts are not supported in this workflow yet.']);
                }
            } elseif (!empty($data['external_settlement_id'])) {
                $settlement = Settlement::where('marketplace_id', $data['marketplace_id'])
                    ->where('external_settlement_id', trim($data['external_settlement_id']))->first();
                if ($settlement && MarketplaceBankPayout::where('settlement_id', $settlement->id)->exists()) {
                    throw ValidationException::withMessages(['external_settlement_id' => 'This settlement already has a bank payout record.']);
                }
            }

            $bankAmount = round((float) $data['bank_amount'], 2);
            $settlementAmount = $settlement ? round((float) $settlement->total_amount, 2) : null;
            $difference = $settlementAmount === null ? null : round($bankAmount - $settlementAmount, 2);
            $status = $settlement === null ? 'UNMATCHED' : (abs($difference) <= 1.00 ? 'MATCHED' : 'MISMATCH');

            return MarketplaceBankPayout::create([
                'marketplace_id' => $data['marketplace_id'],
                'settlement_id' => $settlement?->id,
                'bank_reference' => trim($data['bank_reference']),
                'external_settlement_id' => $data['external_settlement_id'] ?? $settlement?->external_settlement_id,
                'payout_date' => $data['payout_date'] ?? null,
                'bank_amount' => $bankAmount,
                'settlement_amount' => $settlementAmount,
                'difference' => $difference,
                'currency' => strtoupper($data['currency'] ?? 'INR'),
                'status' => $status,
                'note' => $data['note'] ?? null,
                'created_by' => $userId,
            ]);
        });
    }

    /** Attach a previously unmatched bank credit to its marketplace settlement. */
    public function link(MarketplaceBankPayout $payout, Settlement $settlement, ?int $userId = null): MarketplaceBankPayout
    {
        return DB::transaction(function () use ($payout, $settlement, $userId) {
            $payout = MarketplaceBankPayout::query()->lockForUpdate()->findOrFail($payout->id);
            $settlement = Settlement::query()->lockForUpdate()->findOrFail($settlement->id);
            if ($payout->status === 'IGNORED') {
                throw ValidationException::withMessages(['payout' => 'Ignored payout records must be reopened before linking.']);
            }
            if ((int) $payout->marketplace_id !== (int) $settlement->marketplace_id) {
                throw ValidationException::withMessages(['settlement_id' => 'Payout and settlement must belong to the same marketplace.']);
            }
            if (MarketplaceBankPayout::where('settlement_id', $settlement->id)->where('id', '<>', $payout->id)->exists()) {
                throw ValidationException::withMessages(['settlement_id' => 'This settlement is already linked to another bank payout.']);
            }
            $difference = round((float) $payout->bank_amount - (float) $settlement->total_amount, 2);
            $payout->update([
                'settlement_id' => $settlement->id,
                'external_settlement_id' => $settlement->external_settlement_id,
                'settlement_amount' => $settlement->total_amount,
                'difference' => $difference,
                'status' => abs($difference) <= 1.00 ? 'MATCHED' : 'MISMATCH',
                'reviewed_by' => $userId,
                'reviewed_at' => now(),
            ]);
            return $payout->fresh(['settlement', 'marketplace']);
        });
    }

    public function ignore(MarketplaceBankPayout $payout, string $note, ?int $userId = null): MarketplaceBankPayout
    {
        if ($payout->status === 'MATCHED') {
            throw ValidationException::withMessages(['payout' => 'A matched payout cannot be ignored. Unlink it first if it was matched incorrectly.']);
        }
        $payout->update(['status' => 'IGNORED', 'note' => $note, 'reviewed_by' => $userId, 'reviewed_at' => now()]);
        return $payout->fresh();
    }

    public function reopen(MarketplaceBankPayout $payout, ?int $userId = null): MarketplaceBankPayout
    {
        $payout->update([
            'status' => $payout->settlement_id
                ? (abs((float) $payout->bank_amount - (float) $payout->settlement_amount) <= 1.00 ? 'MATCHED' : 'MISMATCH')
                : 'UNMATCHED',
            'reviewed_by' => $userId,
            'reviewed_at' => now(),
        ]);
        return $payout->fresh();
    }
}
