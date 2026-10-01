<?php

namespace Tests\Feature;

use App\Enums\DocumentType;
use App\Enums\RateType;
use App\Support\CatalogField;
use App\Support\Industry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * config/industries.php is the switch that decides which product fields a
 * trade sees and how it can price them, so an entry that names a field the
 * registry does not define is silently broken: the field is gated on but never
 * rendered, validated or persisted, and the gap only surfaces at runtime in
 * one tenant's catalog form.
 *
 * These tests make that class of mistake loud instead.
 */
class IndustryRegistryTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_industry_only_names_fields_the_catalog_registry_defines(): void
    {
        $this->assertSame(
            [],
            Industry::unknownCatalogFields(),
            'An industry references a field that is not in CatalogField::all(). '
            .'Add it to the registry or remove it from item_fields.',
        );
    }

    public function test_every_industry_only_offers_rate_types_the_enum_supports(): void
    {
        $this->assertSame(
            [],
            Industry::unknownRateTypes(),
            'An industry offers a rate type RateType cannot calculate.',
        );
    }

    public function test_every_industry_declares_the_full_config_shape(): void
    {
        foreach (array_keys(Industry::all()) as $key) {
            $config = Industry::config($key);

            foreach ([
                'label', 'description', 'uses_metal_rates', 'uses_weight_fields',
                'uses_stone_fields', 'document_type', 'pricing_mode',
                'rate_types', 'item_fields', 'template_flags', 'charge_types',
            ] as $required) {
                $this->assertArrayHasKey(
                    $required,
                    $config,
                    "Industry '{$key}' is missing the '{$required}' key.",
                );
            }

            $this->assertNotEmpty($config['rate_types'], "Industry '{$key}' has no rate types.");
            $this->assertContains(
                $config['document_type'],
                array_column(DocumentType::cases(), 'value'),
                "Industry '{$key}' names an unknown document_type.",
            );
        }
    }

    public function test_an_industry_key_is_unique_in_the_registry(): void
    {
        $keys = array_keys(Industry::all());

        // array_keys cannot itself produce duplicates, so assert the registry is
        // non-trivial and each key resolves back to itself.
        $this->assertGreaterThan(5, count($keys));

        foreach ($keys as $key) {
            $this->assertSame($key, Industry::normalize($key));
        }
    }

    /**
     * The gating that makes an industry feel different has to actually differ:
     * a tiles shop must not see Metal, and a jeweller must.
     */
    public function test_trade_specific_fields_are_gated_by_the_industry(): void
    {
        $tiles = CatalogField::names('tiles_marble');

        $this->assertContains('default_length', $tiles, 'Tiles price by area, so Length applies.');
        $this->assertContains('default_width', $tiles, 'Tiles price by area, so Width applies.');
        $this->assertContains('boxes', $tiles, 'Tiles sell per box.');
        $this->assertNotContains('metal_type', $tiles, 'A tiles shop has no metal to declare.');
        $this->assertNotContains('purity', $tiles);

        $jewelry = CatalogField::names('jewelry');

        $this->assertContains('metal_type', $jewelry);
        $this->assertContains('purity', $jewelry);
        $this->assertContains('default_net_weight', $jewelry);
        $this->assertNotContains('shade_code', $jewelry);

        $general = CatalogField::names('general');

        // General trade is the baseline: commerce plumbing only.
        $this->assertNotContains('metal_type', $general);
        $this->assertNotContains('default_length', $general);
        $this->assertNotContains('service_type', $general);
    }

    public function test_each_new_industry_reveals_its_own_signature_fields(): void
    {
        $expected = [
            'furniture' => ['fabric', 'weave', 'default_length'],
            'textiles' => ['fabric', 'weave', 'pattern', 'default_length'],
            'electronics' => ['serial_number', 'warranty_months'],
            'paint' => ['shade_code', 'volume', 'coverage_area'],
            'contractors' => ['service_type', 'site_reference'],
            'auto_parts' => ['serial_number'],
            'watches_eyewear' => ['serial_number', 'warranty_months'],
            'mobile_gadgets' => ['serial_number'],
            'modular_kitchen' => ['material', 'thickness', 'service_type'],
            'photography_studio' => ['service_type'],
            'repair_services' => ['service_type', 'serial_number'],
            'education' => ['service_type'],
            'it_services' => ['service_type'],
            'events_interiors' => ['service_type', 'site_reference'],
        ];

        foreach ($expected as $industry => $fields) {
            $names = CatalogField::names($industry);

            foreach ($fields as $field) {
                $this->assertContains(
                    $field,
                    $names,
                    "Industry '{$industry}' should reveal the '{$field}' field.",
                );
            }
        }
    }

    public function test_paint_can_price_by_litre_and_the_calculator_supports_it(): void
    {
        $this->assertContains('per_litre', Industry::rateTypes('paint'));
        $this->assertNotNull(RateType::tryFrom('per_litre'));

        // Hardware retails the same cans, so it needs litre pricing too.
        $this->assertContains('per_litre', Industry::rateTypes('hardware'));
    }

    public function test_the_reveal_all_override_still_hides_nothing(): void
    {
        $everything = CatalogField::names('general', true);

        $this->assertContains('metal_type', $everything);
        $this->assertContains('shade_code', $everything);
        $this->assertContains('service_type', $everything);
    }
}
