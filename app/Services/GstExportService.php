<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Models\BusinessSetting;
use App\Models\Invoice;
use Illuminate\Support\Collection;

class GstExportService
{
    /**
     * Build a simplified GSTR-1 payload (b2b / b2cl / b2cs / hsn / doc_issue)
     * from outward invoices in the filter window. Cancelled documents are
     * reported under doc_issue only.
     *
     * @param  array{from?: ?string, to?: ?string}  $filters
     * @return array<string, mixed>
     */
    public function gstr1(array $filters, BusinessSetting $business): array
    {
        $invoices = $this->outwardInvoices($filters);
        $homeState = substr((string) ($business->state_code ?? ''), 0, 2);

        $b2b = [];
        $b2cl = [];
        $b2cs = [];
        $hsn = [];

        foreach ($invoices as $invoice) {
            if ($invoice->status === InvoiceStatus::Cancelled) {
                continue;
            }

            $customer = $invoice->customer;
            $pos = substr((string) ($customer?->state_code ?: $homeState), 0, 2) ?: $homeState;
            $gstin = trim((string) ($customer?->tax_number ?? ''));

            foreach ($this->rateGroups($invoice) as $group) {
                $this->accumulateHsn($hsn, $invoice, $group);

                if ($gstin !== '') {
                    $b2b[$gstin][] = $this->b2bItem($invoice, $pos, $gstin, $group);
                } elseif ($group['igst'] > 0 && $group['taxable'] > 100000) {
                    $b2cl[] = $this->b2clItem($invoice, $pos, $group);
                } else {
                    $key = "{$pos}|{$group['rate']}";
                    $b2cs[$key] ??= ['pos' => $pos, 'rt' => $group['rate'], 'txval' => 0.0, 'iamt' => 0.0, 'camt' => 0.0, 'samt' => 0.0];
                    $b2cs[$key]['txval'] += $group['taxable'];
                    $b2cs[$key]['iamt'] += $group['igst'];
                    $b2cs[$key]['camt'] += $group['cgst'];
                    $b2cs[$key]['samt'] += $group['sgst'];
                }
            }
        }

        return [
            'gstin' => $business->tax_number,
            'fp' => $this->filingPeriod($filters),
            'b2b' => collect($b2b)->map(fn ($inv, $ctin) => [
                'ctin' => $ctin,
                'inv' => $inv,
            ])->values()->all(),
            'b2cl' => $b2cl,
            'b2cs' => array_values(array_map(fn ($row) => [
                'pos' => $row['pos'],
                'rt' => $row['rt'],
                'txval' => round($row['txval'], 2),
                'iamt' => round($row['iamt'], 2),
                'camt' => round($row['camt'], 2),
                'samt' => round($row['samt'], 2),
            ], $b2cs)),
            'hsn' => [
                'data' => array_values($hsn),
            ],
            'doc_issue' => $this->docIssue($filters),
        ];
    }

    /**
     * GSTR-3B outward-supplies summary. ITC sections stay zero: this app
     * has no purchase module, so inward figures must come from your accounts.
     *
     * @param  array{from?: ?string, to?: ?string}  $filters
     * @return array<string, mixed>
     */
    public function gstr3b(array $filters, BusinessSetting $business): array
    {
        $invoices = $this->outwardInvoices($filters)->filter(
            fn (Invoice $i) => $i->status !== InvoiceStatus::Cancelled,
        );

        $taxable = 0.0;
        $igst = 0.0;
        $cgst = 0.0;
        $sgst = 0.0;

        foreach ($invoices as $invoice) {
            foreach ($this->rateGroups($invoice) as $group) {
                $taxable += $group['taxable'];
                $igst += $group['igst'];
                $cgst += $group['cgst'];
                $sgst += $group['sgst'];
            }
        }

        return [
            'gstin' => $business->tax_number,
            'fp' => $this->filingPeriod($filters),
            'outward_taxable_supplies' => [
                'txval' => round($taxable, 2),
                'iamt' => round($igst, 2),
                'camt' => round($cgst, 2),
                'samt' => round($sgst, 2),
            ],
            'outward_zero_rated_supplies' => ['txval' => 0.0, 'iamt' => 0.0],
            'other_outward_supplies_nil_rated_exempted' => ['txval' => 0.0],
            'inward_nil_rated_exempted' => ['txval' => 0.0],
            'itc_available' => ['iamt' => 0.0, 'camt' => 0.0, 'samt' => 0.0, 'csamt' => 0.0],
            'note' => 'ITC and inward figures are zero: record purchases in your accounting system.',
        ];
    }

    /**
     * @param  array{from?: ?string, to?: ?string}  $filters
     * @return Collection<int, Invoice>
     */
    protected function outwardInvoices(array $filters): Collection
    {
        return Invoice::query()
            ->with(['customer:id,full_name,tax_number,state_code', 'items'])
            ->where('document_type', '!=', 'quotation')
            ->when($filters['from'] ?? null, fn ($q, $from) => $q->whereDate('invoice_date', '>=', $from))
            ->when($filters['to'] ?? null, fn ($q, $to) => $q->whereDate('invoice_date', '<=', $to))
            ->orderBy('invoice_date')
            ->get();
    }

    /**
     * Split an invoice into per-tax-rate groups with HSN detail.
     *
     * @return list<array{rate: float, taxable: float, igst: float, cgst: float, sgst: float, hsn: string, qty: float}>
     */
    protected function rateGroups(Invoice $invoice): array
    {
        $breakdown = collect($invoice->tax_breakdown ?? []);
        $byRate = [];

        foreach ($invoice->items as $item) {
            $rate = round((float) ($item->tax_rate ?? 0), 2);
            $base = (float) $item->total - (float) $item->tax;
            $tax = (float) $item->tax;

            // Split the item tax by mode using the invoice-level breakdown ratio.
            $totalTax = max((float) $invoice->tax, 0.0001);
            $share = $tax / $totalTax;
            $igst = round((float) $breakdown->filter(fn ($r) => str_starts_with($r['label'], 'IGST'))->sum('amount') * $share, 2);
            $cgst = round((float) $breakdown->filter(fn ($r) => str_starts_with($r['label'], 'CGST'))->sum('amount') * $share, 2);
            $sgst = round((float) $breakdown->filter(fn ($r) => str_starts_with($r['label'], 'SGST'))->sum('amount') * $share, 2);

            if ($igst + $cgst + $sgst === 0.0 && $tax > 0) {
                // Single/unsplit tax: treat as intra-state CGST+SGST halves.
                $cgst = round($tax / 2, 2);
                $sgst = round($tax - $cgst, 2);
            }

            $key = (string) $rate;
            $byRate[$key] ??= ['rate' => $rate, 'taxable' => 0.0, 'igst' => 0.0, 'cgst' => 0.0, 'sgst' => 0.0, 'hsn' => (string) ($item->hsn_code ?? ''), 'qty' => 0.0];
            $byRate[$key]['taxable'] += $base;
            $byRate[$key]['igst'] += $igst;
            $byRate[$key]['cgst'] += $cgst;
            $byRate[$key]['sgst'] += $sgst;
            $byRate[$key]['qty'] += (float) $item->quantity;
        }

        return array_values(array_map(fn ($g) => [
            'rate' => $g['rate'],
            'taxable' => round($g['taxable'], 2),
            'igst' => round($g['igst'], 2),
            'cgst' => round($g['cgst'], 2),
            'sgst' => round($g['sgst'], 2),
            'hsn' => $g['hsn'],
            'qty' => $g['qty'],
        ], $byRate));
    }

    /**
     * @param  array<string, mixed>  $hsn
     * @param  array{rate: float, taxable: float, igst: float, cgst: float, sgst: float, hsn: string, qty: float}  $group
     */
    protected function accumulateHsn(array &$hsn, Invoice $invoice, array $group): void
    {
        $key = ($group['hsn'] !== '' ? $group['hsn'] : 'NA').'|'.$invoice->customer?->state_code;
        $hsn[$key] ??= [
            'hsnsac' => $group['hsn'] !== '' ? $group['hsn'] : 'NA',
            'qty' => 0.0,
            'txval' => 0.0,
            'iamt' => 0.0,
            'camt' => 0.0,
            'samt' => 0.0,
        ];
        $hsn[$key]['qty'] += $group['qty'];
        $hsn[$key]['txval'] = round($hsn[$key]['txval'] + $group['taxable'], 2);
        $hsn[$key]['iamt'] = round($hsn[$key]['iamt'] + $group['igst'], 2);
        $hsn[$key]['camt'] = round($hsn[$key]['camt'] + $group['cgst'], 2);
        $hsn[$key]['samt'] = round($hsn[$key]['samt'] + $group['sgst'], 2);
    }

    /**
     * @param  array{rate: float, taxable: float, igst: float, cgst: float, sgst: float, hsn: string, qty: float}  $group
     * @return array<string, mixed>
     */
    protected function b2bItem(Invoice $invoice, string $pos, string $gstin, array $group): array
    {
        return [
            'inum' => $invoice->invoice_number,
            'idt' => $invoice->invoice_date->format('d-m-Y'),
            'val' => round((float) $invoice->grand_total, 2),
            'pos' => $pos,
            'rchrg' => 'N',
            'itms' => [[
                'num' => 1,
                'itm_det' => [
                    'txval' => $group['taxable'],
                    'rt' => $group['rate'],
                    'camt' => $group['cgst'],
                    'samt' => $group['sgst'],
                    'iamt' => $group['igst'],
                    'csamt' => 0,
                ],
            ]],
        ];
    }

    /**
     * @param  array{rate: float, taxable: float, igst: float, cgst: float, sgst: float, hsn: string, qty: float}  $group
     * @return array<string, mixed>
     */
    protected function b2clItem(Invoice $invoice, string $pos, array $group): array
    {
        return [
            'inum' => $invoice->invoice_number,
            'idt' => $invoice->invoice_date->format('d-m-Y'),
            'val' => round((float) $invoice->grand_total, 2),
            'pos' => $pos,
            'itms' => [[
                'num' => 1,
                'itm_det' => [
                    'txval' => $group['taxable'],
                    'rt' => $group['rate'],
                    'iamt' => $group['igst'],
                    'csamt' => 0,
                ],
            ]],
        ];
    }

    /**
     * @param  array{from?: ?string, to?: ?string}  $filters
     * @return array<string, mixed>
     */
    protected function docIssue(array $filters): array
    {
        $numbers = Invoice::query()
            ->where('document_type', '!=', 'quotation')
            ->when($filters['from'] ?? null, fn ($q, $from) => $q->whereDate('invoice_date', '>=', $from))
            ->when($filters['to'] ?? null, fn ($q, $to) => $q->whereDate('invoice_date', '<=', $to))
            ->orderBy('invoice_number')
            ->get(['invoice_number', 'status']);

        return [
            'doc_det' => [[
                'doc_num' => 1,
                'doc_typ' => 'Invoices for outward supply',
                'docs' => [[
                    'num' => 1,
                    'from' => $numbers->min('invoice_number'),
                    'to' => $numbers->max('invoice_number'),
                    'totnum' => $numbers->count(),
                    'cancel' => $numbers->where('status', InvoiceStatus::Cancelled)->count(),
                    'net_issue' => $numbers->where('status', '!=', InvoiceStatus::Cancelled)->count(),
                ]],
            ]],
        ];
    }

    /**
     * @param  array{from?: ?string, to?: ?string}  $filters
     */
    protected function filingPeriod(array $filters): string
    {
        $to = $filters['to'] ?? now()->format('Y-m-d');

        return date('mY', strtotime((string) $to));
    }
}
