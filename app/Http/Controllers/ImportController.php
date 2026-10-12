<?php
namespace App\Http\Controllers;

use App\Models\{DataImport, Marketplace};
use App\Services\{ImportProcessor, ImportService};
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ImportController extends Controller
{
    public function __construct(private ImportService $service, private ImportProcessor $processor) {}

    public function index()
    {
        return view('imports.index', ['imports' => DataImport::with(['marketplace', 'uploader'])->latest()->paginate(20)]);
    }

    public function create(Request $request)
    {
        return view('imports.create', ['marketplaces' => Marketplace::active()->get(), 'pickType' => $request->query('type'), 'pickMp' => $request->query('marketplace_id')]);
    }

    public function store(Request $request)
    {
        $allowed = ['csv', 'txt', 'tsv', 'xlsx', 'xls'];
        $d = $request->validate([
            'marketplace_id' => 'required|exists:marketplaces,id',
            'data_type' => ['required', Rule::in(array_keys(DataImport::TYPES))],
            'file' => ['required', 'file', 'max:20480', function ($attr, $value, $fail) use ($allowed) {
                if (! in_array(strtolower($value->getClientOriginalExtension()), $allowed, true)) $fail('Upload a .csv, .txt, .tsv, .xlsx or .xls file.');
            }],
            'price_mode' => ['nullable', Rule::in(['unit', 'line'])],
            'date_format' => ['required', Rule::in(array_keys(DataImport::DATE_FORMATS))],
        ]);
        $this->authorizeType($d['data_type']);
        $options = ['date_format' => $d['date_format'], 'price_mode' => $d['price_mode'] ?? 'unit', 'deduct_stock' => $request->boolean('deduct_stock'), 'deductions_positive' => $request->boolean('deductions_positive')];
        $import = $this->service->upload($request->file('file'), Marketplace::findOrFail($d['marketplace_id']), $d['data_type'], $options, $request->user());

        $before = DataImport::where('id', '!=', $import->id)->where('marketplace_id', $import->marketplace_id)->where('data_type', $import->data_type)
            ->where('file_hash', $import->file_hash)->where('status', 'IMPORTED')->first();
        $redirect = redirect()->route('imports.map', $import);
        return $before ? $redirect->with('error', "Heads up: this exact file was already imported on {$before->imported_at?->format('d M Y H:i')}. Re-importing is safe (existing records are skipped), but check you picked the right file.") : $redirect;
    }

    public function map(DataImport $dataImport)
    {
        $this->authorizeType($dataImport->data_type);
        if ($dataImport->status === 'IMPORTED') return redirect()->route('imports.show', $dataImport);
        [$headers, $sample] = $this->service->preview($dataImport, 3);
        $map = $dataImport->mapping ?: $this->service->suggestMapping($dataImport, $headers);
        return view('imports.map', ['import' => $dataImport, 'headers' => $headers, 'sample' => $sample, 'map' => $map]);
    }

    public function saveMap(Request $request, DataImport $dataImport)
    {
        $this->authorizeType($dataImport->data_type);
        abort_if(in_array($dataImport->status, ['IMPORTED', 'RUNNING'], true), 403);
        $this->service->saveMapping($dataImport, (array) $request->input('map', []), $request->user());
        return redirect()->route('imports.preview', $dataImport);
    }

    public function preview(DataImport $dataImport)
    {
        $this->authorizeType($dataImport->data_type);
        if (! in_array($dataImport->status, ['MAPPED', 'FAILED'], true)) return redirect()->route('imports.show', $dataImport);
        return view('imports.preview', ['import' => $dataImport, 's' => $this->processor->run($dataImport, false)]);
    }

    public function run(DataImport $dataImport)
    {
        $this->authorizeType($dataImport->data_type);
        $s = $this->processor->run($dataImport, true);
        return redirect()->route('imports.show', $dataImport)->with('success', "Import finished: {$s['created']} created, {$s['duplicate']} duplicate row(s) skipped, {$s['error']} row(s) with errors.");
    }

    public function show(Request $request, DataImport $dataImport)
    {
        $this->authorizeType($dataImport->data_type);
        $dataImport->load(['marketplace', 'uploader']);
        $filter = $request->query('rows', 'problems');
        $rows = $dataImport->rows()->when($filter === 'problems', fn ($q) => $q->where('status', '!=', 'OK'))
            ->when(in_array($filter, ['OK', 'ERROR', 'DUPLICATE']), fn ($q) => $q->where('status', $filter))->orderBy('row_number')->paginate(50)->withQueryString();
        return view('imports.show', ['import' => $dataImport, 'rows' => $rows, 'filter' => $filter]);
    }

    public function errors(DataImport $dataImport)
    {
        $this->authorizeType($dataImport->data_type);
        [$headers] = $this->service->preview($dataImport, 0);
        $safe = fn ($v) => is_string($v) && preg_match('/^[=+\-@\t\r]/', $v) ? "'" . $v : $v;      // stop Excel formula injection
        return response()->streamDownload(function () use ($dataImport, $headers, $safe) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, array_merge(['Row', 'Status', 'Problem'], $headers), ',', '"', '');
            $dataImport->rows()->where('status', '!=', 'OK')->chunkById(500, function ($chunk) use ($out, $headers, $safe) {
                foreach ($chunk as $r) {
                    $raw = $r->raw ?? [];
                    fputcsv($out, array_merge([$r->row_number, $r->status, $safe($r->message)], array_map(fn ($h) => $safe($raw[$h] ?? ''), $headers)), ',', '"', '');
                }
            });
            fclose($out);
        }, "import-{$dataImport->id}-problems.csv", ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Settlements need finance.manage, ad reports need advertising.manage, everything else orders.edit. */
    private function authorizeType(string $type): void
    {
        $perm = match (true) { $type === 'SETTLEMENTS' => 'finance.manage', str_starts_with($type, 'ADS_') => 'advertising.manage', $type === 'KEYWORDS' => 'listings.manage', default => 'orders.edit' };
        abort_unless(auth()->user()->can($perm), 403);
    }
}
