<?php

namespace Tests\Unit;

use App\Enums\PricingMode;
use App\Services\InvoiceCalculationService;
use Tests\TestCase;

class InvoiceCalculationServiceTest extends TestCase
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
    protected function item(array $overrides = []): array
    {
        return array_merge([
            'item_name' => 'Item',
            'quantity' => 1,
            'rate_type' => 'per_piece',
            'rate' => 100,
            'discount' => 0,
            'tax_rate' => 0,
            'charges' => [],
        ], $overrides);
    }

    /**
     * @param  array<string,mixed>  $overrides
     * @return array<string,mixed>
     */
    protected function invoice(array $overrides = []): array
    {
        return array_merge([
            'pricing_mode' => PricingMode::Manual->value,
            'tax_mode' => 'single',
        ], $overrides);
    }

    public function test_jewelry_weight_pricing_multiplies_rate_by_net_weight(): void
    {
        $result = $this->calculator->calculate($this->invoice([
            'pricing_mode' => PricingMode::JewelryCalculated->value,
            'items' => [$this->item([
                'quantity' => 2,
                'rate_type' => 'per_gram',
                'rate' => 5000,
                'net_weight' => 10,
            ])],
        ]));

        // 5000/g × 10g × 2 = 100,000
        $this->assertSame(100000.0, $result['items'][0]['base_value']);
    }

    public function test_manual_pricing_mode_treats_the_rate_as_the_amount_per_unit(): void
    {
        $result = $this->calculator->calculate($this->invoice([
            'items' => [$this->item([
                'quantity' => 3,
                'rate_type' => 'per_gram',
                'rate' => 5000,
                'net_weight' => 10,
            ])],
        ]));

        // Manual mode ignores the weight: 5000 × 3 = 15,000
        $this->assertSame(15000.0, $result['items'][0]['base_value']);
    }

    public function test_area_pricing_uses_length_and_width_in_square_feet(): void
    {
        $result = $this->calculator->calculate($this->invoice([
            'items' => [$this->item([
                'quantity' => 100,
                'rate_type' => 'per_sqft',
                'rate' => 50,
                'length' => 60,
                'width' => 60,
            ])],
        ]));

        // 60cm × 60cm = 3600cm² = 3.875 sq ft; 3.875 × 50 × 100
        $this->assertSame(19375.04, $result['items'][0]['base_value']);
    }

    public function test_area_pricing_in_square_metres_uses_metres(): void
    {
        $result = $this->calculator->calculate($this->invoice([
            'items' => [$this->item([
                'rate_type' => 'per_sqm',
                'rate' => 2000,
                'length' => 100,
                'width' => 100,
            ])],
        ]));

        // 100cm × 100cm = 1m² × 2000
        $this->assertSame(2000.0, $result['items'][0]['base_value']);
    }

    public function test_area_pricing_adds_wastage_percent(): void
    {
        $result = $this->calculator->calculate($this->invoice([
            'items' => [$this->item([
                'rate_type' => 'per_sqm',
                'rate' => 2000,
                'length' => 100,
                'width' => 100,
                'wastage_percent' => 10,
            ])],
        ]));

        // 1m² × 1.10 wastage × 2000
        $this->assertSame(2200.0, $result['items'][0]['base_value']);
    }

    public function test_box_pricing_multiplies_by_box_count(): void
    {
        $result = $this->calculator->calculate($this->invoice([
            'items' => [$this->item([
                'quantity' => 5,
                'rate_type' => 'per_box',
                'rate' => 450,
                'boxes' => 4,
            ])],
        ]));

        // 450 × 4 boxes × 5 = 9,000
        $this->assertSame(9000.0, $result['items'][0]['base_value']);
    }

    public function test_box_pricing_defaults_to_one_box_when_count_is_missing(): void
    {
        $result = $this->calculator->calculate($this->invoice([
            'items' => [$this->item([
                'rate_type' => 'per_box',
                'rate' => 450,
            ])],
        ]));

        $this->assertSame(450.0, $result['items'][0]['base_value']);
    }

    public function test_area_pricing_without_dimensions_falls_back_to_the_raw_rate(): void
    {
        $result = $this->calculator->calculate($this->invoice([
            'items' => [$this->item([
                'rate_type' => 'per_sqft',
                'rate' => 50,
            ])],
        ]));

        $this->assertSame(50.0, $result['items'][0]['base_value']);
    }

    public function test_tax_is_applied_after_discount(): void
    {
        $result = $this->calculator->calculate($this->invoice([
            'items' => [$this->item([
                'rate' => 1000,
                'discount' => 200,
                'tax_rate' => 18,
            ])],
        ]));

        $item = $result['items'][0];

        // base_value is the undiscounted line; tax is charged on the
        // discounted amount (1000 − 200 = 800 → 18% = 144).
        $this->assertSame(1000.0, $item['base_value']);
        $this->assertSame(200.0, $item['discount']);
        $this->assertSame(144.0, $item['tax']);
        $this->assertSame(944.0, $item['total']);
    }

    public function test_custom_attributes_are_normalised_to_a_key_value_map(): void
    {
        $result = $this->calculator->calculate($this->invoice([
            'items' => [$this->item([
                'attributes' => ['finish' => 'Matt', '  ', 'grade' => '', 'voltage' => '220V'],
            ])],
        ]));

        $this->assertSame(
            ['finish' => 'Matt', 'voltage' => '220V'],
            $result['items'][0]['attributes'],
        );
    }

    public function test_custom_attributes_accept_a_json_string(): void
    {
        $result = $this->calculator->calculate($this->invoice([
            'items' => [$this->item(['attributes' => '{"size":"4 inch"}'])],
        ]));

        $this->assertSame(['size' => '4 inch'], $result['items'][0]['attributes']);
    }

    public function test_absent_numeric_fields_are_stored_as_null_not_zero(): void
    {
        $result = $this->calculator->calculate($this->invoice([
            'items' => [$this->item()],
        ]));

        $item = $result['items'][0];

        $this->assertNull($item['length']);
        $this->assertNull($item['wastage_percent']);
        $this->assertNull($item['warranty_months']);
        $this->assertNull($item['attributes']);
    }

    public function test_grand_total_equals_subtotal_plus_charges_plus_tax_minus_discount(): void
    {
        $result = $this->calculator->calculate($this->invoice([
            'tax_rate' => 18,
            'items' => [$this->item([
                'rate' => 1000,
                'tax_rate' => 18,
            ])],
        ]));

        $this->assertSame(1000.0, $result['subtotal']);
        $this->assertSame(1180.0, $result['grand_total']);
    }
}
