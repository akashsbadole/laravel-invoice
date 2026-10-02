<?php

namespace Tests\Feature;

use App\Enums\DocumentType;
use App\Enums\LineType;
use App\Enums\TaxMode;
use App\Models\BusinessSetting;
use App\Models\Invoice;
use App\Models\Tenant;
use App\Services\EInvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class EInvoiceGenerationTest extends TestCase
{
    use RefreshDatabase;

    protected const SELLER_GSTIN = '27ABCDE1234F1Z5';

    protected const IRN = 'a5c12e0d80f2b1e9c3a4d5e6f7a8b9c0d1e2f3a4b5c6d7e8f9a0b1c2d3e4f5a6';

    protected function gstrInvoice(array $itemOverrides = [], array $options = [], ?string $industry = null): Invoice
    {
        // Rate types are validated against the tenant's industry, so a line
        // priced per litre needs an industry that actually sells by volume.
        $user = $this->adminFor($industry
            ? Tenant::factory()->forIndustry($industry)->create()
            : null);
        $customer = $this->customerFor($user, [
            'customer_type' => 'business',
            'tax_number' => '29AAAAA0000A1Z5',
            'state_code' => '29',
        ]);

        $settings = BusinessSetting::forTenant($user->tenant_id);
        $settings->update([
            'tax_number' => self::SELLER_GSTIN,
            'state_code' => '27',
            'address' => 'Shop 4, MG Road',
            'pincode' => '380001',
        ]);

        $this->actingAs($user)->post(route('invoices.store'), array_merge([
            'customer_id' => $customer->id,
            'document_type' => DocumentType::GeneralInvoice->value,
            'invoice_date' => now()->toDateString(),
            'pricing_mode' => 'manual',
            'tax_mode' => TaxMode::Igst->value,
            'tax_rate' => 0,
            'discount' => 0,
            'invoice_charges' => [],
            'items' => [array_merge([
                'item_name' => 'Bracelet',
                'quantity' => 1,
                'hsn_code' => '711311',
                'rate_type' => 'per_piece',
                'rate' => 1000,
                'tax_rate' => 18,
                'discount' => 0,
                'charges' => [],
            ], $itemOverrides)],
        ], $options))->assertRedirect();

        return Invoice::firstOrFail();
    }

    public function test_log_driver_marks_the_invoice_pending_without_calling_out(): void
    {
        config(['services.einvoice.driver' => 'log']);
        Log::spy();
        Http::fake();

        $invoice = $this->gstrInvoice();

        $this->actingAs($invoice->tenant->users()->first())
            ->post(route('invoices.einvoice', $invoice))
            ->assertRedirect();

        $this->assertSame('pending', $invoice->refresh()->einvoice_status);
        $this->assertNull($invoice->irn);
        Http::assertNothingSent();
        Log::shouldHaveReceived('info')->once();
    }

    public function test_api_driver_stores_the_returned_irn_and_eway_bill(): void
    {
        config([
            'services.einvoice.driver' => 'api',
            'services.einvoice.api_url' => 'https://irp.test/einvoice',
            'services.einvoice.api_key' => 'test-key',
        ]);

        Http::fake([
            'https://irp.test/*' => Http::response([
                'Irn' => self::IRN,
                'AckNo' => '112010000123456',
                'AckDt' => '2026-10-01T10:00:00Z',
                'EwbNo' => '331001234567',
            ]),
        ]);

        $invoice = $this->gstrInvoice();

        $this->actingAs($invoice->tenant->users()->first())
            ->post(route('invoices.einvoice', $invoice))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $invoice->refresh();

        $this->assertSame('generated', $invoice->einvoice_status);
        $this->assertSame(self::IRN, $invoice->irn);
        $this->assertSame('112010000123456', $invoice->irn_ack_no);
        $this->assertSame('331001234567', $invoice->eway_bill_no);
        $this->assertNotNull($invoice->irn_ack_date);

        Http::assertSent(function ($request) {
            $payload = $request->data();

            return $request->hasHeader('Authorization', 'Bearer test-key')
                && $payload['Version'] === '1.1'
                && $payload['SellerDtls']['Gstin'] === self::SELLER_GSTIN
                && $payload['DocDtls']['No'] !== '';
        });
    }

    public function test_a_provider_error_marks_the_invoice_failed(): void
    {
        config([
            'services.einvoice.driver' => 'api',
            'services.einvoice.api_url' => 'https://irp.test/einvoice',
            'services.einvoice.api_key' => 'test-key',
        ]);

        Http::fake(['https://irp.test/*' => Http::response(['error' => 'bad gstin'], 422)]);

        $invoice = $this->gstrInvoice();

        $this->actingAs($invoice->tenant->users()->first())
            ->post(route('invoices.einvoice', $invoice))
            ->assertRedirect();

        $this->assertSame('failed', $invoice->refresh()->einvoice_status);
    }

    public function test_a_second_generation_is_refused_once_an_irn_exists(): void
    {
        config([
            'services.einvoice.driver' => 'api',
            'services.einvoice.api_url' => 'https://irp.test/einvoice',
            'services.einvoice.api_key' => 'test-key',
        ]);

        Http::fake([
            'https://irp.test/*' => Http::response([
                'Irn' => self::IRN,
                'AckNo' => '1',
                'AckDt' => '2026-10-01T10:00:00Z',
            ]),
        ]);

        $invoice = $this->gstrInvoice();
        $user = $invoice->tenant->users()->first();

        $this->actingAs($user)->post(route('invoices.einvoice', $invoice));
        $this->actingAs($user)
            ->post(route('invoices.einvoice', $invoice))
            ->assertRedirect()
            ->assertSessionHasErrors();

        Http::assertSentCount(1);
    }

    public function test_a_missing_seller_gstin_blocks_generation_with_a_clear_message(): void
    {
        config(['services.einvoice.driver' => 'api']);

        $invoice = $this->gstrInvoice();

        BusinessSetting::forTenant($invoice->tenant_id)->update(['tax_number' => null]);

        $this->actingAs($invoice->tenant->users()->first())
            ->post(route('invoices.einvoice', $invoice))
            ->assertRedirect()
            ->assertSessionHasErrors();

        $this->assertSame('not_required', $invoice->refresh()->einvoice_status);
    }

    public function test_an_item_without_an_hsn_blocks_generation(): void
    {
        config(['services.einvoice.driver' => 'api']);

        $invoice = $this->gstrInvoice(['hsn_code' => null]);

        $this->actingAs($invoice->tenant->users()->first())
            ->post(route('invoices.einvoice', $invoice))
            ->assertRedirect()
            ->assertSessionHasErrors();

        $this->assertSame('not_required', $invoice->refresh()->einvoice_status);
    }

    public function test_a_b2c_customer_uses_the_seller_place_of_supply(): void
    {
        config(['services.einvoice.driver' => 'log']);

        $invoice = $this->gstrInvoice();

        // The fixture customer has a GSTIN, so flip it to a walk-in buyer.
        $invoice->customer->update(['tax_number' => null, 'state_code' => '19']);

        $payload = app(EInvoiceService::class)->buildPayload($invoice->refresh());

        $this->assertSame('B2C', $payload['TranDtls']['SupTyp']);
        $this->assertSame('27', $payload['BuyerDtls']['Pos']);
        $this->assertSame('27', $payload['BuyerDtls']['Stcd']);
        $this->assertNull($payload['BuyerDtls']['Gstin']);
    }

    public function test_an_intra_state_invoice_reports_cgst_and_sgst(): void
    {
        config(['services.einvoice.driver' => 'log']);

        $invoice = $this->gstrInvoice([], ['tax_mode' => TaxMode::CgstSgst->value]);
        $invoice->tax_mode = TaxMode::CgstSgst;

        $payload = app(EInvoiceService::class)->buildPayload($invoice);

        $this->assertSame(90.0, $payload['ItemList'][0]['CgstAmt']);
        $this->assertSame(90.0, $payload['ItemList'][0]['SgstAmt']);
        $this->assertEquals(0, $payload['ItemList'][0]['IgstAmt']);
        $this->assertSame(90.0, $payload['ValDtls']['CgstAmt']);
        $this->assertSame(90.0, $payload['ValDtls']['SgstAmt']);
        $this->assertEquals(0, $payload['ValDtls']['IgstAmt']);
    }

    public function test_the_tax_columns_always_sum_back_to_the_invoice_tax(): void
    {
        config(['services.einvoice.driver' => 'log']);

        $invoice = $this->gstrInvoice();

        $payload = app(EInvoiceService::class)->buildPayload($invoice);

        $values = $payload['ValDtls'];

        $this->assertEquals(
            $values['TaxAmt'],
            $values['CgstAmt'] + $values['SgstAmt'] + $values['IgstAmt'],
        );
        $this->assertEquals(
            $values['TotInvVal'],
            $values['AssVal'] + $values['TaxAmt'] + $values['RoundOff'],
            'Taxable value + tax + round-off must equal the invoice total.',
        );
    }

    public function test_exchange_credit_lines_are_never_sent_to_the_irp(): void
    {
        config(['services.einvoice.driver' => 'log']);

        $invoice = $this->gstrInvoice();
        $user = $invoice->tenant->users()->first();

        $this->actingAs($user)->put(route('invoices.update', $invoice), [
            'customer_id' => $invoice->customer_id,
            'document_type' => DocumentType::GeneralInvoice->value,
            'invoice_date' => $invoice->invoice_date->toDateString(),
            'pricing_mode' => 'manual',
            'tax_mode' => TaxMode::Igst->value,
            'tax_rate' => 0,
            'discount' => 0,
            'invoice_charges' => [],
            'items' => [
                [
                    'item_name' => 'Bracelet',
                    'line_type' => 'sale',
                    'quantity' => 1,
                    'hsn_code' => '711311',
                    'rate_type' => 'per_piece',
                    'rate' => 1000,
                    'tax_rate' => 18,
                    'discount' => 0,
                    'charges' => [],
                ],
                [
                    'item_name' => 'Old gold exchange',
                    'line_type' => LineType::ExchangeCredit->value,
                    'quantity' => 1,
                    'hsn_code' => '711311',
                    'rate_type' => 'fixed',
                    'rate' => 400,
                    'tax_rate' => 18,
                    'discount' => 0,
                    'charges' => [],
                ],
            ],
        ])->assertRedirect();

        $payload = app(EInvoiceService::class)->buildPayload($invoice->refresh());

        $this->assertCount(1, $payload['ItemList']);
        $this->assertSame('Bracelet', $payload['ItemList'][0]['PrdDesc']);
    }

    public function test_the_unit_of_measure_follows_the_rate_type(): void
    {
        config(['services.einvoice.driver' => 'log']);

        $invoice = $this->gstrInvoice(['rate_type' => 'per_gram', 'net_weight' => 5]);

        $payload = app(EInvoiceService::class)->buildPayload($invoice);

        $this->assertSame('GMS', $payload['ItemList'][0]['Unit']);
    }

    public function test_a_litre_priced_line_reports_litres_as_the_unit(): void
    {
        config(['services.einvoice.driver' => 'log']);

        $invoice = $this->gstrInvoice(['rate_type' => 'per_litre', 'quantity' => 5], [], 'paint');

        $payload = app(EInvoiceService::class)->buildPayload($invoice);

        $this->assertSame('LTR', $payload['ItemList'][0]['Unit']);
    }
}
