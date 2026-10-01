<?php

namespace App\Http\Controllers;

use App\Enums\Permission;
use App\Models\BusinessSetting;
use App\Models\CatalogItem;
use App\Services\InventoryService;
use App\Support\CatalogField;
use App\Support\Industry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response as ResponseFacade;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * CSV import/export for the product catalog. Uses the same industry-aware
 * columns as the catalog form, so an export can be re-imported unchanged.
 */
class CatalogItemImportController extends Controller
{
    public function __construct(private readonly InventoryService $inventory) {}

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
        'image_path', 'attributes', 'is_active',
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

            $sample = array_map(function (string $column) use ($defaultRateType, $usesWeights): string {
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
                    'is_active' => '1',
                    'attributes' => 'thread=2x40;finish=matte',
                    'description' => 'Sample row',
                    default => '',
                };
            }, $columns);

            fputcsv($handle, $sample);
            fclose($handle);
        }, 'catalog-import-template.csv', $this->csvHeaders());
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

            $itemCode = $payload['item_code'];
            $existing = $itemCode ? CatalogItem::query()->where('item_code', $itemCode)->first() : null;
            $payload['created_by'] = $request->user()->id;

            $submittedStock = (float) ($payload['stock_quantity'] ?? 0);
            $payload['stock_quantity'] = 0;

            if ($existing) {
                $existing->update($payload);

                if ($payload['stock_tracked'] && $submittedStock !== (float) $existing->stock_quantity) {
                    $this->inventory->setOpeningStock($existing, $submittedStock, $request->user()->id);
                }

                $updated++;
            } else {
                $item = CatalogItem::create($payload);

                if ($payload['stock_tracked'] && $submittedStock !== 0.0) {
                    $this->inventory->setOpeningStock($item, $submittedStock, $request->user()->id);
                }

                $created++;
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

        if ($column === 'is_active') {
            return $item->is_active ? '1' : '0';
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
            // Importing is an explicit act, so a product with no is_active
            // column is treated as active.
            'is_active' => $this->booleanOrDefault($data['is_active'] ?? null),
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
                'boolean' => $this->booleanOrDefault($value, $name === 'is_active'),
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
