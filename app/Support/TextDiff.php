<?php
namespace App\Support;

/** Word-level diff for comparing listing versions. Returns two safe HTML strings: removed words in A, added words in B. */
class TextDiff
{
    private const MAX_WORDS = 600;

    /** @return array{0:string,1:string} */
    public static function words(?string $a, ?string $b): array
    {
        $x = preg_split('/\s+/u', trim((string) $a), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $y = preg_split('/\s+/u', trim((string) $b), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if (count($x) > self::MAX_WORDS || count($y) > self::MAX_WORDS) return [e((string) $a), e((string) $b)];   // too long to highlight cheaply
        $n = count($x); $m = count($y);
        $l = array_fill(0, $n + 1, array_fill(0, $m + 1, 0));
        for ($i = $n - 1; $i >= 0; $i--) for ($j = $m - 1; $j >= 0; $j--) $l[$i][$j] = $x[$i] === $y[$j] ? $l[$i + 1][$j + 1] + 1 : max($l[$i + 1][$j], $l[$i][$j + 1]);
        $ha = []; $hb = []; $i = $j = 0;
        while ($i < $n && $j < $m) {
            if ($x[$i] === $y[$j]) { $ha[] = e($x[$i]); $hb[] = e($y[$j]); $i++; $j++; }
            elseif ($l[$i + 1][$j] >= $l[$i][$j + 1]) { $ha[] = '<del class="bg-danger-subtle text-danger">' . e($x[$i++]) . '</del>'; }
            else { $hb[] = '<ins class="bg-success-subtle text-success text-decoration-none">' . e($y[$j++]) . '</ins>'; }
        }
        while ($i < $n) $ha[] = '<del class="bg-danger-subtle text-danger">' . e($x[$i++]) . '</del>';
        while ($j < $m) $hb[] = '<ins class="bg-success-subtle text-success text-decoration-none">' . e($y[$j++]) . '</ins>';
        return [implode(' ', $ha), implode(' ', $hb)];
    }
}
