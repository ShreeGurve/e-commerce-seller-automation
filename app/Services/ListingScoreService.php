<?php
namespace App\Services;

use App\Models\{Keyword, ListingScoreRule};

/**
 * 0-100 listing score. Every criterion has a configurable maximum and rules (Listings > Score rules).
 * The final score is earned points / total maximum x 100, so you can re-weight criteria freely.
 */
class ListingScoreService
{
    public function score(int $productId, int $marketplaceId, array $fields): array
    {
        $kws = Keyword::where('product_id', $productId)->where('marketplace_id', $marketplaceId)->get(['keyword', 'keyword_type'])
            ->map(fn ($k) => ['keyword' => $k->keyword, 'type' => $k->keyword_type])->all();
        return $this->compute($fields, $this->rules($marketplaceId), $kws);
    }

    /** criterion => ['max'=>float,'params'=>array]. A channel-specific rule beats the "all channels" rule; inactive rules switch the criterion off. */
    public function rules(int $marketplaceId): array
    {
        $rows = ListingScoreRule::where(fn ($q) => $q->whereNull('marketplace_id')->orWhere('marketplace_id', $marketplaceId))->get();
        $out = [];
        foreach (ListingScoreRule::DEFAULTS as $c => [$label, $max, $params]) {
            $row = $rows->where('criterion', $c)->sortByDesc(fn ($r) => $r->marketplace_id !== null)->first();
            if ($row && ! $row->is_active) continue;
            $out[$c] = ['max' => (float) ($row->max_points ?? $max), 'params' => ($row->params ?? []) + $params];
        }
        return $out;
    }

    /**
     * Pure calculation (no database): $fields = title, bullets[], description, backend_keywords, special_features, aplus_content, image_count, has_video
     * @param array<string,array{max:float,params:array}> $rules   @param array<int,array{keyword:string,type:string}> $kws
     * @return array{total:float,breakdown:array<string,array{label:string,points:float,max:float,tips:array<int,string>}>}
     */
    public function compute(array $fields, array $rules, array $kws): array
    {
        $title = trim((string) ($fields['title'] ?? ''));
        $bullets = array_values(array_filter(array_map(fn ($b) => trim((string) $b), (array) ($fields['bullets'] ?? [])), fn ($b) => $b !== ''));
        $desc = trim((string) ($fields['description'] ?? '')); $back = trim((string) ($fields['backend_keywords'] ?? ''));
        $feat = array_values(array_filter(array_map('trim', preg_split('/[\r\n,;]+/', (string) ($fields['special_features'] ?? ''))), fn ($x) => $x !== ''));
        $aplus = trim((string) ($fields['aplus_content'] ?? '')); $imgs = (int) ($fields['image_count'] ?? 0); $video = (bool) ($fields['has_video'] ?? false);

        $of = fn (array $types) => array_values(array_map(fn ($k) => $k['keyword'], array_filter($kws, fn ($k) => in_array($k['type'], $types, true))));
        $has = fn (string $text, string $kw) => $kw !== '' && mb_stripos($text, $kw) !== false;
        $share = fn (array $words, string $text) => $words ? count(array_filter($words, fn ($w) => $has($text, $w))) / count($words) : null;
        $primary = $of(['PRIMARY']); $content = $of(['PRIMARY', 'SECONDARY']);

        $calc = [
            'title' => function ($p) use ($title, $primary, $has) {
                $len = mb_strlen($title); $tips = [];
                $lf = $len === 0 ? 0 : ($len > $p['max_len'] ? 0.5 : ($len >= $p['min_len'] ? 1 : $len / $p['min_len']));
                if ($len === 0) $tips[] = 'Add a title.'; elseif ($len < $p['min_len']) $tips[] = "Title is {$len} characters; aim for {$p['min_len']}-{$p['max_len']}."; elseif ($len > $p['max_len']) $tips[] = "Title is {$len} characters; over the {$p['max_len']} limit.";
                if (! $primary) { $kf = 0.5; $tips[] = 'Add PRIMARY keywords for this product so the title can be checked.'; }
                else {
                    $front = mb_substr($title, 0, (int) $p['front_chars']);
                    $kf = count(array_filter($primary, fn ($k) => $has($front, $k))) ? 1 : (count(array_filter($primary, fn ($k) => $has($title, $k))) ? 0.6 : 0);
                    if ($kf < 1) $tips[] = $kf > 0 ? "Move a primary keyword into the first {$p['front_chars']} characters." : 'No primary keyword appears in the title.';
                }
                return [0.5 * $lf + 0.5 * $kf, $tips];
            },
            'bullets' => function ($p) use ($bullets, $content, $share) {
                $n = count($bullets); $tips = [];
                $cf = min($n, $p['count']) / max($p['count'], 1);
                $ok = count(array_filter($bullets, fn ($b) => mb_strlen($b) >= $p['min_len'] && mb_strlen($b) <= $p['max_len']));
                $lf = $n ? $ok / $n : 0;
                $kf = $share($content, implode(' ', $bullets)) ?? 0.5;
                if ($n < $p['count']) $tips[] = "{$n} of {$p['count']} bullet points used.";
                if ($n && $ok < $n) $tips[] = ($n - $ok) . " bullet(s) outside {$p['min_len']}-{$p['max_len']} characters.";
                if ($content && $kf < 0.6) $tips[] = 'Work more of your primary / secondary keywords into the bullets.';
                return [0.4 * $cf + 0.4 * $lf + 0.2 * $kf, $tips];
            },
            'description' => function ($p) use ($desc, $content, $has) {
                $len = mb_strlen($desc); $tips = [];
                $lf = $len === 0 ? 0 : ($len > $p['max_len'] ? 0.7 : min($len / $p['min_len'], 1));
                $kf = $content ? (count(array_filter($content, fn ($k) => $has($desc, $k))) ? 1 : 0) : 0.5;
                if ($len < $p['min_len']) $tips[] = "Description is {$len} characters; aim for {$p['min_len']}+."; elseif ($len > $p['max_len']) $tips[] = "Description is over {$p['max_len']} characters.";
                if ($content && $kf === 0) $tips[] = 'No target keyword appears in the description.';
                return [0.7 * $lf + 0.3 * $kf, $tips];
            },
            'keywords' => function ($p) use ($kws, $title, $bullets, $desc, $back, $has) {
                $pool = array_filter($kws, fn ($k) => in_array($k['type'], ['PRIMARY', 'SECONDARY', 'LONG_TAIL', 'BACKEND'], true));   // AEO / SEO / GEO are tracked, not scored
                if (! $pool) return [0, ['No keywords saved for this product on this channel. Add them under Keywords.']];
                $text = $title . ' ' . implode(' ', $bullets) . ' ' . $desc . ' ' . $back; $tw = 0; $got = 0; $missing = [];
                foreach ($pool as $k) { $w = $k['type'] === 'PRIMARY' ? 2 : 1; $tw += $w; if ($has($text, $k['keyword'])) $got += $w; else $missing[] = $k['keyword']; }
                return [$got / $tw, $missing ? ['Missing from the listing: ' . implode(', ', array_slice($missing, 0, 6)) . (count($missing) > 6 ? '…' : '')] : []];
            },
            'images' => function ($p) use ($imgs, $video) {
                $f = 0.85 * min($imgs / max($p['target'], 1), 1) + ($video ? 0.15 : 0); $tips = [];
                if ($imgs < $p['target']) $tips[] = "{$imgs} of {$p['target']} images.";
                if (! $video) $tips[] = 'Add a product video.';
                return [$f, $tips];
            },
            'aplus' => function ($p) use ($aplus) {
                $len = mb_strlen($aplus);
                return [min($len / max($p['min_len'], 1), 1), $len < $p['min_len'] ? ['A+ / enhanced content is missing or short.'] : []];
            },
            'features' => function ($p) use ($feat) {
                $n = count($feat);
                return [min($n / max($p['target'], 1), 1), $n < $p['target'] ? ["{$n} of {$p['target']} special features listed."] : []];
            },
            'backend' => function ($p) use ($back, $title) {
                $bytes = strlen($back); $tips = [];
                $f = $bytes === 0 ? 0 : ($bytes > $p['max_bytes'] ? 0.3 : ($bytes >= $p['min_bytes'] ? 1 : $bytes / $p['min_bytes']));
                if ($bytes === 0) $tips[] = 'No backend search terms.'; elseif ($bytes > $p['max_bytes']) $tips[] = "{$bytes} bytes; the marketplace ignores anything over {$p['max_bytes']}."; elseif ($bytes < $p['min_bytes']) $tips[] = "Only {$bytes} bytes; you can use up to {$p['max_bytes']}.";
                $bw = array_filter(preg_split('/[\s,;]+/u', mb_strtolower($back)), fn ($w) => mb_strlen($w) > 2); $tw = array_flip(preg_split('/[\s,;]+/u', mb_strtolower($title)));
                if ($bw && count(array_filter($bw, fn ($w) => isset($tw[$w]))) / count($bw) > 0.3) { $f *= 0.7; $tips[] = 'Many backend words repeat the title; use different words.'; }
                return [$f, $tips];
            },
        ];

        $breakdown = []; $earned = 0.0; $max = 0.0;
        foreach ($rules as $c => $r) {
            if (! isset($calc[$c])) continue;
            [$frac, $tips] = $calc[$c]($r['params']);
            $pts = round(max(0, min(1, $frac)) * $r['max'], 2); $earned += $pts; $max += $r['max'];
            $breakdown[$c] = ['label' => ListingScoreRule::DEFAULTS[$c][0], 'points' => $pts, 'max' => round($r['max'], 2), 'tips' => $tips];
        }
        return ['total' => $max > 0 ? round($earned / $max * 100, 1) : 0.0, 'breakdown' => $breakdown];
    }
}
