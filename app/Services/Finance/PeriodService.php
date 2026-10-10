<?php

namespace App\Services\Finance;

use App\Models\FinancialPeriod;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/** Enforces monthly financial period state when callers explicitly guard a write. */
class PeriodService
{
    public function assertOpen(int $businessId, Carbon|string $date): void
    {
        $date = $date instanceof Carbon ? $date : Carbon::parse($date);
        $period = FinancialPeriod::query()
            ->where('business_id', $businessId)
            ->where('year', (int) $date->format('Y'))
            ->where('month', (int) $date->format('n'))
            ->first();

        if ($period && strtoupper((string) $period->status) === 'CLOSED') {
            throw ValidationException::withMessages([
                'financial_period' => sprintf('The financial period %04d-%02d is closed. Reopen it before editing financial records.', $date->year, $date->month),
            ]);
        }
    }

    public function close(int $businessId, int $year, int $month, int $userId, ?string $notes = null): FinancialPeriod
    {
        $period = FinancialPeriod::query()->firstOrNew([
            'business_id' => $businessId,
            'year' => $year,
            'month' => $month,
        ]);
        $period->status = 'CLOSED';
        $period->closed_by = $userId;
        $period->closed_at = now();
        $period->notes = $notes;
        $period->save();
        return $period;
    }

    public function reopen(FinancialPeriod $period, int $userId, ?string $notes = null): FinancialPeriod
    {
        $period->status = 'OPEN';
        $period->reopened_by = $userId;
        $period->reopened_at = now();
        if ($notes !== null) $period->notes = $notes;
        $period->save();
        return $period;
    }
}
