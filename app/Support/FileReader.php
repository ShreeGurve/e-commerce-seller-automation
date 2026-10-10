<?php
namespace App\Support;

/** Reads CSV / TSV / TXT natively (BOM, delimiter and Windows-1252 handled). Excel needs: composer require phpoffice/phpspreadsheet */
class FileReader
{
    public const MAX_ROWS = 20000;

    /** @return array{0:array<int,string>,1:\Generator} headers + generator of [rowNumber => [header => value]] */
    public static function open(string $path, string $ext): array
    {
        $table = in_array(strtolower($ext), ['xlsx', 'xls'], true) ? self::excel($path) : self::csv($path);
        $headers = self::cleanHeaders(array_shift($table) ?? []);
        $gen = (function () use ($table, $headers) {
            $n = 1;
            foreach ($table as $cells) {
                $n++;
                $cells = array_map(fn ($c) => trim((string) $c), $cells);
                if (! array_filter($cells, fn ($c) => $c !== '')) continue;          // skip blank lines
                $cells = array_slice(array_pad($cells, count($headers), ''), 0, count($headers));
                yield $n => array_combine($headers, $cells);
            }
        })();
        return [$headers, $gen];
    }

    private static function csv(string $path): array
    {
        $s = file_get_contents($path);
        if (str_starts_with($s, "\xEF\xBB\xBF")) $s = substr($s, 3);
        if (! mb_check_encoding($s, 'UTF-8')) $s = mb_convert_encoding($s, 'UTF-8', 'Windows-1252');
        $first = strtok($s, "\n") ?: '';
        $delim = ',';
        $best = -1;
        foreach ([',', "\t", ';', '|'] as $d) { $c = substr_count($first, $d); if ($c > $best) { $best = $c; $delim = $d; } }
        $h = fopen('php://temp/maxmemory:33554432', 'r+');
        fwrite($h, $s); rewind($h);
        $rows = [];
        while (($r = fgetcsv($h, 0, $delim, '"', '')) !== false) {
            $rows[] = $r;
            if (count($rows) > self::MAX_ROWS + 1) throw new \RuntimeException('File has more than ' . number_format(self::MAX_ROWS) . ' rows. Please split it into smaller files.');
        }
        fclose($h);
        return $rows;
    }

    private static function excel(string $path): array
    {
        if (! class_exists(\PhpOffice\PhpSpreadsheet\IOFactory::class)) {
            throw new \RuntimeException('Excel files need the PhpSpreadsheet package (run: composer require phpoffice/phpspreadsheet). Or save the sheet as CSV and upload that.');
        }
        $rows = \PhpOffice\PhpSpreadsheet\IOFactory::load($path)->getActiveSheet()->toArray(null, true, true, false);
        if (count($rows) > self::MAX_ROWS + 1) throw new \RuntimeException('File has more than ' . number_format(self::MAX_ROWS) . ' rows. Please split it into smaller files.');
        return $rows;
    }

    private static function cleanHeaders(array $h): array
    {
        $out = []; $seen = [];
        foreach ($h as $i => $name) {
            $name = trim((string) $name) ?: 'Column ' . ($i + 1);
            $seen[$name] = ($seen[$name] ?? 0) + 1;
            $out[] = $seen[$name] > 1 ? "{$name} ({$seen[$name]})" : $name;
        }
        return $out;
    }
}
