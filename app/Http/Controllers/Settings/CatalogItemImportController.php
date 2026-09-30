<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\CatalogItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response as ResponseFacade;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CatalogItemImportController extends Controller
{
    protected const EXPECTED_COLUMNS = [
        'name', 'item_code', 'hsn_code', 'metal_type', 'purity',
        'rate_type', 'default_rate', 'default_net_weight', 'default_gross_weight', 'description',
    ];

    protected const VALID_RATE_TYPES = ['per_gram', 'per_carat', 'per_piece', 'fixed'];

    public function template(): StreamedResponse
    {
        abort_unless(request()->user()->role->canWrite(), 403);

        return ResponseFacade::streamDownload(function () {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, self::EXPECTED_COLUMNS);
            fputcsv($handle, [
                'Gold Ring 22K', 'RG-001', '7113', 'Gold', '22K',
                'per_gram', '6200', '', '', 'Classic band design',
            ]);
            fclose($handle);
        }, 'catalog-import-template.csv');
    }

    public function importCsv(Request $request): RedirectResponse
    {
        abort_unless($request->user()->role->canWrite(), 403);

        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:2048'],
        ]);

        $handle = fopen($request->file('file')->getRealPath(), 'r');
        $header = fgetcsv($handle);

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

        while (($row = fgetcsv($handle)) !== false) {
            $rowNumber++;
            $data = $this->extractRow($row, $columnIndex);

            if (blank($data['name'] ?? null)) {
                $skipped[] = "Row {$rowNumber}: missing name";

                continue;
            }

            $data['rate_type'] = in_array($data['rate_type'] ?? null, self::VALID_RATE_TYPES, true)
                ? $data['rate_type']
                : 'per_gram';

            $itemCode = $data['item_code'] ?: null;
            $existing = $itemCode ? CatalogItem::query()->where('item_code', $itemCode)->first() : null;

            $payload = [
                'name' => $data['name'],
                'item_code' => $itemCode,
                'hsn_code' => $data['hsn_code'] ?: null,
                'metal_type' => $data['metal_type'] ?: null,
                'purity' => $data['purity'] ?: null,
                'rate_type' => $data['rate_type'],
                'default_rate' => is_numeric($data['default_rate'] ?? null) ? $data['default_rate'] : null,
                'default_net_weight' => is_numeric($data['default_net_weight'] ?? null) ? $data['default_net_weight'] : null,
                'default_gross_weight' => is_numeric($data['default_gross_weight'] ?? null) ? $data['default_gross_weight'] : null,
                'description' => $data['description'] ?: null,
                'is_active' => true,
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
     * @param  array<int,string>  $header
     * @return array<string,int>
     */
    protected function mapHeader(array $header): array
    {
        $map = [];
        foreach ($header as $i => $rawName) {
            $key = strtolower(trim(str_replace(' ', '_', (string) $rawName)));
            if (in_array($key, self::EXPECTED_COLUMNS, true)) {
                $map[$key] = $i;
            }
        }

        return $map;
    }

    /**
     * @param  array<int,string>  $row
     * @param  array<string,int>  $columnIndex
     * @return array<string,string|null>
     */
    protected function extractRow(array $row, array $columnIndex): array
    {
        $data = [];
        foreach ($columnIndex as $column => $index) {
            $data[$column] = isset($row[$index]) ? trim((string) $row[$index]) : null;
        }

        return $data;
    }
}
