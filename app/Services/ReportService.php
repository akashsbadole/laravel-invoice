<?php

namespace App\Services;

use App\Enums\DocumentType;
use App\Enums\InvoiceStatus;
use App\Enums\LineType;
use App\Enums\QuotationStatus;
use App\Models\Invoice;
use App\Models\InvoiceEvent;
use App\Models\InvoiceItem;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Single source for every report: the on-screen table, CSV, Excel and PDF
 * exports all render the same {title, columns, rows, totals} structure.
 */
class ReportService
{
    public const TYPES = [
        'invoices' => 'Document report',
        'paid' => 'Paid invoices',
        'unpaid' => 'Unpaid invoices',
        'outstanding' => 'Outstanding balances',
        'customer' => 'Customer invoice summary',
        'payments' => 'Payments',
        'tax' => 'Tax report (GST)',
        'salesperson' => 'Salesperson report',
        'monthly' => 'Monthly revenue',
        'ageing' => 'Customer ageing',
        'top_items' => 'Top selling items',
        'quotation_conversion' => 'Quotation conversion',
    ];

    /**
     * @param  array<string,mixed>  $filters  from, to, customer_id, staff_id, status
     * @return array{type:string,title:string,columns:array<int,array<string,string>>,rows:array<int,array<string,mixed>>,totals:array<string,mixed>|null}
     */
    public function build(string $type, array $filters): array
    {
        $type = array_key_exists($type, self::TYPES) ? $type : 'invoices';

        $result = match ($type) {
            'paid' => $this->invoiceList('Paid invoices', $filters, [InvoiceStatus::Paid]),
            'unpaid' => $this->invoiceList('Unpaid invoices', $filters, [InvoiceStatus::Unpaid, InvoiceStatus::Overdue]),
            'outstanding' => $this->outstanding($filters),
            'customer' => $this->customerSummary($filters),
            'payments' => $this->payments($filters),
            'tax' => $this->tax($filters),
            'salesperson' => $this->salesperson($filters),
            'monthly' => $this->monthly($filters),
            'ageing' => $this->ageing($filters),
            'top_items' => $this->topItems($filters),
            'quotation_conversion' => $this->quotationConversion($filters),
            // The one report that is a document listing rather than a money
            // total, so it keeps quotations and challans and labels the type.
            default => $this->invoiceList('Document report', $filters, null, includeNonSales: true),
        };

        $result['type'] = $type;
        $result['totals'] = $this->totals($result['columns'], $result['rows']);

        return $result;
    }

    /**
     * @param  array<string,mixed>  $f
     * @param  array<int,InvoiceStatus>|null  $only
     * @return array<string,mixed>
     */
    protected function invoiceList(string $title, array $f, ?array $only, bool $includeNonSales = false): array
    {
        $rows = $this->invoiceQuery($f, $only, includeNonSales: $includeNonSales)->get()->map(fn (Invoice $i) => [
            'invoice_number' => $i->invoice_number,
            'invoice_date' => $i->invoice_date->format('Y-m-d'),
            'customer' => $i->customer?->full_name ?? '-',
            // Only meaningful when non-sales are included; harmless otherwise.
            'document_type' => $i->document_type->label(),
            'salesperson' => $i->salesperson?->name ?? '-',
            'status' => str_replace('_', ' ', $i->status->value),
            'grand_total' => (float) $i->grand_total,
            'tax' => (float) $i->tax,
            'paid' => (float) $i->paid_amount,
            'balance' => (float) $i->balance_amount,
        ])->all();

        return [
            'title' => $title,
            'columns' => [
                $this->col('invoice_number', 'Invoice'), $this->col('invoice_date', 'Date', 'date'),
                $this->col('customer', 'Customer'), $this->col('document_type', 'Document'),
                $this->col('salesperson', 'Salesperson'),
                $this->col('status', 'Status'), $this->col('grand_total', 'Total', 'money'),
                $this->col('tax', 'Tax', 'money'), $this->col('paid', 'Paid', 'money'),
                $this->col('balance', 'Balance', 'money'),
            ],
            'rows' => $rows,
        ];
    }

    /**
     * @param  array<string,mixed>  $f
     * @return array<string,mixed>
     */
    protected function outstanding(array $f): array
    {
        $open = [InvoiceStatus::Unpaid, InvoiceStatus::PartiallyPaid, InvoiceStatus::Overdue];

        $rows = $this->invoiceQuery($f, $open)->where('balance_amount', '>', 0)->get()
            ->groupBy('customer_id')
            ->map(function (Collection $group) {
                /** @var Invoice $first */
                $first = $group->first();
                $oldestDue = $group->pluck('due_date')->filter()->min();

                return [
                    'customer' => $first->customer?->full_name ?? '-',
                    'mobile' => $first->customer?->mobile_number ?? '-',
                    'invoices' => $group->count(),
                    'invoiced' => round((float) $group->sum('grand_total'), 2),
                    'paid' => round((float) $group->sum('paid_amount'), 2),
                    'outstanding' => round((float) $group->sum('balance_amount'), 2),
                    'oldest_due' => $oldestDue ? Carbon::parse($oldestDue)->format('Y-m-d') : '-',
                ];
            })
            ->sortByDesc('outstanding')->values()->all();

        return [
            'title' => 'Outstanding balances',
            'columns' => [
                $this->col('customer', 'Customer'), $this->col('mobile', 'Mobile'),
                $this->col('invoices', 'Open invoices', 'number'), $this->col('invoiced', 'Invoiced', 'money'),
                $this->col('paid', 'Paid', 'money'), $this->col('outstanding', 'Outstanding', 'money'),
                $this->col('oldest_due', 'Oldest due date', 'date'),
            ],
            'rows' => $rows,
        ];
    }

    /**
     * @param  array<string,mixed>  $f
     * @return array<string,mixed>
     */
    protected function customerSummary(array $f): array
    {
        $rows = $this->invoiceQuery($f, null, true)->get()
            ->groupBy('customer_id')
            ->map(function (Collection $group) {
                /** @var Invoice $first */
                $first = $group->first();

                return [
                    'customer' => $first->customer?->full_name ?? '-',
                    'mobile' => $first->customer?->mobile_number ?? '-',
                    'invoices' => $group->count(),
                    'invoiced' => round((float) $group->sum('grand_total'), 2),
                    'paid' => round((float) $group->sum('paid_amount'), 2),
                    'outstanding' => round((float) $group->sum('balance_amount'), 2),
                    'last_invoice' => $group->max('invoice_date')?->format('Y-m-d') ?? '-',
                ];
            })
            ->sortByDesc('invoiced')->values()->all();

        return [
            'title' => 'Customer invoice summary',
            'columns' => [
                $this->col('customer', 'Customer'), $this->col('mobile', 'Mobile'),
                $this->col('invoices', 'Invoices', 'number'), $this->col('invoiced', 'Invoiced', 'money'),
                $this->col('paid', 'Paid', 'money'), $this->col('outstanding', 'Outstanding', 'money'),
                $this->col('last_invoice', 'Last invoice', 'date'),
            ],
            'rows' => $rows,
        ];
    }

    /**
     * @param  array<string,mixed>  $f
     * @return array<string,mixed>
     */
    protected function payments(array $f): array
    {
        $status = $f['status'] ?? 'all';

        $rows = Payment::query()
            ->with(['invoice.customer:id,full_name', 'receiver:id,name'])
            ->when($f['from'] ?? null, fn ($q, $v) => $q->whereDate('payment_date', '>=', $v))
            ->when($f['to'] ?? null, fn ($q, $v) => $q->whereDate('payment_date', '<=', $v))
            ->when($f['customer_id'] ?? null, fn ($q, $v) => $q->whereHas('invoice', fn ($iq) => $iq->where('customer_id', $v)))
            ->when($f['staff_id'] ?? null, fn ($q, $v) => $q->where('received_by', $v))
            ->when($status !== 'all', fn ($q) => $q->whereHas('invoice', fn ($iq) => $iq->where('status', $status)))
            ->orderBy('payment_date')->orderBy('id')
            ->get()
            ->map(fn (Payment $p) => [
                'payment_date' => $p->payment_date->format('Y-m-d'),
                'invoice' => $p->invoice?->invoice_number ?? '-',
                'customer' => $p->invoice?->customer?->full_name ?? '-',
                'method' => $p->payment_method->label(),
                'reference' => $p->reference_number ?? '-',
                'received_by' => $p->receiver?->name ?? '-',
                'amount' => (float) $p->amount,
            ])->all();

        return [
            'title' => 'Payments',
            'columns' => [
                $this->col('payment_date', 'Date', 'date'), $this->col('invoice', 'Invoice'),
                $this->col('customer', 'Customer'), $this->col('method', 'Method'),
                $this->col('reference', 'Reference'), $this->col('received_by', 'Received by'),
                $this->col('amount', 'Amount', 'money'),
            ],
            'rows' => $rows,
        ];
    }

    /**
     * @param  array<string,mixed>  $f
     * @return array<string,mixed>
     */
    protected function tax(array $f): array
    {
        $rows = $this->invoiceQuery($f, null, true)->get()->map(function (Invoice $i) {
            $breakdown = collect($i->tax_breakdown ?? []);
            $sum = fn (string $prefix) => round((float) $breakdown
                ->filter(fn ($r) => str_starts_with($r['label'], $prefix))->sum('amount'), 2);

            return [
                'invoice_date' => $i->invoice_date->format('Y-m-d'),
                'invoice_number' => $i->invoice_number,
                'customer' => $i->customer?->full_name ?? '-',
                'gstin' => $i->customer?->tax_number ?? '-',
                // Value before tax (after discounts, incl. all charges, before round-off)
                'taxable' => round((float) $i->grand_total - (float) $i->round_off - (float) $i->tax, 2),
                'cgst' => $sum('CGST'),
                'sgst' => $sum('SGST'),
                'igst' => $sum('IGST'),
                'other_tax' => $breakdown->isEmpty() ? (float) $i->tax : 0.0,
                'total_tax' => (float) $i->tax,
            ];
        })->all();

        return [
            'title' => 'Tax report (GST)',
            'columns' => [
                $this->col('invoice_date', 'Date', 'date'), $this->col('invoice_number', 'Invoice'),
                $this->col('customer', 'Customer'), $this->col('gstin', 'Customer GSTIN'),
                $this->col('taxable', 'Value before tax', 'money'), $this->col('cgst', 'CGST', 'money'),
                $this->col('sgst', 'SGST', 'money'), $this->col('igst', 'IGST', 'money'),
                $this->col('other_tax', 'Tax (unsplit)', 'money'), $this->col('total_tax', 'Total tax', 'money'),
            ],
            'rows' => $rows,
        ];
    }

    /**
     * Outstanding balances bucketed by how long they have been unpaid.
     *
     * Days are measured from the due date where one exists, because that is the
     * date the customer agreed to pay by; an invoice with no due date falls
     * back to its invoice date so it still ages rather than disappearing.
     *
     * @param  array<string,mixed>  $f
     * @return array<string,mixed>
     */
    protected function ageing(array $f): array
    {
        $today = Carbon::parse($f['to'] ?? today()->toDateString())->startOfDay();

        $buckets = [
            'current' => ['label' => 'Not yet due', 'min' => null, 'max' => 0],
            '1-30' => ['label' => '1–30 days', 'min' => 1, 'max' => 30],
            '31-60' => ['label' => '31–60 days', 'min' => 31, 'max' => 60],
            '61-90' => ['label' => '61–90 days', 'min' => 61, 'max' => 90],
            '90+' => ['label' => '90+ days', 'min' => 91, 'max' => null],
        ];

        $rows = $this->invoiceQuery($f, null, true)
            ->where('balance_amount', '>', 0)
            ->get(['id', 'invoice_number', 'invoice_date', 'due_date', 'balance_amount', 'customer_id'])
            ->map(function (Invoice $invoice) use ($today, $buckets) {
                $reference = ($invoice->due_date ?? $invoice->invoice_date)->copy()->startOfDay();
                // Measure from the reference date toward today: a past reference
                // is positive days overdue, while a future reference is negative
                // and therefore clamped to current.
                $overdueDays = max(0, (int) $reference->diffInDays($today, false));

                $bucket = 'current';

                foreach ($buckets as $key => $definition) {
                    if ($definition['min'] === null) {
                        continue;
                    }

                    if ($overdueDays >= $definition['min']
                        && ($definition['max'] === null || $overdueDays <= $definition['max'])) {
                        $bucket = $key;

                        break;
                    }
                }

                return [
                    'invoice_number' => $invoice->invoice_number,
                    'customer' => $invoice->customer?->full_name ?? '-',
                    'due_date' => $reference?->format('Y-m-d'),
                    'days_overdue' => $overdueDays,
                    'bucket' => $bucket,
                    'bucket_label' => $buckets[$bucket]['label'],
                    'balance' => (float) $invoice->balance_amount,
                ];
            })
            // Oldest debt first: that is what needs chasing.
            ->sortByDesc('days_overdue')
            ->values()
            ->all();

        return [
            'title' => 'Customer ageing',
            'columns' => [
                $this->col('invoice_number', 'Invoice'), $this->col('customer', 'Customer'),
                $this->col('due_date', 'Due', 'date'), $this->col('days_overdue', 'Days overdue', 'number'),
                $this->col('bucket_label', 'Bucket'), $this->col('balance', 'Outstanding', 'money'),
            ],
            'rows' => $rows,
        ];
    }

    /**
     * Best sellers by line item.
     *
     * Aggregated from invoice_items rather than the invoice header so a single
     * large invoice does not hide a product that sells steadily. Exchange credit
     * lines are excluded: they reduce a sale, they are not one.
     *
     * Built as its own query rather than on top of invoiceQuery(): joining
     * invoice_items makes `id` and `document_type` ambiguous, and the shared
     * base applies an ORDER BY that would fight the ranking here.
     *
     * @param  array<string,mixed>  $f
     * @return array<string,mixed>
     */
    protected function topItems(array $f, int $limit = 20): array
    {
        $rows = InvoiceItem::query()
            ->join('invoices', 'invoices.id', '=', 'invoice_items.invoice_id')
            ->whereIn('invoices.document_type', $this->saleDocumentTypes())
            ->whereNotIn('invoices.status', [InvoiceStatus::Cancelled->value, InvoiceStatus::Refunded->value])
            ->where('invoice_items.line_type', LineType::Sale->value)
            ->when($f['from'] ?? null, fn ($q, $v) => $q->whereDate('invoices.invoice_date', '>=', $v))
            ->when($f['to'] ?? null, fn ($q, $v) => $q->whereDate('invoices.invoice_date', '<=', $v))
            ->when($f['customer_id'] ?? null, fn ($q, $v) => $q->where('invoices.customer_id', $v))
            ->when($f['staff_id'] ?? null, fn ($q, $v) => $q->where('invoices.salesperson_id', $v))
            ->groupBy('invoice_items.item_name')
            ->orderByDesc(DB::raw('SUM(invoice_items.total)'))
            ->limit($limit)
            ->get([
                'invoice_items.item_name',
                DB::raw('COUNT(*) as lines'),
                DB::raw('SUM(invoice_items.quantity) as quantity'),
                DB::raw('SUM(invoice_items.total) as revenue'),
            ])
            ->map(fn ($row) => [
                'item_name' => $row->item_name,
                'lines' => (int) $row->lines,
                'quantity' => round((float) $row->quantity, 2),
                'revenue' => round((float) $row->revenue, 2),
            ])
            ->all();

        return [
            'title' => 'Top selling items',
            'columns' => [
                $this->col('item_name', 'Item'), $this->col('lines', 'Invoices', 'number'),
                $this->col('quantity', 'Quantity', 'number'), $this->col('revenue', 'Revenue', 'money'),
            ],
            'rows' => $rows,
        ];
    }

    /**
     * Document types that count as realised revenue.
     *
     * @return list<string>
     */
    protected function saleDocumentTypes(): array
    {
        return array_values(array_map(
            fn (DocumentType $type) => $type->value,
            array_filter(DocumentType::cases(), fn (DocumentType $type) => $type->isSale()),
        ));
    }

    /**
     * How many quotations actually turn into invoices.
     *
     * Conversion is measured by converted_to_id, which is set the moment staff
     * convert, rather than by quotation_status, which can also be moved by hand.
     *
     * @param  array<string,mixed>  $f
     * @return array<string,mixed>
     */
    protected function quotationConversion(array $f): array
    {
        $quotations = Invoice::query()
            ->where('document_type', DocumentType::Quotation->value)
            ->when($f['from'] ?? null, fn ($q, $v) => $q->whereDate('invoice_date', '>=', $v))
            ->when($f['to'] ?? null, fn ($q, $v) => $q->whereDate('invoice_date', '<=', $v))
            ->when($f['staff_id'] ?? null, fn ($q, $v) => $q->where('salesperson_id', $v))
            ->get();

        $total = $quotations->count();

        $converted = $quotations->whereNotNull('converted_to_id')->count();
        $accepted = $quotations->where('quotation_status', QuotationStatus::Accepted->value)->count();
        $rejected = $quotations->where('quotation_status', QuotationStatus::Rejected->value)->count();
        $expired = $quotations->filter(
            fn (Invoice $q) => app(QuotationService::class)->currentStatus($q) === QuotationStatus::Expired
        )->count();

        // Won back: a converted quote whose lifecycle history passed through
        // Rejected. The current status is necessarily Converted by then, so the
        // lifecycle event log is the durable evidence.
        $wonBackIds = $this->convertedAfterRejectionIds(
            $quotations->whereNotNull('converted_to_id')->modelKeys()
        );
        $wonBack = count($wonBackIds);

        $value = fn (callable $filter) => round((float) $quotations->filter($filter)
            ->sum('grand_total'), 2);

        return [
            'title' => 'Quotation conversion',
            'columns' => [
                $this->col('metric', 'Metric'), $this->col('count', 'Count', 'number'),
                $this->col('share', 'Share', 'percent'), $this->col('value', 'Value', 'money'),
            ],
            'rows' => [
                $this->conversionRow('Quotations raised', $total, $total, $quotations),
                $this->conversionRow('Accepted', $accepted, $total, $quotations, QuotationStatus::Accepted),
                $this->conversionRow('Rejected', $rejected, $total, $quotations, QuotationStatus::Rejected),
                $this->conversionRow('Expired', $expired, $total, $quotations),
                $this->conversionRow('Converted to invoice', $converted, $total, $quotations, null, true),
                $this->conversionRow('Won back after rejection', $wonBack, $total, $quotations, null, true, $wonBackIds),
            ],
        ];
    }

    /**
     * Converted quotations whose lifecycle previously passed through Rejected.
     *
     * @param  array<int,int>  $convertedIds
     * @return array<int,int>
     */
    protected function convertedAfterRejectionIds(array $convertedIds): array
    {
        if ($convertedIds === []) {
            return [];
        }

        return InvoiceEvent::query()
            ->whereIn('invoice_id', $convertedIds)
            ->get(['invoice_id', 'meta'])
            ->filter(function (InvoiceEvent $event) {
                $meta = $event->meta ?? [];

                return ($meta['action'] ?? null) === 'quotation_status'
                    && ($meta['from'] ?? null) === QuotationStatus::Rejected->value
                    && ($meta['to'] ?? null) === QuotationStatus::Converted->value;
            })
            ->map(fn (InvoiceEvent $event) => (int) $event->invoice_id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int,Invoice>  $quotations
     * @return array<string,mixed>
     */
    protected function conversionRow(
        string $label,
        int $count,
        int $total,
        Collection $quotations,
        ?QuotationStatus $status = null,
        bool $byConversion = false,
        ?array $onlyIds = null,
    ): array {
        $subset = $quotations->filter(function (Invoice $quotation) use ($status, $byConversion, $onlyIds) {
            if ($byConversion) {
                if ($onlyIds !== null) {
                    return in_array($quotation->id, $onlyIds, true);
                }

                return $quotation->converted_to_id !== null;
            }

            return $status !== null && $quotation->quotation_status === $status;
        });

        return [
            'metric' => $label,
            'count' => $count,
            'share' => $total > 0 ? round($count / $total * 100, 1) : 0.0,
            'value' => round((float) $subset->sum('grand_total'), 2),
        ];
    }

    /**
     * @param  array<string,mixed>  $f
     * @return array<string,mixed>
     */
    protected function salesperson(array $f): array
    {
        $rows = $this->invoiceQuery($f, null, true)->get()
            ->groupBy(fn (Invoice $i) => $i->salesperson_id ?? 0)
            ->map(function (Collection $group) {
                /** @var Invoice $first */
                $first = $group->first();

                return [
                    'salesperson' => $first->salesperson?->name ?? 'Unassigned',
                    'invoices' => $group->count(),
                    'sales' => round((float) $group->sum('grand_total'), 2),
                    'collected' => round((float) $group->sum('paid_amount'), 2),
                    'outstanding' => round((float) $group->sum('balance_amount'), 2),
                ];
            })
            ->sortByDesc('sales')->values()->all();

        return [
            'title' => 'Salesperson report',
            'columns' => [
                $this->col('salesperson', 'Salesperson'), $this->col('invoices', 'Invoices', 'number'),
                $this->col('sales', 'Sales', 'money'), $this->col('collected', 'Collected', 'money'),
                $this->col('outstanding', 'Outstanding', 'money'),
            ],
            'rows' => $rows,
        ];
    }

    /**
     * @param  array<string,mixed>  $f
     * @return array<string,mixed>
     */
    protected function monthly(array $f): array
    {
        $invoices = $this->invoiceQuery($f, null, true)->get()
            ->groupBy(fn (Invoice $i) => $i->invoice_date->format('Y-m'));

        $payments = Payment::query()
            ->when($f['from'] ?? null, fn ($q, $v) => $q->whereDate('payment_date', '>=', $v))
            ->when($f['to'] ?? null, fn ($q, $v) => $q->whereDate('payment_date', '<=', $v))
            ->when($f['customer_id'] ?? null, fn ($q, $v) => $q->whereHas('invoice', fn ($iq) => $iq->where('customer_id', $v)))
            ->get(['id', 'invoice_id', 'amount', 'payment_date'])
            ->groupBy(fn (Payment $p) => $p->payment_date->format('Y-m'));

        $rows = $invoices->keys()->merge($payments->keys())->unique()->sort()->values()
            ->map(fn (string $month) => [
                'month' => Carbon::parse($month.'-01')->format('M Y'),
                'invoices' => ($invoices[$month] ?? collect())->count(),
                'invoiced' => round((float) ($invoices[$month] ?? collect())->sum('grand_total'), 2),
                'tax' => round((float) ($invoices[$month] ?? collect())->sum('tax'), 2),
                'collected' => round((float) ($payments[$month] ?? collect())->sum('amount'), 2),
            ])->all();

        return [
            'title' => 'Monthly revenue',
            'columns' => [
                $this->col('month', 'Month'), $this->col('invoices', 'Invoices', 'number'),
                $this->col('invoiced', 'Invoiced', 'money'), $this->col('tax', 'Tax', 'money'),
                $this->col('collected', 'Collected', 'money'),
            ],
            'rows' => $rows,
        ];
    }

    /**
     * Base query for every report.
     *
     * Non-sale documents (quotations, delivery challans) are excluded by
     * default because every report here is about money that actually moved.
     * A converted quotation in particular would otherwise be counted twice:
     * once as the quote and once as the invoice it became.
     *
     * @param  array<string,mixed>  $f
     * @param  array<int,InvoiceStatus>|null  $only
     * @return Builder<Invoice>
     */
    protected function invoiceQuery(
        array $f,
        ?array $only = null,
        bool $excludeVoid = false,
        bool $includeNonSales = false,
    ): Builder {
        $status = $f['status'] ?? 'all';

        return Invoice::query()
            ->with(['customer:id,full_name,mobile_number,tax_number', 'salesperson:id,name'])
            ->when($includeNonSales, fn ($q) => null, fn ($q) => $q->whereIn('document_type', $this->saleDocumentTypes()))
            ->when($f['from'] ?? null, fn ($q, $v) => $q->whereDate('invoice_date', '>=', $v))
            ->when($f['to'] ?? null, fn ($q, $v) => $q->whereDate('invoice_date', '<=', $v))
            ->when($f['customer_id'] ?? null, fn ($q, $v) => $q->where('customer_id', $v))
            ->when($f['staff_id'] ?? null, fn ($q, $v) => $q->where('salesperson_id', $v))
            ->when(
                $only !== null,
                fn ($q) => $q->whereIn('status', array_map(fn (InvoiceStatus $s) => $s->value, $only)),
                fn ($q) => $q->when($status !== 'all', fn ($qq) => $qq->where('status', $status)),
            )
            ->when($excludeVoid, fn ($q) => $q->whereNotIn('status', ['cancelled', 'refunded']))
            ->orderBy('invoice_date')->orderBy('id');
    }

    /**
     * @return array{key:string,label:string,type:string}
     */
    protected function col(string $key, string $label, string $type = 'text'): array
    {
        return ['key' => $key, 'label' => $label, 'type' => $type];
    }

    /**
     * @param  array<int,array<string,string>>  $columns
     * @param  array<int,array<string,mixed>>  $rows
     * @return array<string,mixed>|null
     */
    protected function totals(array $columns, array $rows): ?array
    {
        if ($rows === []) {
            return null;
        }

        $totals = [];

        foreach ($columns as $i => $col) {
            $totals[$col['key']] = $i === 0
                ? 'Total'
                : (in_array($col['type'], ['money', 'number'], true)
                    ? round(array_sum(array_column($rows, $col['key'])), 2)
                    : '');
        }

        return $totals;
    }
}
