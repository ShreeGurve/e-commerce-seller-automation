<?php
namespace App\Support;

use ZipArchive;

/**
 * Minimal, dependency-free .xlsx writer (opens in Excel, Google Sheets, LibreOffice).
 * Text is stored as plain text, so a cell like =HYPERLINK(...) can never run as a formula.
 * Column types: 'text' | 'int' | 'money' (2 decimals) | 'ratio' (2 decimals) | 'pct' (1 decimal) | 'date'
 */
class XlsxWriter
{
    /** @param array<int,array{0:string,1:string,2:string}> $columns [key,label,type]   @param array<int,array<string,mixed>> $rows keyed by column key   @param ?array<string,mixed> $totals */
    public static function build(array $columns, array $rows, string $sheetName = 'Report', ?array $totals = null): string
    {
        $sheet = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>';
        $widths = [];
        foreach ($columns as $i => $c) $widths[$i] = min(max(mb_strlen($c[1]) + 2, 10), 50);
        $all = $rows; if ($totals) $all[] = $totals;
        foreach ($all as $r) foreach ($columns as $i => $c) { $len = mb_strlen((string) ($r[$c[0]] ?? '')) + 2; if ($len > $widths[$i]) $widths[$i] = min($len, 50); }
        $sheet .= '<cols>';
        foreach ($widths as $i => $w) $sheet .= '<col min="' . ($i + 1) . '" max="' . ($i + 1) . '" width="' . $w . '" customWidth="1"/>';
        $sheet .= '</cols><sheetData>';

        $sheet .= '<row r="1">';
        foreach ($columns as $i => $c) $sheet .= self::text(self::ref($i, 1), $c[1], 1);
        $sheet .= '</row>';
        $n = 2;
        foreach ($rows as $r) { $sheet .= self::row($columns, $r, $n++, false); }
        if ($totals) $sheet .= self::row($columns, $totals, $n, true);
        $sheet .= '</sheetData></worksheet>';

        $name = htmlspecialchars(mb_substr(preg_replace('/[\[\]:*?\/\\\\]/', ' ', $sheetName) ?: 'Report', 0, 31), ENT_XML1);
        $files = [
            '[Content_Types].xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>',
            '_rels/.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>',
            'xl/workbook.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="' . $name . '" sheetId="1" r:id="rId1"/></sheets></workbook>',
            'xl/_rels/workbook.xml.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>',
            // 0 normal | 1 bold header | 2 money | 3 pct | 4 bold money (totals) | 5 bold text (totals)
            'xl/styles.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><numFmts count="2"><numFmt numFmtId="164" formatCode="#,##0.00"/><numFmt numFmtId="165" formatCode="0.0&quot;%&quot;"/></numFmts><fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts><fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FFE3E6ED"/></patternFill></fill></fills><borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="6"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"/><xf numFmtId="164" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/><xf numFmtId="165" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/><xf numFmtId="164" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1" applyNumberFormat="1"/><xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/></cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>',
            'xl/worksheets/sheet1.xml' => $sheet,
        ];
        $tmp = tempnam(sys_get_temp_dir(), 'xlsx');
        $zip = new ZipArchive();
        if ($zip->open($tmp, ZipArchive::OVERWRITE) !== true) throw new \RuntimeException('Could not create the Excel file (is the PHP zip extension enabled?).');
        foreach ($files as $path => $xml) $zip->addFromString($path, $xml);
        $zip->close();
        $bin = file_get_contents($tmp); @unlink($tmp);
        return $bin;
    }

    private static function row(array $columns, array $r, int $n, bool $total): string
    {
        $x = '<row r="' . $n . '">';
        foreach ($columns as $i => [$key, , $type]) {
            $v = $r[$key] ?? null; $ref = self::ref($i, $n);
            if ($v === null || $v === '') { if ($i === 0 && $total) $x .= self::text($ref, 'TOTAL', 5); continue; }
            if (in_array($type, ['int', 'money', 'pct', 'ratio'], true) && is_numeric($v)) {
                $style = in_array($type, ['money', 'ratio'], true) ? ($total ? 4 : 2) : ($type === 'pct' ? 3 : ($total ? 4 : 0));
                $x .= '<c r="' . $ref . '" s="' . $style . '"><v>' . (0 + $v) . '</v></c>';
            } else $x .= self::text($ref, (string) $v, $total ? 5 : 0);
        }
        return $x . '</row>';
    }

    private static function text(string $ref, string $v, int $style): string
    {
        $v = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $v);        // characters XML cannot hold
        return '<c r="' . $ref . '" s="' . $style . '" t="inlineStr"><is><t xml:space="preserve">' . htmlspecialchars($v, ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</t></is></c>';
    }

    private static function ref(int $col, int $row): string
    {
        $s = ''; $c = $col + 1;
        while ($c > 0) { $m = ($c - 1) % 26; $s = chr(65 + $m) . $s; $c = intdiv($c - 1, 26); }
        return $s . $row;
    }
}
