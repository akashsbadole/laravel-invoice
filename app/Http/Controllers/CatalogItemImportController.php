<?php

namespace App\Http\Controllers;

use App\Models\BusinessSetting;
use App\Models\CatalogItem;
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
    /**
     * Columns the importer understands. Jewelry-only columns are ignored for
     * other trades and generic ones ignored for jewelry, so a shared template
     * works everywhere.
     */
    protected const COLUMNS = [
        'name', 'item_code', 'brand', 'model_number', 'hsn_code',
        'size_label', 'finish', 'grade', 'specification', 'unit_label',
        'metal_type', 'purity', 'rate_type',
        'default_rate', 'default_net_weight', 'default_gross_weight',
        'default_length', 'default_width', 'default_wastage_percent',
        'description', 'is_active',
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
        $usesWeights = Industry::usesWeightFields($industry);
        $isArea = in_array('per_sqft', Industry::rateTypes($industry), true)
            || in_array('per_sqm', Industry::rateTypes($industry), true);
        $fields = Industry::itemFields($industry);

        return array_values(array_filter(self::COLUMNS, function (string $column) use ($industry, $usesWeights, $isArea, $fields): bool {
            if (in_array($column, ['metal_type', 'purity', 'default_net_weight', 'default_gross_weight'], true)) {
                return $usesWeights;
            }

            if (in_array($column, ['default_length', 'default_width', 'default_wastage_percent'], true)) {
                return $isArea;
            }

            if (in_array($column, ['size_label', 'finish', 'grade', 'specification', 'unit_label', 'brand', 'model_number'], true)) {
                return in_array($column, $fields, true) || $column === 'model_number';
            }

            return true;
        }));
    }

    public function template(): StreamedResponse
    {
        abort_unless(request()->user()->role->canWrite(), 403);

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
                    'brand' => 'Your Brand',
                    'hsn_code' => $usesWeights ? '7113' : '6910',
                    'metal_type' => 'Gold',
                    'purity' => '22K',
                    'rate_type' => $defaultRateType,
                    'default_rate' => '50',
                    'default_length' => '60',
                    'default_width' => '60',
                    'is_active' => '1',
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
        abort_unless($request->user()->role->canWrite(), 403);

        $columns = $this->columnsFor(BusinessSetting::current()->industryKey());
        $items = CatalogItem::query()->orderBy('name')->get();

        return ResponseFacade::streamDownload(function () use ($columns, $items) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, $columns);

            foreach ($items as $item) {
                fputcsv($handle, array_map(
                    fn (string $column) => $column === 'rate_type' ? $item->rate_type->value : (string) ($item->{$column} ?? ''),
                    $columns,
                ));
            }

            fclose($handle);
        }, 'catalog-export.csv', $this->csvHeaders());
    }

    public function importCsv(Request $request): RedirectResponse
    {
        abort_unless($request->user()->role->canWrite(), 403);

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

            $itemCode = $data['item_code'] ?: null;
            $existing = $itemCode ? CatalogItem::query()->where('item_code', $itemCode)->first() : null;

            $payload = [
                'name' => $data['name'],
                'item_code' => $itemCode,
                'brand' => $data['brand'] ?: null,
                'model_number' => $data['model_number'] ?: null,
                'hsn_code' => $data['hsn_code'] ?: null,
                'size_label' => $data['size_label'] ?: null,
                'finish' => $data['finish'] ?: null,
                'grade' => $data['grade'] ?: null,
                'specification' => $data['specification'] ?: null,
                'unit_label' => $data['unit_label'] ?: null,
                'metal_type' => $data['metal_type'] ?: null,
                'purity' => $data['purity'] ?: null,
                'rate_type' => $data['rate_type'],
                'default_rate' => $this->numberOrNull($data['default_rate']),
                'default_net_weight' => $this->numberOrNull($data['default_net_weight']),
                'default_gross_weight' => $this->numberOrNull($data['default_gross_weight']),
                'default_length' => $this->numberOrNull($data['default_length']),
                'default_width' => $this->numberOrNull($data['default_width']),
                'default_wastage_percent' => $this->numberOrNull($data['default_wastage_percent']),
                'description' => $data['description'] ?: null,
                'is_active' => $this->booleanOrDefault($data['is_active']),
                'created_by' => $request->user()->id,
            ];

            if ($existing) {
                $existing->update($payload);
                $updated++;
            } else {
                CatalogItem::create($payload);
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

    protected function numberOrNull(?string $value): int|float|null
    {
        return is_numeric($value) ? $value + 0 : null;
    }

    protected function booleanOrDefault(?string $value): bool
    {
        return $value === null || $value === ''
            ? true
            : filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }
}
