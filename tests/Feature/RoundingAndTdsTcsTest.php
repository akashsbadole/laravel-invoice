<?php

namespace Tests\Feature;

use App\Enums\DocumentType;
use App\Enums\InvoiceStatus;
use App\Models\BusinessSetting;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Owner pain: "the total on screen must be the total on the bill, in the
 * way MY business rounds it — and my buyer withholds TDS / my sale carries
 * TCS without me re-keying it."
 */
class RoundingAndTdsTcsTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;

    protected function payload(array $overrides = []): array
    {
        return array_merge([
            'business_name' => 'Bill Smith Jewellers',
            'industry' => 'jewelry',
            'unit_label' => 'g',
            'invoice_prefix' => 'INV-',
            'invoice_number_start' => 1,
            'default_tax_rate' => 3,
            'default_currency' => 'INR',
            'receipt_width' => 80,
            'state_code' => '27',
            'sms_driver' => 'log',
            'sms_country_code' => '+91',
            'quotation_prefix' => 'QT-',
            'next_quotation_sequence' => 1,
            'challan_prefix' => 'CH-',
            'next_challan_sequence' => 1,
        ], $overrides);
    }

    /**
     * @param  array<string,mixed>  $overrides
     */
    protected function createInvoice(array $overrides = []): Invoice
    {
        $this->owner ??= $this->adminFor();
        $customer = $this->customerFor($this->owner);

        $this->actingAs($this->owner)->post(route('invoices.store'), array_merge([
            'customer_id' => $customer->id,
            'document_type' => DocumentType::GeneralInvoice->value,
            'invoice_date' => now()->toDateString(),
            'pricing_mode' => 'manual',
            'tax_mode' => 'single',
            'tax_rate' => 0,
            'discount' => 0,
            'invoice_charges' => [],
            'items' => [[
                'item_name' => 'Service line',
                'quantity' => 1,
                'rate_type' => 'fixed',
                'rate' => 1000,
                'tax_rate' => 0,
                'discount' => 0,
                'charges' => [],
            ]],
        ], $overrides))->assertRedirect()->assertSessionHasNoErrors();

        return Invoice::where('document_type', DocumentType::GeneralInvoice->value)->latest('id')->firstOrFail();
    }

    public function test_nearest_rupee_rounding_is_the_default_and_surfaces_a_round_off_line(): void
    {
        $invoice = $this->createInvoice([
            'items' => [[
                'item_name' => 'Service line',
                'quantity' => 1,
                'rate_type' => 'fixed',
                'rate' => 999.50,
                'tax_rate' => 0,
                'discount' => 0,
                'charges' => [],
            ]],
        ]);

        $this->assertSame(999.5, (float) $invoice->subtotal);
        $this->assertSame(1000.0, (float) $invoice->grand_total);
        $this->assertSame(0.5, (float) $invoice->round_off);
    }

    public function test_two_decimal_rounding_keeps_the_paise_and_never_reports_a_round_off(): void
    {
        $this->owner = $this->adminFor();
        BusinessSetting::forTenant($this->owner->tenant_id)
            ->update(['rounding_mode' => 'two_decimals']);

        $invoice = $this->createInvoice([
            'items' => [[
                'item_name' => 'Service line',
                'quantity' => 1,
                'rate_type' => 'fixed',
                'rate' => 999.50,
                'tax_rate' => 0,
                'discount' => 0,
                'charges' => [],
            ]],
        ]);

        $this->assertSame(999.5, (float) $invoice->grand_total);
        $this->assertSame(0.0, (float) $invoice->round_off);
        $this->assertSame(999.5, (float) $invoice->balance_amount);
    }

    public function test_the_rounding_rule_is_saved_from_the_settings_page(): void
    {
        $owner = $this->adminFor();

        $this->actingAs($owner)->post(route('business.update'), $this->payload([
            'rounding_mode' => 'two_decimals',
        ]))->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame(
            'two_decimals',
            BusinessSetting::forTenant($owner->tenant_id)->rounding_mode,
        );
    }

    public function test_an_unknown_rounding_rule_is_rejected(): void
    {
        $owner = $this->adminFor();

        $this->actingAs($owner)->post(route('business.update'), $this->payload([
            'rounding_mode' => 'bankers_round',
        ]))->assertSessionHasErrors('rounding_mode');
    }

    public function test_tcs_is_added_to_the_total_the_customer_owes(): void
    {
        $invoice = $this->createInvoice(['tcs_rate' => 1]);

        $this->assertSame(10.0, (float) $invoice->tcs_amount);
        $this->assertSame(1010.0, (float) $invoice->grand_total);
        // TCS is collected, so the balance is the full billed amount.
        $this->assertSame(1010.0, (float) $invoice->balance_amount);
    }

    public function test_tds_is_withheld_so_the_buyer_never_pays_it_in_cash(): void
    {
        $invoice = $this->createInvoice(['tds_rate' => 10]);

        $this->assertSame(100.0, (float) $invoice->tds_amount);
        // The invoice total is untouched — TDS is not a discount.
        $this->assertSame(1000.0, (float) $invoice->grand_total);
        // What the customer still owes excludes the withheld tax.
        $this->assertSame(900.0, (float) $invoice->balance_amount);
        $this->assertSame(InvoiceStatus::Unpaid, $invoice->status);
    }

    public function test_paying_the_amount_net_of_tds_settles_the_invoice(): void
    {
        $invoice = $this->createInvoice(['tds_rate' => 10]);

        $invoice->payments()->create([
            'amount' => 900,
            'payment_date' => now()->toDateString(),
            'payment_method' => 'cash',
            'tenant_id' => $invoice->tenant_id,
        ]);
        $invoice->load('payments');
        $invoice->recalculatePaymentStatus();
        $invoice->save();

        $fresh = $invoice->fresh();
        $this->assertSame(900.0, (float) $fresh->paid_amount);
        $this->assertSame(0.0, (float) $fresh->balance_amount);
        $this->assertSame(InvoiceStatus::Paid, $fresh->status);
    }

    public function test_tds_and_tcs_rates_must_be_a_valid_percentage(): void
    {
        $owner = $this->adminFor();
        $customer = $this->customerFor($owner);

        $this->actingAs($owner)->post(route('invoices.store'), [
            'customer_id' => $customer->id,
            'document_type' => DocumentType::GeneralInvoice->value,
            'invoice_date' => now()->toDateString(),
            'pricing_mode' => 'manual',
            'tax_mode' => 'single',
            'tax_rate' => 0,
            'discount' => 0,
            'tcs_rate' => 140,
            'tds_rate' => -5,
            'invoice_charges' => [],
            'items' => [[
                'item_name' => 'Service line',
                'quantity' => 1,
                'rate_type' => 'fixed',
                'rate' => 1000,
                'tax_rate' => 0,
                'discount' => 0,
                'charges' => [],
            ]],
        ])->assertSessionHasErrors(['tcs_rate', 'tds_rate']);
    }

    public function test_the_show_page_hands_the_withholding_figures_to_the_ui(): void
    {
        $invoice = $this->createInvoice(['tcs_rate' => 1, 'tds_rate' => 10]);

        $this->actingAs($this->owner)->get(route('invoices.show', $invoice))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('invoices/show')
                ->where('invoice.tds_rate', '10.00')
                ->where('invoice.tds_amount', '100.00')
                ->where('invoice.tcs_rate', '1.00')
                ->where('invoice.tcs_amount', '10.00'));
    }

    public function test_the_settings_page_offers_the_rounding_rule(): void
    {
        $this->actingAs($this->adminFor())->get(route('business.edit'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('settings/business')
                ->where('settings.rounding_mode', 'nearest_rupee'));
    }
}
