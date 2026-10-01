<?php

namespace Tests\Feature;

use App\Enums\DocumentType;
use App\Enums\LineType;
use App\Models\Invoice;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExchangeCreditOnInvoiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_old_gold_line_is_persisted_as_an_exchange_credit(): void
    {
        $user = $this->adminFor(Tenant::factory()->forIndustry('jewelry')->create());
        $customer = $this->customerFor($user);

        $this->actingAs($user)->post(route('invoices.store'), [
            'customer_id' => $customer->id,
            'document_type' => DocumentType::JewelryInvoice->value,
            'invoice_date' => now()->toDateString(),
            'pricing_mode' => 'jewelry_calculated',
            'tax_mode' => 'single',
            'tax_rate' => 0,
            'discount' => 0,
            'invoice_charges' => [],
            'items' => [
                [
                    'item_name' => 'New gold ring',
                    'line_type' => 'sale',
                    'quantity' => 1,
                    'net_weight' => 10,
                    'rate_type' => 'per_gram',
                    'rate' => 6000,
                    'tax_rate' => 3,
                    'discount' => 0,
                    'charges' => [],
                ],
                [
                    'item_name' => 'Old gold exchange',
                    'line_type' => 'exchange_credit',
                    'quantity' => 1,
                    'metal_type' => 'Gold',
                    'purity' => '22K',
                    'net_weight' => 10,
                    'rate_type' => 'per_gram',
                    'rate' => 5500,
                    'tax_rate' => 3,
                    'discount' => 0,
                    'charges' => [],
                ],
            ],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $invoice = Invoice::firstOrFail();
        $credit = $invoice->items()->where('line_type', 'exchange_credit')->firstOrFail();

        $this->assertSame(LineType::ExchangeCredit, $credit->line_type);
        $this->assertEquals(-55000, $credit->base_value);
        $this->assertEquals(5000, $invoice->subtotal);
        $this->assertEquals(6800, $invoice->grand_total);
    }

    public function test_an_unknown_line_type_is_rejected(): void
    {
        $user = $this->adminFor();
        $customer = $this->customerFor($user);

        $this->actingAs($user)->post(route('invoices.store'), [
            'customer_id' => $customer->id,
            'document_type' => DocumentType::GeneralInvoice->value,
            'invoice_date' => now()->toDateString(),
            'pricing_mode' => 'manual',
            'tax_mode' => 'single',
            'tax_rate' => 0,
            'discount' => 0,
            'invoice_charges' => [],
            'items' => [[
                'item_name' => 'Mystery',
                'line_type' => 'discount_line',
                'quantity' => 1,
                'rate_type' => 'fixed',
                'rate' => 100,
                'tax_rate' => 0,
                'discount' => 0,
                'charges' => [],
            ]],
        ])->assertSessionHasErrors('items.0.line_type');
    }
}
