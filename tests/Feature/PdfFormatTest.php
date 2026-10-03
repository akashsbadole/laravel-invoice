<?php

namespace Tests\Feature;

use App\Enums\DocumentType;
use App\Models\BusinessSetting;
use App\Models\Invoice;
use App\Models\User;
use App\Services\InvoicePdfService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The PDF is the document that actually leaves the shop, so both templates
 * have to render for every document type — and pick the right one.
 */
class PdfFormatTest extends TestCase
{
    use RefreshDatabase;

    private function jewelryInvoice(User $user, string $documentType = DocumentType::JewelryInvoice->value, string $taxMode = 'cgst_sgst'): Invoice
    {
        $customer = $this->customerFor($user, [
            'tax_number' => '32AABCA1234C1Z5',
            'state_code' => '32',
        ]);

        $this->actingAs($user)->post(route('invoices.store'), [
            'customer_id' => $customer->id,
            'document_type' => $documentType,
            'invoice_date' => now()->toDateString(),
            'pricing_mode' => 'jewelry_calculated',
            'tax_mode' => $taxMode,
            'tax_rate' => 3,
            'discount' => 500,
            'invoice_charges' => [],
            'items' => [[
                'item_name' => '22K Gold Necklace',
                'hsn_code' => '711311',
                'quantity' => 1,
                'rate_type' => 'per_gram',
                'rate' => 7150,
                'net_weight' => 15.2,
                'metal_type' => 'Gold',
                'purity' => '22K',
                'tax_rate' => 3,
                'discount' => 0,
                'charges' => [],
            ]],
        ])->assertRedirect();

        return Invoice::where('document_type', $documentType)->latest('id')->firstOrFail();
    }

    public function test_a_quotation_renders_the_quotation_template(): void
    {
        $user = $this->adminFor();
        $invoice = $this->jewelryInvoice($user, DocumentType::Quotation->value);

        $this->assertSame('pdf.quotation', app(InvoicePdfService::class)->viewName($invoice));

        $response = $this->actingAs($user)->get(route('invoices.pdf', $invoice));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }

    public function test_an_invoice_renders_the_tax_invoice_template(): void
    {
        $user = $this->adminFor();
        $invoice = $this->jewelryInvoice($user);

        $this->assertSame('pdf.invoice', app(InvoicePdfService::class)->viewName($invoice));

        $this->actingAs($user)->get(route('invoices.pdf', $invoice))->assertOk();
    }

    public function test_the_pan_is_derived_from_the_sellers_gstin(): void
    {
        $user = $this->adminFor();
        $invoice = $this->jewelryInvoice($user);

        // The PAN belongs to the seller, so it comes from the business's own
        // GSTIN — the buyer's GSTIN must not produce a seller's PAN.
        $this->inTenant($user, function () use ($user): void {
            BusinessSetting::forTenant($user->tenant_id)->update([
                'tax_number' => '32AABCA1234C1Z5',
            ]);
        });

        $data = app(InvoicePdfService::class)->viewData($invoice->refresh());

        // A GSTIN is state(2) + PAN(10) + entity + Z + checksum.
        $this->assertSame('AABCA1234C', $data['pan']);
        $this->assertSame('32', $data['placeOfSupply']);
    }

    public function test_the_hsn_summary_groups_taxable_value_and_splits_tax(): void
    {
        $user = $this->adminFor();
        $invoice = $this->jewelryInvoice($user);

        $summary = app(InvoicePdfService::class)->viewData($invoice)['hsnSummary'];

        $this->assertCount(1, $summary);
        $this->assertSame('711311', $summary[0]['hsn']);
        $this->assertSame(3.0, (float) $summary[0]['rate']);
        // 3% intrastate splits evenly rather than showing a fake IGST line.
        $this->assertGreaterThan(0, $summary[0]['cgst']);
        $this->assertEqualsWithDelta($summary[0]['cgst'], $summary[0]['sgst'], 0.01);
        $this->assertSame(0.0, $summary[0]['igst']);
        $this->assertGreaterThan(0, $summary[0]['taxable']);
    }

    public function test_an_exchange_credit_line_is_excluded_from_the_hsn_summary(): void
    {
        $user = $this->adminFor();
        $customer = $this->customerFor($user);

        $this->actingAs($user)->post(route('invoices.store'), [
            'customer_id' => $customer->id,
            'document_type' => DocumentType::JewelryInvoice->value,
            'invoice_date' => now()->toDateString(),
            'pricing_mode' => 'jewelry_calculated',
            'tax_mode' => 'cgst_sgst',
            'tax_rate' => 0,
            'discount' => 0,
            'invoice_charges' => [],
            'items' => [
                [
                    'item_name' => 'New gold ring',
                    'hsn_code' => '711311',
                    'quantity' => 1,
                    'rate_type' => 'per_gram',
                    'rate' => 7000,
                    'net_weight' => 5,
                    'metal_type' => 'Gold',
                    'purity' => '22K',
                    'tax_rate' => 3,
                    'discount' => 0,
                    'charges' => [],
                ],
                [
                    'item_name' => 'Old gold exchange',
                    'line_type' => 'exchange_credit',
                    'hsn_code' => '711311',
                    'quantity' => 1,
                    'rate_type' => 'per_gram',
                    'rate' => 6500,
                    'net_weight' => 5,
                    'metal_type' => 'Gold',
                    'purity' => '22K',
                    'tax_rate' => 0,
                    'discount' => 0,
                    'charges' => [],
                ],
            ],
        ])->assertRedirect();

        $invoice = Invoice::where('document_type', DocumentType::JewelryInvoice->value)->latest('id')->firstOrFail();

        $summary = app(InvoicePdfService::class)->viewData($invoice)['hsnSummary'];

        // Only the sale counts as a supply.
        $this->assertCount(1, $summary);
        $this->assertSame(35000.0, (float) $summary[0]['taxable']);
    }

    public function test_the_amount_in_words_is_rendered_for_an_invoice(): void
    {
        $user = $this->adminFor();
        $invoice = $this->jewelryInvoice($user);

        $words = app(InvoicePdfService::class)->viewData($invoice)['totalInWords'];

        $this->assertNotSame('', $words);
        $this->assertStringEndsWith('only', $words);
        $this->assertStringContainsString('rupees', $words);
    }

    public function test_an_invoice_without_a_seller_gstin_still_renders(): void
    {
        $user = $this->adminFor();
        $invoice = $this->jewelryInvoice($user);

        $this->inTenant($user, function () use ($user): void {
            BusinessSetting::forTenant($user->tenant_id)->update(['tax_number' => null]);
        });

        $data = app(InvoicePdfService::class)->viewData($invoice->refresh());

        // A missing GSTIN means no PAN can be derived, so the field stays null
        // and the template prints nothing rather than an empty label.
        $this->assertNull($data['pan']);

        $this->actingAs($user)->get(route('invoices.pdf', $invoice))->assertOk();
    }
}
