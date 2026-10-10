<?php
namespace App\Services;

use App\Marketplaces\MarketplaceManager;
use App\Models\{DataImport, ImportColumnMapping, Marketplace, User};
use App\Support\FileReader;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ImportService
{
    public function __construct(private MarketplaceManager $markets) {}

    public function upload(UploadedFile $file, Marketplace $mp, string $type, array $options, User $user): DataImport
    {
        $ext = strtolower($file->getClientOriginalExtension());
        $path = $file->store('imports/' . now()->format('Y-m'), 'local');        // PRIVATE disk
        $abs = Storage::disk('local')->path($path);
        try {
            [$headers, $rows] = FileReader::open($abs, $ext);
            $total = 0; foreach ($rows as $_) $total++;
        } catch (\Throwable $e) {
            Storage::disk('local')->delete($path);
            throw ValidationException::withMessages(['file' => $e instanceof \RuntimeException ? $e->getMessage() : 'Could not read this file. Is it a valid CSV?']);
        }
        if (count($headers) < 2 || $total < 1) {
            Storage::disk('local')->delete($path);
            throw ValidationException::withMessages(['file' => 'File has no data rows (first row must be the column headings).']);
        }
        $import = DataImport::create([
            'marketplace_id' => $mp->id, 'data_type' => $type, 'file_name' => $file->getClientOriginalName(), 'stored_path' => $path,
            'file_hash' => hash_file('sha256', $abs), 'file_size' => filesize($abs), 'status' => 'UPLOADED', 'options' => $options,
            'total_rows' => $total, 'uploaded_by' => $user->id,
        ]);
        ActivityLogger::log('import.uploaded', $import, null, ['file' => $import->file_name, 'type' => $type, 'marketplace' => $mp->name, 'rows' => $total]);
        return $import;
    }

    public function absolutePath(DataImport $i): string { return Storage::disk('local')->path($i->stored_path); }

    /** @return array{0:array,1:array} headers + first $n rows */
    public function preview(DataImport $i, int $n = 3): array
    {
        [$headers, $rows] = FileReader::open($this->absolutePath($i), pathinfo($i->file_name, PATHINFO_EXTENSION));
        $sample = [];
        foreach ($rows as $r) { $sample[] = $r; if (count($sample) >= $n) break; }
        return [$headers, $sample];
    }

    /** Remembered mapping first, then the marketplace adapter's guesses for the rest. Only columns that exist in this file. */
    public function suggestMapping(DataImport $i, array $headers): array
    {
        $saved = ImportColumnMapping::where('marketplace_id', $i->marketplace_id)->where('data_type', $i->data_type)->value('mapping') ?? [];
        $map = array_filter($saved, fn ($col) => in_array($col, $headers, true));
        $guess = $this->markets->for($i->marketplace)->suggestMapping($headers, $i->data_type);
        foreach ($guess as $field => $col) $map[$field] ??= $col;
        return $map;
    }

    public function saveMapping(DataImport $i, array $map, User $user): DataImport
    {
        [$headers] = $this->preview($i, 0);
        $clean = [];
        foreach ($i->fields() as $field => [$label, $required]) {
            $col = $map[$field] ?? '';
            if ($col === '' || $col === null) {
                if ($required) throw ValidationException::withMessages(['mapping' => "\"{$label}\" must be mapped to a column."]);
                continue;
            }
            if (! in_array($col, $headers, true)) throw ValidationException::withMessages(['mapping' => "Column \"{$col}\" is not in the file."]);
            $clean[$field] = $col;
        }
        if ($i->data_type === 'SETTLEMENTS' && ! array_intersect_key($clean, array_flip(['amount', 'sale_amount', 'shipping_credit', 'refund_amount', 'referral_fee', 'closing_fee', 'shipping_fee', 'collection_fee', 'payment_fee', 'other_fee', 'fee_tax', 'reimbursement', 'adjustment']))) {
            throw ValidationException::withMessages(['mapping' => 'Map the "Amount" column (long format) or at least one fee / sale column (one-column-per-item format).']);
        }
        if (count(array_unique($clean)) !== count($clean)) throw ValidationException::withMessages(['mapping' => 'The same file column is mapped to two different fields.']);

        $i->update(['mapping' => $clean, 'status' => 'MAPPED']);
        ImportColumnMapping::updateOrCreate(['marketplace_id' => $i->marketplace_id, 'data_type' => $i->data_type], ['mapping' => $clean, 'updated_by' => $user->id]);
        return $i;
    }
}
