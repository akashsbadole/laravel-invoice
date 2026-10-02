<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Services\ReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Ageing, top-selling items and quotation conversion.
 *
 * All three were on the wishlist and had no implementation: the report service
 * had nine types, none of which answered "who owes us the longest", "what
 * actually sells" or "do our quotes turn into money".
 */
class SalesInsightReportsTest extends TestCase
{
    use RefreshDatabase;

    protected ?Customer $customer = null;

    protected function invoice(
        $user,
        array $overrides = [],
        array $items = [['item_name' => 'Ring', 'quantity' => 1, 'rate' => 1000]],
    ): Invoice {
        // One customer for the whole test, so invoice() can find the row it just
        // created rather than whichever happens to be newest overall.
        $this->customer ??= $this->customerFor($user);

        $this->actingAs($user)
            ->post(route('invoices.store'), array_merge([
                'customer_id' => $this->customer->id,
                'document_type' => 'general_invoice',
                'invoice_date' => now()->subYear()->toDateString(),
                'pricing_mode' => 'manual',
                'tax_mode' => 'single',
                'tax_rate' => 0,
                'discount' => 0,
                'invoice_charges' => [],
                'items' => array_map(fn (array $item) => array_merge([
                    'rate_type' => 'fixed',
                    'tax_rate' => 0,
                    'discount' => 0,
                    'charges' => [],
                ], $item), $items),
            ], $overrides))
            ->assertSessionHasNoErrors();

        return Invoice::where('customer_id', $this->customer->id)->latest('id')->firstOrFail();
    }

    public function test_all_three_new_report_types_are_offered(): void
    {
        $types = ReportService::TYPES;

        $this->assertArrayHasKey('ageing', $types);
        $this->assertArrayHasKey('top_items', $types);
        $this->assertArrayHasKey('quotation_conversion', $types);
    }

    public function test_ageing_buckets_unpaid_invoices_by_how_late_they_are(): void
    {
        $user = $this->adminFor();

        // Three invoices, each with a different due date.
        foreach ([
            ['date' => now()->subDays(10)->toDateString(), 'due' => now()->addDays(20)->toDateString()],
            ['date' => now()->subDays(45)->toDateString(), 'due' => now()->subDays(15)->toDateString()],
            ['date' => now()->subDays(200)->toDateString(), 'due' => now()->subDays(100)->toDateString()],
        ] as $case) {
            $this->invoice($user, [
                'invoice_date' => $case['date'],
                'due_date' => $case['due'],
            ]);
        }

        $report = app(ReportService::class)->build('ageing', []);

        $this->assertCount(3, $report['rows']);

        $byNumber = collect($report['rows'])->keyBy('invoice_number');

        // Oldest debt first — that is what needs chasing.
        $this->assertSame('90+ days', $byNumber->first()['bucket_label']);
        $this->assertSame('1–30 days', $byNumber->values()[1]['bucket_label']);
        $this->assertSame('Not yet due', $byNumber->last()['bucket_label']);

        $days = collect($report['rows'])->pluck('days_overdue')->all();
        $this->assertSame($days, collect($days)->sortDesc()->values()->all());
    }

    public function test_ageing_ignores_fully_paid_invoices(): void
    {
        $user = $this->adminFor();
        $invoice = $this->invoice($user, ['due_date' => now()->subDays(90)->toDateString()]);

        $this->actingAs($user)
            ->post(route('invoices.payments.store', $invoice), [
                'amount' => 1000,
                'payment_date' => now()->toDateString(),
                'payment_method' => 'cash',
            ])
            ->assertRedirect();

        $report = app(ReportService::class)->build('ageing', []);

        $this->assertSame([], $report['rows'], 'A settled invoice is not aged.');
    }

    public function test_ageing_excludes_quotations(): void
    {
        $user = $this->adminFor();

        $this->invoice($user, ['due_date' => now()->subDays(30)->toDateString()]);
        $this->invoice($user, [
            'document_type' => 'quotation',
            'due_date' => now()->subDays(30)->toDateString(),
        ]);

        $this->assertCount(1, app(ReportService::class)->build('ageing', [])['rows']);
    }

    public function test_top_items_ranks_by_revenue_across_invoices(): void
    {
        $user = $this->adminFor();

        $this->invoice($user, items: [
            ['item_name' => 'Gold Ring', 'quantity' => 1, 'rate' => 5000],
            ['item_name' => 'Chain', 'quantity' => 1, 'rate' => 500],
        ]);

        $this->invoice($user, items: [
            ['item_name' => 'Gold Ring', 'quantity' => 1, 'rate' => 5000],
        ]);

        $report = app(ReportService::class)->build('top_items', []);

        $names = array_column($report['rows'], 'item_name');

        $this->assertSame('Gold Ring', $names[0], 'Highest revenue item ranks first.');
        $this->assertContains('Chain', $names);

        $ring = collect($report['rows'])->firstWhere('item_name', 'Gold Ring');

        $this->assertSame(2, $ring['lines']);
        $this->assertSame(2.0, $ring['quantity']);
        $this->assertSame(10000.0, $ring['revenue']);
    }

    public function test_top_items_excludes_exchange_credit_lines(): void
    {
        $user = $this->adminFor();

        $this->invoice($user, items: [
            ['item_name' => 'Gold Ring', 'quantity' => 1, 'rate' => 5000],
            // Exchange credit reduces an invoice; it is not a sale.
            ['item_name' => 'Old Gold', 'quantity' => 1, 'rate' => 4000, 'line_type' => 'exchange_credit'],
        ]);

        $names = array_column(app(ReportService::class)->build('top_items', [])['rows'], 'item_name');

        $this->assertContains('Gold Ring', $names);
        $this->assertNotContains('Old Gold', $names);
    }

    public function test_quotation_conversion_counts_and_percentages(): void
    {
        $user = $this->adminFor();

        // One quotation, converted to an invoice.
        $quotation = $this->invoice($user, ['document_type' => 'quotation']);

        $this->actingAs($user)->post(route('invoices.convert', $quotation), [
            'document_type' => 'general_invoice',
        ])->assertRedirect();

        $report = app(ReportService::class)->build('quotation_conversion', []);
        $metrics = collect($report['rows'])->keyBy('metric');

        $this->assertSame(1, $metrics['Quotations raised']['count']);
        $this->assertSame(1, $metrics['Converted to invoice']['count']);
        $this->assertSame(100.0, $metrics['Converted to invoice']['share']);
    }

    public function test_a_rejected_quotation_counts_as_rejected_not_converted(): void
    {
        $user = $this->adminFor();

        $quotation = $this->invoice($user, ['document_type' => 'quotation']);

        $this->actingAs($user)->post(route('invoices.quotation-status', $quotation), [
            'status' => 'rejected',
        ])->assertRedirect();

        $metrics = collect(app(ReportService::class)->build('quotation_conversion', [])['rows'])
            ->keyBy('metric');

        $this->assertSame(1, $metrics['Rejected']['count']);
        $this->assertSame(0, $metrics['Converted to invoice']['count']);
        $this->assertSame(0, $metrics['Won back after rejection']['count']);
    }

    public function test_a_rejected_quotation_that_is_converted_counts_as_won_back(): void
    {
        $user = $this->adminFor();

        $quotation = $this->invoice($user, ['document_type' => 'quotation']);

        $this->actingAs($user)->post(route('invoices.quotation-status', $quotation), [
            'status' => 'rejected',
        ])->assertRedirect();

        // Staff talk it round and convert anyway.
        $this->actingAs($user)->post(route('invoices.convert', $quotation), [
            'document_type' => 'general_invoice',
        ])->assertRedirect();

        $metrics = collect(app(ReportService::class)->build('quotation_conversion', [])['rows'])
            ->keyBy('metric');

        $this->assertSame(1, $metrics['Won back after rejection']['count']);
    }

    public function test_conversion_rate_is_zero_rather_than_a_division_error_with_no_quotes(): void
    {
        $report = app(ReportService::class)->build('quotation_conversion', []);

        foreach ($report['rows'] as $row) {
            $this->assertSame(0.0, $row['share']);
        }
    }

    public function test_the_new_reports_are_reachable_from_the_reports_page(): void
    {
        $user = $this->adminFor();

        foreach (['ageing', 'top_items', 'quotation_conversion'] as $type) {
            $this->actingAs($user)
                ->get(route('reports.index', ['type' => $type]))
                ->assertOk()
                ->assertInertia(fn ($page) => $page
                    ->component('reports')
                    ->where('report.type', $type)
                    ->where('filters.type', $type)
                    // `types` is the option list offered in the picker.
                    ->where('types', fn ($types) => collect($types)
                        ->pluck('value')
                        ->contains($type))
                );
        }
    }
}
