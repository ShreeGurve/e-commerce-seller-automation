<?php
namespace App\Support;

/** Money is added as integer paise (no float drift) and stored as DECIMAL(15,2). */
class Money
{
    public static function p(string|int|float|null $v): int { return (int) round(((float) $v) * 100); }
    public static function m(int $paise): string { return number_format($paise / 100, 2, '.', ''); }
}
