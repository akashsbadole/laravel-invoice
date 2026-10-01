<?php

namespace Tests\Unit;

use App\Enums\LineType;
use App\Enums\PricingMode;
use App\Services\InvoiceCalculationService;
use Tests\TestCase;

class ExchangeCreditTest extends TestCase
{
    protected InvoiceCalculationService $calculator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->calculator = new InvoiceCalculationService;
    }

    /**
     * @param  array<string,mixed>  $overrides
     * @return array<string,mixed>
     */
    protected function invoice(array $overrides = []): array
    {
        return array_merge([
            'pricing_mode' => PricingMode::JewelryCalculated->value,
            'tax_mode' => 'single',
            'invoice_charges' => [],
        ], $overrides);
    }

    /**
     * @param  array<string,mixed>  $overrides
     * @return array<string,mixed>
     */
    protected function newGoldRing(array $overrides = []): array
    {
        return array_merge([
            'item_name' => 'New gold ring',
            'quantity' => 1,
            'net_weight' => 10,
            'rate_type' => 'per_gram',
            'rate' => 6000,
            'tax_rate' => 3,
            'discount' => 0,
            'charges' => [],
        ], $overrides);
    }

    /**
     * @param  array<string,mixed>  $overrides
     * @return array<string,mixed>
     */
    protected function oldGold(array $overrides = []): array
    {
        return array_merge([
            'item_name' => 'Old gold exchange',
            'line_type' => LineType::ExchangeCredit->value,
            'quantity' => 1,
            'metal_type' => 'Gold',
            'purity' => '22K',
            'net_weight' => 10,
            'rate_type' => 'per_gram',
            'rate' => 5500,
            'tax_rate' => 3,
            'discount' => 0,
            'charges' => [],
        ], $overrides);
    }

    public function test_exchange_credit_is_stored_as_a_negative_line(): void
    {
        $result = $this->calculator->calculate($this->invoice([
            'items' => [$this->newGoldRing(), $this->oldGold()],
        ]));

        $exchange = $result['items'][1];

        $this->assertSame(LineType::ExchangeCredit->value, $exchange['line_type']);
        $this->assertSame(-55000.0, $exchange['base_value']);
        $this->assertSame(-55000.0, $exchange['total']);
    }

    public function test_exchange_credit_never_carries_gst(): void
    {
        $result = $this->calculator->calculate($this->invoice([
            'items' => [$this->newGoldRing(), $this->oldGold()],
        ]));

        // GST is charged on the full value of the new goods: 60,000 × 3%.
        // The old metal handed back is not a supply, so it carries none.
        $this->assertSame(1800.0, $result['items'][0]['tax']);
        $this->assertSame(0.0, $result['items'][1]['tax']);
        $this->assertSame(1800.0, $result['tax']);
    }

    public function test_the_credit_reduces_what_the_customer_owes(): void
    {
        $result = $this->calculator->calculate($this->invoice([
            'items' => [$this->newGoldRing(), $this->oldGold()],
        ]));

        // 60,000 sale − 55,000 credit = 5,000, plus 1,800 GST = 6,800
        $this->assertSame(5000.0, $result['subtotal']);
        $this->assertSame(6800.0, $result['grand_total']);
    }

    public function test_exchange_credit_ignores_charges_and_discount(): void
    {
        $withExtras = $this->calculator->calculate($this->invoice([
            'items' => [$this->oldGold(['discount' => 500, 'charges' => []])],
        ]));

        $this->assertSame(0.0, $withExtras['items'][0]['discount']);
        $this->assertSame(0.0, $withExtras['items'][0]['charges_total']);
    }

    public function test_an_oversized_credit_never_produces_a_negative_invoice(): void
    {
        $result = $this->calculator->calculate($this->invoice([
            'items' => [$this->newGoldRing(), $this->oldGold(['rate' => 90000])],
        ]));

        $this->assertSame(0.0, $result['grand_total']);
    }

    public function test_a_normal_sale_line_defaults_to_the_sale_type(): void
    {
        $result = $this->calculator->calculate($this->invoice([
            'items' => [$this->newGoldRing()],
        ]));

        $this->assertSame(LineType::Sale->value, $result['items'][0]['line_type']);
    }
}
