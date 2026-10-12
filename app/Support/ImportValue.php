<?php
namespace App\Support;

use Carbon\Carbon;

/** Forgiving parsers for messy marketplace file values. Return null when the value cannot be trusted. */
class ImportValue
{
    public static function int(?string $v): ?int
    {
        $v = trim((string) $v);
        if ($v === '' || ! is_numeric(str_replace(',', '', $v))) return null;
        $f = (float) str_replace(',', '', $v);
        return floor($f) == $f ? (int) $f : null;
    }

    /** "₹1,299.00", "INR 1299", "Rs. 99.5" -> "1299.00". Negative or garbage -> null. */
    public static function money(?string $v): ?string
    {
        $s = preg_replace('/[^0-9.\-]/', '', (string) $v);
        if ($s === '' || $s === '-' || $s === '.' || substr_count($s, '.') > 1 || str_contains($s, '-')) return null;
        return number_format(round((float) $s, 2), 2, '.', '');
    }


    /** Signed amounts: "-25.50", "(25.50)", "₹ -1,200" -> "-25.50". Garbage -> null. */
    public static function signed(?string $v): ?string
    {
        $v = trim((string) $v);
        $neg = str_starts_with($v, '(') && str_ends_with($v, ')') || str_contains($v, '-');
        $s = preg_replace('/[^0-9.]/', '', $v);
        if ($s === '' || $s === '.' || substr_count($s, '.') > 1) return null;
        $n = round((float) $s, 2);
        return number_format($neg ? -$n : $n, 2, '.', '');
    }

    public static function date(?string $v, string $fmt = 'auto'): ?Carbon
    {
        $v = trim((string) $v);
        if ($v === '') return null;
        $tz = config('app.timezone');
        try {
            if ($fmt === 'auto') return Carbon::parse($v)->setTimezone($tz);
            foreach ([$fmt . ' H:i:s', $fmt . ' H:i', $fmt] as $f) {
                $dt = \DateTimeImmutable::createFromFormat('!' . $f, $v, new \DateTimeZone($tz));
                $e = \DateTimeImmutable::getLastErrors();
                if ($dt && (! $e || ($e['warning_count'] === 0 && $e['error_count'] === 0))) return Carbon::instance($dt);
            }
        } catch (\Throwable) {}
        return null;
    }

    /** Free-text return reason -> ProductReturn::TYPES key. */
    public static function returnType(?string $reason): string
    {
        $r = strtolower((string) $reason);
        $rules = [
            'COURIER_DAMAGE' => '/courier|transit|logistics|carrier/', 'WRONG_PRODUCT' => '/wrong|incorrect/',
            'DIFFERENT_PRODUCT' => '/different|not as described|not same/', 'MISSING_PART' => '/missing|incomplete/',
            'EMPTY_PACKAGE' => '/empty/', 'DAMAGED' => '/damage|broken|defect|torn|crack/', 'USED' => '/used|worn|dirty/',
            'CHANGED_MIND' => '/mind|no longer|not needed|unwanted|don.?t like|size|fit|changed/', 'SELLER_FAULT' => '/seller|fault|late|delay/',
        ];
        foreach ($rules as $type => $re) if (preg_match($re, $r)) return $type;
        return 'UNKNOWN';
    }
}
