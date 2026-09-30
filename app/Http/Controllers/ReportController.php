<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\User;
use App\Services\ReportService;
use App\Support\XlsxWriter;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class ReportController extends Controller
{
    public function index(Request $request, ReportService $reports): Response
    {
        $filters = $this->filters($request);
        $type = (string) $request->query('type', 'invoices');

        return Inertia::render('reports', [
            'report' => $reports->build($type, $filters),
            'types' => collect(ReportService::TYPES)
                ->map(fn ($label, $key) => ['value' => $key, 'label' => $label])->values(),
            'filters' => [
                'type' => array_key_exists($type, ReportService::TYPES) ? $type : 'invoices',
                'from' => $filters['from'] ?? '',
                'to' => $filters['to'] ?? '',
                'customer_id' => $filters['customer_id'] ?? 'all',
                'staff_id' => $filters['staff_id'] ?? 'all',
                'status' => $filters['status'] ?? 'all',
            ],
            'customers' => Customer::query()->orderBy('full_name')->get(['id', 'full_name']),
            'staff' => User::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function download(Request $request, ReportService $reports): SymfonyResponse
    {
        $request->validate(['format' => ['required', 'in:csv,xlsx,pdf']]);

        $filters = $this->filters($request);
        $report = $reports->build((string) $request->query('type', 'invoices'), $filters);
        $basename = Str::slug($report['title']).'-'.now()->format('Ymd');

        return match ($request->query('format')) {
            'xlsx' => $this->xlsx($report, $basename),
            'pdf' => Pdf::loadView('pdf.report', ['report' => $report, 'filters' => $filters])
                ->setPaper('a4', 'landscape')
                ->download("{$basename}.pdf"),
            default => $this->csv($report, $basename),
        };
    }

    /**
     * @param  array<string,mixed>  $report
     */
    protected function csv(array $report, string $basename): SymfonyResponse
    {
        return response()->streamDownload(function () use ($report) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel reads ₹ / accents correctly
            fputcsv($out, array_column($report['columns'], 'label'));

            $line = function (array $row) use ($report) {
                return array_map(function ($col) use ($row) {
                    $value = $row[$col['key']] ?? '';

                    return $col['type'] === 'money' && is_numeric($value) ? number_format((float) $value, 2, '.', '') : $value;
                }, $report['columns']);
            };

            foreach ($report['rows'] as $row) {
                fputcsv($out, $line($row));
            }

            if ($report['totals']) {
                fputcsv($out, $line($report['totals']));
            }

            fclose($out);
        }, "{$basename}.csv", ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * @param  array<string,mixed>  $report
     */
    protected function xlsx(array $report, string $basename): SymfonyResponse
    {
        $path = tempnam(sys_get_temp_dir(), 'rep').'.xlsx';
        XlsxWriter::write($path, $report['title'], $report['columns'], $report['rows'], $report['totals']);

        return response()->download($path, "{$basename}.xlsx")->deleteFileAfterSend(true);
    }

    /**
     * Blank / "all" values become null; missing date keys fall back to this month.
     *
     * @return array<string,mixed>
     */
    protected function filters(Request $request): array
    {
        $pick = fn (string $key) => in_array($request->query($key), [null, '', 'all'], true) ? null : $request->query($key);

        return [
            'from' => $request->has('from') ? ($request->query('from') ?: null) : now()->startOfMonth()->toDateString(),
            'to' => $request->has('to') ? ($request->query('to') ?: null) : today()->toDateString(),
            'customer_id' => $pick('customer_id'),
            'staff_id' => $pick('staff_id'),
            'status' => $pick('status') ?? 'all',
        ];
    }
}
