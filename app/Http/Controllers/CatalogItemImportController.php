<?php

namespace App\Http\Controllers;

use App\Enums\CatalogStatus;
use App\Enums\Permission;
use App\Models\BusinessSetting;
use App\Models\CatalogItem;
use App\Services\InventoryService;
use App\Support\CatalogField;
use App\Support\Industry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Response as ResponseFacade;
use Illuminate\Support\Str;
use Inertia\Inertia;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * CSV and Excel import/export for the product catalog. Uses the same
 * industry-aware columns as the catalog form, so an export can be
 * re-imported unchanged.
 */
class CatalogItemImportController extends Controller
{
    public function __construct(private readonly InventoryService $inventory) {}

    /** Preview rows are cached briefly so the review page can edit them. */
    protected const PREVIEW_TTL_HOURS = 2;

    /** Hard ceiling so one upload cannot fill the cache or the browser. */
    protected const PREVIEW_MAX_ROWS = 1000;

    /**
     * Columns the importer understands, straight from the field registry so
     * the CSV can never offer a column the model does not have. The
     * `attributes` column is serialised as `key=value;key2=value2`.
     */
    protected const COLUMNS = [
        'name', 'item_code', 'barcode', 'brand', 'model_number',
        'manufacturer', 'country_of_origin', 'hsn_code', 'description',
        'rate_type', 'default_rate', 'cost_price', 'tax_inclusive',
        'minimum_order_quantity', 'pack_size', 'unit_label',
        'metal_type', 'purity', 'size_label', 'finish', 'grade', 'color',
        'material', 'thickness', 'specification', 'warranty_months',
        'default_net_weight', 'default_gross_weight',
        'default_length', 'default_width', 'default_wastage_percent',
        'stock_tracked', 'stock_quantity', 'reorder_level', 'stock_unit',
        'image_path', 'attributes', 'status',
    ];

    /**
     * Only emit columns this industry can actually store, so a shared
     * template never asks for jewelry-only weight columns on a tiles
     * catalog.
     *
     * @return list<string>
     */
    protected function columnsFor(string $industry): array
    {
        return CatalogField::names($industry);
    }

    public function template(): StreamedResponse
    {
        abort_unless(request()->user()->canDo(Permission::ManageCatalog), 403);

        $industry = BusinessSetting::current()->industryKey();
        $usesWeights = Industry::usesWeightFields($industry);
        $columns = $this->columnsFor($industry);
        $defaultRateType = Industry::rateTypes($industry)[0] ?? 'per_piece';

        return ResponseFacade::streamDownload(function () use ($columns, $defaultRateType, $usesWeights) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, $columns);
            fputcsv($handle, $this->sampleRow($columns, $defaultRateType, $usesWeights));
            fclose($handle);
        }, 'catalog-import-template.csv', $this->csvHeaders());
    }

    /**
     * The example row the template ships with, keyed to the same columns
     * the header uses so a spreadsheet can show a realistic fill-in.
     *
     * @param  list<string>  $columns
     * @return list<string>
     */
    protected function sampleRow(array $columns, string $defaultRateType, bool $usesWeights): array
    {
        return array_map(function (string $column) use ($defaultRateType, $usesWeights): string {
            return match ($column) {
                'name' => $usesWeights ? 'Gold Ring 22K' : 'Product sample',
                'item_code' => 'SKU-001',
                'barcode' => '8901234567890',
                'brand' => 'Your Brand',
                'hsn_code' => $usesWeights ? '7113' : '6910',
                'metal_type' => 'Gold',
                'purity' => '22K',
                'rate_type' => $defaultRateType,
                'default_rate' => '50',
                'cost_price' => '35',
                'minimum_order_quantity' => '1',
                'pack_size' => 'Box of 12',
                'length' => '60',
                'default_length' => '60',
                'default_width' => '60',
                'stock_tracked' => '1',
                'stock_quantity' => '0',
                'reorder_level' => '0',
                'status' => 'active',
                'attributes' => 'thread=2x40;finish=matte',
                'description' => 'Sample row',
                default => '',
            };
        }, $columns);
    }

    /**
     * Symfony's streamDownload defaults to text/html; a CSV should declare
     * itself so Excel and the browser handle it correctly.
     *
     * @return array<string, string>
     */
    protected function csvHeaders(): array
    {
        return [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store, no-cache',
        ];
    }

    /**
     * Export the whole catalog using the same columns as the import template,
     * so an export can be edited and re-imported without reformatting.
     */
    public function exportCsv(Request $request): StreamedResponse
    {
        abort_unless($request->user()->canDo(Permission::ManageCatalog), 403);

        $columns = $this->columnsFor(BusinessSetting::current()->industryKey());
        $items = CatalogItem::query()->orderBy('name')->get();

        return ResponseFacade::streamDownload(function () use ($columns, $items) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, $columns);

            foreach ($items as $item) {
                fputcsv($handle, array_map(
                    fn (string $column) => $this->exportValue($item, $column),
                    $columns,
                ));
            }

            fclose($handle);
        }, 'catalog-export.csv', $this->csvHeaders());
    }

    public function importCsv(Request $request): RedirectResponse
    {
        abort_unless($request->user()->canDo(Permission::ManageCatalog), 403);

        // Validate on the uploaded filename rather than the browser-reported
        // MIME type: Excel sends .csv files as application/vnd.ms-excel, which
        // a `mimes:csv` rule rejects outright.
        $request->validate([
            'file' => ['required', 'file', 'extensions:csv,txt', 'max:2048'],
        ]);

        $industry = BusinessSetting::current()->industryKey();
        $allowedRateTypes = Industry::rateTypes($industry);
        $defaultRateType = $allowedRateTypes[0] ?? 'per_piece';

        $handle = fopen($request->file('file')->getRealPath(), 'r');
        $header = $this->readRow($handle);

        if (! $header) {
            fclose($handle);

            return back()->withErrors(['file' => 'The file appears to be empty.']);
        }

        $columnIndex = $this->mapHeader($header);

        if (! isset($columnIndex['name'])) {
            fclose($handle);

            return back()->withErrors(['file' => 'The CSV must include a "name" column.']);
        }

        $created = 0;
        $updated = 0;
        $skipped = [];
        $rowNumber = 1;

        while (($row = $this->readRow($handle)) !== false) {
            $rowNumber++;
            $data = $this->extractRow($row, $columnIndex);

            if (blank($data['name'])) {
                $skipped[] = "Row {$rowNumber}: missing name";

                continue;
            }

            $data['rate_type'] = in_array($data['rate_type'], $allowedRateTypes, true)
                ? $data['rate_type']
                : $defaultRateType;

            $payload = $this->payloadFrom($data, $allowedRateTypes, $defaultRateType);
            $payload['created_by'] = $request->user()->id;

            if ($this->upsertRow($payload, $request->user()->id) === 'created') {
                $created++;
            } else {
                $updated++;
            }
        }

        fclose($handle);

        $message = "Imported: {$created} added, {$updated} updated.";
        if ($skipped) {
            $message .= ' Skipped '.count($skipped).' row(s).';
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return back();
    }

    /**
     * Create or update one catalog row from an import payload.
     *
     * Rows carrying an item_code update the product it names, so re-importing
     * an export edits instead of duplicating. Stock always travels through
     * the movement ledger, never as a bare column overwrite.
     *
     * @param  array<string,mixed>  $payload
     * @return 'created'|'updated'
     */
    protected function upsertRow(array $payload, int $userId): string
    {
        $itemCode = $payload['item_code'] ?? null;
        $existing = $itemCode ? CatalogItem::query()->where('item_code', $itemCode)->first() : null;

        $submittedStock = (float) ($payload['stock_quantity'] ?? 0);
        $payload['stock_quantity'] = 0;

        if ($existing) {
            $existing->update($payload);

            if ($payload['stock_tracked'] && $submittedStock !== (float) $existing->stock_quantity) {
                $this->inventory->setOpeningStock($existing, $submittedStock, $userId);
            }

            return 'updated';
        }

        $item = CatalogItem::create($payload);

        if ($payload['stock_tracked'] && $submittedStock !== 0.0) {
            $this->inventory->setOpeningStock($item, $submittedStock, $userId);
        }

        return 'created';
    }

    /**
     * Excel (.xlsx) version of the import template.
     */
    public function excelTemplate(): StreamedResponse
    {
        abort_unless(request()->user()->canDo(Permission::ManageCatalog), 403);

        $industry = BusinessSetting::current()->industryKey();
        $usesWeights = Industry::usesWeightFields($industry);
        $columns = $this->columnsFor($industry);
        $defaultRateType = Industry::rateTypes($industry)[0] ?? 'per_piece';

        $spreadsheet = $this->spreadsheetFrom([
            $columns,
            $this->sampleRow($columns, $defaultRateType, $usesWeights),
        ]);
        $spreadsheet->getActiveSheet()->getStyle('A1')->getFont()->setBold(true);

        return $this->xlsxDownload($spreadsheet, 'catalog-import-template.xlsx');
    }

    /**
     * Export the whole catalog as an editable Excel workbook.
     */
    public function exportExcel(Request $request): StreamedResponse
    {
        abort_unless($request->user()->canDo(Permission::ManageCatalog), 403);

        $columns = $this->columnsFor(BusinessSetting::current()->industryKey());
        $items = CatalogItem::query()->orderBy('name')->get();

        $rows = [$columns];

        foreach ($items as $item) {
            $rows[] = array_map(
                fn (string $column) => $this->exportValue($item, $column),
                $columns,
            );
        }

        return $this->xlsxDownload($this->spreadsheetFrom($rows), 'catalog-export.xlsx');
    }

    /**
     * Parse an uploaded workbook into an editable preview the user can fix
     * (add, edit, delete rows) before anything touches the catalog.
     */
    public function excelPreviewUpload(Request $request): RedirectResponse
    {
        abort_unless($request->user()->canDo(Permission::ManageCatalog), 403);

        $request->validate([
            'file' => ['required', 'file', 'extensions:xlsx,xls,csv', 'max:10240'],
        ]);

        try {
            $spreadsheet = IOFactory::load($request->file('file')->getRealPath());
        } catch (\Throwable) {
            return back()->withErrors(['file' => 'That file could not be read. Upload an .xlsx, .xls or .csv file.']);
        }

        $grid = $spreadsheet->getActiveSheet()->toArray(null, true, false, false);
        $spreadsheet->disconnectWorksheets();

        if (blank($grid)) {
            return back()->withErrors(['file' => 'The file appears to be empty.']);
        }

        $header = array_map(
            fn ($value) => is_scalar($value) ? (string) $value : '',
            array_shift($grid),
        );
        $columnIndex = $this->mapHeader($header);

        if (! isset($columnIndex['name'])) {
            return back()->withErrors(['file' => 'The spreadsheet must include a "name" column.']);
        }

        $rows = [];
        $truncated = false;
        $rowNumber = 1;

        foreach ($grid as $gridRow) {
            $rowNumber++;
            $data = $this->extractRow(array_values($gridRow), $columnIndex);

            if (! $this->rowHasContent($data)) {
                continue;
            }

            if (count($rows) >= self::PREVIEW_MAX_ROWS) {
                $truncated = true;

                break;
            }

            $rows[] = $data;
        }

        if ($rows === []) {
            return back()->withErrors(['file' => 'No data rows were found in the file.']);
        }

        $token = Str::random(40);
        Cache::put(
            $this->previewKey($token),
            ['rows' => $rows, 'truncated' => $truncated],
            now()->addHours(self::PREVIEW_TTL_HOURS),
        );

        return redirect()->route('catalog.excel-preview', ['token' => $token]);
    }

    /**
     * The review page itself: rows come from the cache so the user can add,
     * edit and delete them before importing.
     */
    public function excelPreview(Request $request)
    {
        abort_unless($request->user()->canDo(Permission::ManageCatalog), 403);

        $token = (string) $request->query('token');
        $preview = $token !== '' ? Cache::get($this->previewKey($token)) : null;

        if (! is_array($preview) || ! isset($preview['rows'])) {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'That import preview has expired. Upload the file again.']);

            return redirect()->route('catalog.index');
        }

        $industry = BusinessSetting::current()->industryKey();

        return Inertia::render('catalog/excel-preview', [
            'token' => $token,
            'columns' => $this->columnsFor($industry),
            'rows' => $preview['rows'],
            'truncated' => (bool) ($preview['truncated'] ?? false),
            'rateTypes' => Industry::rateTypes($industry),
            'statuses' => array_map(
                fn (CatalogStatus $status) => ['value' => $status->value, 'label' => $status->label()],
                CatalogStatus::cases(),
            ),
        ]);
    }

    /**
     * Import the (possibly edited) preview rows for real.
     */
    public function importExcel(Request $request): RedirectResponse
    {
        abort_unless($request->user()->canDo(Permission::ManageCatalog), 403);

        $request->validate([
            'token' => ['required', 'string'],
            'rows' => ['required', 'array', 'min:1', 'max:'.(self::PREVIEW_MAX_ROWS + 1)],
            'rows.*.name' => ['nullable', 'string', 'max:255'],
        ]);

        // Validate() only returns rule-matched keys, and a preview row
        // carries every catalog column — read the raw payload instead.
        $token = (string) $request->input('token');
        $rawRows = $request->input('rows', []);

        $industry = BusinessSetting::current()->industryKey();
        $allowedRateTypes = Industry::rateTypes($industry);
        $defaultRateType = $allowedRateTypes[0] ?? 'per_piece';

        $created = 0;
        $updated = 0;
        $skipped = 0;

        foreach ($rawRows as $rawRow) {
            if (! is_array($rawRow)) {
                $skipped++;

                continue;
            }

            $data = array_fill_keys(self::COLUMNS, null);

            foreach ($rawRow as $key => $value) {
                if (in_array($key, self::COLUMNS, true) && is_scalar($value)) {
                    $data[$key] = trim((string) $value);
                }
            }

            if (blank($data['name'])) {
                $skipped++;

                continue;
            }

            $payload = $this->payloadFrom($data, $allowedRateTypes, $defaultRateType);
            $payload['created_by'] = $request->user()->id;

            if ($this->upsertRow($payload, $request->user()->id) === 'created') {
                $created++;
            } else {
                $updated++;
            }
        }

        Cache::forget($this->previewKey($token));

        $message = "Imported: {$created} added, {$updated} updated.";
        if ($skipped > 0) {
            $message .= " Skipped {$skipped} row(s) without a name.";
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return redirect()->route('catalog.index');
    }

    /**
     * Whether a parsed row holds anything worth previewing, so blank spacer
     * rows Excel loves to save do not clutter the review table.
     *
     * @param  array<string,string|null>  $data
     */
    protected function rowHasContent(array $data): bool
    {
        foreach ($data as $value) {
            if (filled($value)) {
                return true;
            }
        }

        return false;
    }

    protected function previewKey(string $token): string
    {
        return "catalog-excel-preview:{$token}";
    }

    /**
     * Build a one-sheet workbook from ordered rows (header first).
     *
     * @param  list<list<string>>  $rows
     */
    protected function spreadsheetFrom(array $rows): Spreadsheet
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Catalog');
        $sheet->fromArray($rows, null, 'A1');

        return $spreadsheet;
    }

    protected function xlsxDownload(Spreadsheet $spreadsheet, string $filename): StreamedResponse
    {
        return ResponseFacade::streamDownload(function () use ($spreadsheet) {
            (new Xlsx($spreadsheet))->save('php://output');
            $spreadsheet->disconnectWorksheets();
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'no-store, no-cache',
        ]);
    }

    /**
     * Read one CSV record. Spelled out rather than relying on the ini
     * defaults, which are deprecated to leave implicit in PHP 8.4+.
     *
     * @param  resource  $handle
     * @return array<int,string|null>|false
     */
    protected function readRow($handle): array|false
    {
        return fgetcsv($handle, 0, ',', '"', '\\');
    }

    /**
     * @param  array<int,string>  $header
     * @return array<string,int>
     */
    protected function mapHeader(array $header): array
    {
        $map = [];

        foreach ($header as $i => $rawName) {
            // Excel prefixes a UTF-8 BOM to the first cell, which would
            // otherwise turn "name" into an unrecognised column.
            $name = preg_replace('/^\xEF\xBB\xBF/', '', (string) $rawName);
            $key = strtolower(trim(str_replace(' ', '_', $name)));

            if (in_array($key, self::COLUMNS, true)) {
                $map[$key] = $i;
            }
        }

        return $map;
    }

    /**
     * Every known column is present in the result, so the row payload can be
     * built without probing for keys a hand-written CSV may have omitted.
     *
     * @param  array<int,string|null>  $row
     * @param  array<string,int>  $columnIndex
     * @return array<string,string|null>
     */
    protected function extractRow(array $row, array $columnIndex): array
    {
        $data = array_fill_keys(self::COLUMNS, null);

        foreach ($columnIndex as $column => $index) {
            $data[$column] = isset($row[$index]) ? trim((string) $row[$index]) : null;
        }

        return $data;
    }

    /**
     * One cell for the export. Attributes are flattened so the round trip
     * through the importer is lossless.
     */
    protected function exportValue(CatalogItem $item, string $column): string
    {
        if ($column === 'rate_type') {
            return $item->rate_type?->value ?? '';
        }

        if ($column === 'attributes') {
            return $this->stringifyAttributes($item->attributes);
        }

        if ($column === 'status') {
            return $item->status?->value ?? CatalogStatus::Active->value;
        }

        $value = $item->{$column} ?? '';

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        return (string) $value;
    }

    protected function numberOrNull(?string $value): int|float|null
    {
        return is_numeric($value) ? $value + 0 : null;
    }

    protected function booleanOrDefault(?string $value, bool $default = true): bool
    {
        if ($value === null || trim($value) === '') {
            return $default;
        }

        return filter_var(trim($value), FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Map a raw CSV cell to a valid CatalogStatus, falling back to active
     * when the cell is blank or holds an unrecognised value.
     */
    protected function statusFrom(?string $value): string
    {
        if ($value === null || trim($value) === '') {
            return CatalogStatus::Active->value;
        }

        $normalized = strtolower(trim($value));

        // Map older/common spreadsheet labels to the supported lifecycle.
        if ($normalized === 'archived' || $normalized === 'disabled') {
            return CatalogStatus::Discontinued->value;
        }

        return CatalogStatus::tryFrom($normalized)
            ? $normalized
            : CatalogStatus::Active->value;
    }

    /**
     * Turn one extracted CSV row into model attributes, applying the same
     * coercion rules the form uses.
     *
     * @param  array<string,string|null>  $data
     * @param  list<string>  $allowedRateTypes
     * @return array<string,mixed>
     */
    protected function payloadFrom(array $data, array $allowedRateTypes, string $defaultRateType): array
    {
        $payload = [
            'rate_type' => in_array($data['rate_type'], $allowedRateTypes, true)
                ? $data['rate_type']
                : $defaultRateType,
            'attributes' => $this->parseAttributes($data['attributes'] ?? null),
            // Importing is an explicit act, so a product with no status column
            // is treated as active. Valid values: draft, active, inactive, discontinued.
            'status' => $this->statusFrom($data['status'] ?? null),
            'tax_inclusive' => $this->booleanOrDefault($data['tax_inclusive'] ?? null, false),
            'stock_tracked' => $this->booleanOrDefault($data['stock_tracked'] ?? null, false),
        ];

        foreach (CatalogField::all() as $field) {
            $name = $field['name'];

            // Already handled above or by the caller.
            if (in_array($name, ['rate_type', 'attributes', 'item_code'], true)) {
                continue;
            }

            $value = $data[$name] ?? null;

            $payload[$name] = match ($field['type']) {
                // Fall back to the registry default: several numeric columns
                // are NOT NULL, so a blank CSV cell must not write null.
                'number' => $this->numberOrNull($value) ?? ($field['default'] ?? null),
                'boolean' => $this->booleanOrDefault($value, false),
                default => filled($value) ? $value : null,
            };
        }

        $payload['item_code'] = $data['item_code'] ?: null;

        return $payload;
    }

    /**
     * Read the `key=value;key2=value2` attributes column.
     *
     * @return array<string,string>
     */
    protected function parseAttributes(?string $value): array
    {
        if (blank($value)) {
            return [];
        }

        $attributes = [];

        foreach (explode(';', $value) as $pair) {
            if (! str_contains($pair, '=')) {
                continue;
            }

            [$key, $val] = explode('=', $pair, 2);
            $key = trim($key);
            $val = trim($val);

            if ($key !== '' && $val !== '') {
                $attributes[$key] = $val;
            }
        }

        return $attributes;
    }

    /**
     * Render the attributes array back into one CSV cell.
     *
     * @param  array<string,mixed>|null  $attributes
     */
    protected function stringifyAttributes(?array $attributes): string
    {
        if (! $attributes) {
            return '';
        }

        $pairs = [];

        foreach ($attributes as $key => $value) {
            if (is_scalar($value) && (string) $value !== '') {
                $pairs[] = $key.'='.(string) $value;
            }
        }

        return implode(';', $pairs);
    }
}
