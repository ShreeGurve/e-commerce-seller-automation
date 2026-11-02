<?php
namespace App\Http\Controllers;

use App\Models\{Category, Marketplace, Product};
use App\Services\{ActivityLogger, ReportService};
use App\Support\XlsxWriter;
use Carbon\Carbon;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    private const SCREEN_ROWS = 500;

    public function __construct(private ReportService $reports) {}

    public function index()
    {
        return view('reports.index', ['catalog' => $this->reports->catalog(), 'groups' => ReportService::GROUPS]);
    }

    public function show(Request $request, string $key)
    {
        $data = $this->reports->run($key, $f = $this->filters($request));
        return view('reports.show', ['data' => $data, 'f' => $f, 'shown' => array_slice($data['rows'], 0, self::SCREEN_ROWS), 'total' => count($data['rows']), 'limit' => self::SCREEN_ROWS,
            'marketplaces' => Marketplace::active()->get(), 'products' => Product::orderBy('sku')->get(['id', 'sku', 'name']), 'categories' => Category::orderBy('name')->get(), 'sorts' => ReportService::SORTS]);
    }

    public function print(Request $request, string $key)
    {
        $data = $this->reports->run($key, $f = $this->filters($request));
        return view('reports.print', ['data' => $data, 'f' => $f, 'mp' => $f['marketplace_id'] ? Marketplace::find($f['marketplace_id'])?->name : null, 'by' => $request->user()->name]);
    }

    public function export(Request $request, string $key, string $format)
    {
        $data = $this->reports->run($key, $f = $this->filters($request));
        ActivityLogger::log('report.exported', null, null, ['report' => $key, 'format' => $format, 'rows' => count($data['rows']), 'from' => $f['from'], 'to' => $f['to']]);
        $name = $key . (in_array('date', $data['filters'], true) ? "-{$f['from']}-to-{$f['to']}" : '-' . now()->toDateString());

        if ($format === 'xlsx') {
            $bin = XlsxWriter::build($data['columns'], $data['rows'], $data['title'], $data['totals']);
            return response($bin, 200, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'Content-Disposition' => "attachment; filename=\"{$name}.xlsx\"", 'Content-Length' => strlen($bin)]);
        }
        return response()->streamDownload(function () use ($data) {
            $o = fopen('php://output', 'w');
            fwrite($o, "\xEF\xBB\xBF");                                                  // so Excel reads rupee signs and accents correctly
            fputcsv($o, array_map(fn ($c) => $c[1], $data['columns']), ',', '"', '');
            foreach ($data['rows'] as $r) fputcsv($o, array_map(fn ($c) => self::cell($r[$c[0]] ?? null, $c[2]), $data['columns']), ',', '"', '');
            if ($data['totals']) { $first = true; fputcsv($o, array_map(function ($c) use ($data, &$first) { $v = $data['totals'][$c[0]] ?? null; if ($first) { $first = false; return 'TOTAL'; } return self::cell($v, $c[2]); }, $data['columns']), ',', '"', ''); }
            fclose($o);
        }, "{$name}.csv", ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** numbers stay plain numbers; TEXT starting with = + - @ gets a leading quote so Excel can never run it as a formula */
    public static function cell($v, string $type)
    {
        if ($v === null || $v === '') return '';
        if (in_array($type, ['money', 'ratio'], true) && is_numeric($v)) return number_format((float) $v, 2, '.', '');
        if ($type === 'pct' && is_numeric($v)) return number_format((float) $v, 1, '.', '');
        if ($type === 'int' && is_numeric($v)) return (int) $v;
        $v = (string) $v;
        return preg_match('/^[=+\-@\t\r]/', $v) ? "'" . $v : $v;
    }

    public function daily(Request $request)
    {
        try { $date = Carbon::parse($request->query('date', now()->subDay()->toDateString()))->toDateString(); } catch (\Throwable) { $date = now()->subDay()->toDateString(); }
        if ($date > now()->toDateString()) $date = now()->toDateString();
        return view('reports.daily', ['r' => $this->reports->daily($date, $request->user()), 'date' => $date]);
    }

    private function filters(Request $r): array
    {
        $parse = function ($v) { try { return $v ? Carbon::parse($v)->startOfDay() : null; } catch (\Throwable) { return null; } };
        $to = $parse($r->query('to')) ?? now()->startOfDay(); $from = $parse($r->query('from')) ?? now()->subDays(29)->startOfDay();
        if ($from->gt($to)) [$from, $to] = [$to, $from];
        return ['from' => $from->toDateString(), 'to' => $to->toDateString(), 'marketplace_id' => $r->query('marketplace_id') ? (int) $r->query('marketplace_id') : null,
            'product_id' => $r->query('product_id') ? (int) $r->query('product_id') : null, 'category_id' => $r->query('category_id') ? (int) $r->query('category_id') : null,
            'sort' => array_key_exists($r->query('sort'), ReportService::SORTS) ? $r->query('sort') : 'profit_desc'];
    }
}
