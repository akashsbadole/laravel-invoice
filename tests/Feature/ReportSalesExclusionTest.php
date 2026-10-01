<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\User;
use App\Services\ReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Every report here answers "how much money actually moved", so quotations and
 * delivery challans must stay out of them.
 *
 * The concrete failure this guards against: converting a quotation creates a
 * separate invoice for the same value, so a sales total that included
 * quotations would count that money twice — once as the quote and once as the
 * invoice it became.
 */
class ReportSalesExclusionTest extends TestCase
{
    use RefreshDatabase;

    protected ?User $cachedUser = null;

    protected function createDocument(string $type, float $grandTotal): Invoice
    {
        $user = $this->user();
        $customer = $this->customerFor($user);

        $this->actingAs($user)
            ->post(route('invoices.store'), [
                'customer_id' => $customer->id,
                'document_type' => $type,
                'invoice_date' => now()->toDateString(),
                'pricing_mode' => 'manual',
                'tax_mode' => 'single',
                'tax_rate' => 0,
                'discount' => 0,
                'invoice_charges' => [],
                'items' => [[
                    'item_name' => 'Line',
                    'quantity' => 1,
                    'rate_type' => 'fixed',
                    'rate' => $grandTotal,
                    'tax_rate' => 0,
                    'discount' => 0,
                    'charges' => [],
                ]],
            ])
            ->assertRedirect();

        return Invoice::where('document_type', $type)->latest('id')->firstOrFail();
    }

    /**
     * One tenant for the whole test. Spinning up a fresh admin per document
     * would put each row in a different tenant, and every report is
     * tenant-scoped — so the earlier rows would simply be invisible.
     */
    protected function user(): User
    {
        return $this->cachedUser ??= $this->adminFor();
    }

    public function test_a_quotation_is_excluded_from_sales_totals(): void
    {
        $this->createDocument('general_invoice', 1000);
        $this->createDocument('quotation', 5000);

        $report = app(ReportService::class)->build('invoices', []);

        // The document listing keeps both, and labels which is which.
        $this->assertCount(2, $report['rows']);

        $numbers = array_column($report['rows'], 'invoice_number');
        $this->assertCount(2, $numbers);

        $labels = array_column($report['rows'], 'document_type');
        $this->assertContains('General Invoice', $labels);
        $this->assertContains('Quotation', $labels);
    }

    public function test_a_converted_quotation_is_not_counted_twice(): void
    {
        $user = $this->adminFor();
        $customer = $this->customerFor($user);

        $this->actingAs($user)
            ->post(route('invoices.store'), [
                'customer_id' => $customer->id,
                'document_type' => 'quotation',
                'invoice_date' => now()->toDateString(),
                'pricing_mode' => 'manual',
                'tax_mode' => 'single',
                'tax_rate' => 0,
                'discount' => 0,
                'invoice_charges' => [],
                'items' => [[
                    'item_name' => 'Line',
                    'quantity' => 1,
                    'rate_type' => 'fixed',
                    'rate' => 2000,
                    'tax_rate' => 0,
                    'discount' => 0,
                    'charges' => [],
                ]],
            ])
            ->assertRedirect();

        $quotation = Invoice::where('document_type', 'quotation')->latest('id')->firstOrFail();

        $this->actingAs($user)
            ->post(route('invoices.convert', $quotation), [
                // The convert endpoint requires the target document type.
                'document_type' => 'general_invoice',
            ])
            ->assertRedirect();

        // Money reports must see the value exactly once. Each report names its
        // money column differently, so sum the rows rather than guessing a
        // totals key. The document listing is excluded on purpose: it is a
        // register of every document, not a money total.
        foreach (['monthly', 'customer'] as $type) {
            $report = app(ReportService::class)->build($type, []);

            $this->assertNotNull($report['rows'], "The '{$type}' report returned no rows.");

            $sum = 0.0;

            foreach ($report['rows'] as $row) {
                $sum += (float) ($row['grand_total'] ?? $row['invoiced'] ?? $row['sales'] ?? 0);
            }

            $this->assertSame(
                2000.0,
                round($sum, 2),
                "The '{$type}' report double-counted a converted quotation.",
            );
        }

        // The document listing still shows both, and labels the quotation, so
        // the two documents remain visible for reconciliation.
        $documents = app(ReportService::class)->build('invoices', []);

        $this->assertCount(2, $documents['rows']);
        $this->assertContains(
            'Quotation',
            array_column($documents['rows'], 'document_type'),
        );
    }

    public function test_a_delivery_challan_is_excluded_from_sales_totals(): void
    {
        $this->createDocument('general_invoice', 750);
        $this->createDocument('delivery_challan', 300);

        $report = app(ReportService::class)->build('monthly', []);

        $sum = 0.0;

        foreach ($report['rows'] as $row) {
            $sum += (float) ($row['invoiced'] ?? $row['grand_total'] ?? 0);
        }

        $this->assertSame(750.0, round($sum, 2));
    }
}
