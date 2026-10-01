<?php

namespace App\Support;

use App\Models\BusinessSetting;

/**
 * The catalog field registry.
 *
 * One description of every product attribute — label, input type and which
 * group it belongs to — so the catalog form, the CSV template, the importer
 * and the validator can never drift apart. Fields are industry-aware by
 * default (driven by `Industry::itemFields()`); a tenant can reveal the whole
 * registry from settings.
 */
final class CatalogField
{
    public const GROUP_IDENTITY = 'identity';

    public const GROUP_PRICING = 'pricing';

    public const GROUP_PHYSICAL = 'physical';

    public const GROUP_STOCK = 'stock';

    /**
     * Groups whose fields are trade-specific. Everything else is commerce
     * plumbing that every business needs regardless of industry.
     */
    private const INDUSTRY_GATED_GROUPS = [self::GROUP_PHYSICAL];

    /**
     * Whether a trade-specific field applies to this industry.
     *
     * Weight columns belong to weight-priced trades and area columns to
     * area-priced ones; the rest are opted into via `item_fields` so a tiles
     * catalog does not show a "Metal" box.
     */
    private static function physicalApplies(string $name, string $industry): bool
    {
        $weightOnly = ['metal_type', 'purity', 'default_net_weight', 'default_gross_weight'];
        $areaOnly = ['default_length', 'default_width', 'default_wastage_percent'];

        if (in_array($name, $weightOnly, true)) {
            return Industry::usesWeightFields($industry);
        }

        if (in_array($name, $areaOnly, true)) {
            return in_array('per_sqft', Industry::rateTypes($industry), true)
                || in_array('per_sqm', Industry::rateTypes($industry), true);
        }

        return in_array($name, Industry::itemFields($industry), true);
    }

    /**
     * @return list<array{name:string,label:string,type:string,group:string,hint?:string,default?:int|float}>
     */
    public static function all(): array
    {
        return [
            // Identity — how the product is found and identified.
            ['name' => 'name', 'label' => 'Product name', 'type' => 'text', 'group' => self::GROUP_IDENTITY],
            ['name' => 'item_code', 'label' => 'Item code / SKU', 'type' => 'text', 'group' => self::GROUP_IDENTITY, 'hint' => 'Used to match rows when re-importing a CSV.'],
            ['name' => 'barcode', 'label' => 'Barcode', 'type' => 'text', 'group' => self::GROUP_IDENTITY],
            ['name' => 'brand', 'label' => 'Brand', 'type' => 'text', 'group' => self::GROUP_IDENTITY],
            ['name' => 'model_number', 'label' => 'Model number', 'type' => 'text', 'group' => self::GROUP_IDENTITY],
            ['name' => 'manufacturer', 'label' => 'Manufacturer', 'type' => 'text', 'group' => self::GROUP_IDENTITY],
            ['name' => 'country_of_origin', 'label' => 'Country of origin', 'type' => 'text', 'group' => self::GROUP_IDENTITY],
            ['name' => 'hsn_code', 'label' => 'HSN / SAC code', 'type' => 'text', 'group' => self::GROUP_IDENTITY, 'hint' => 'Required for GST invoices.'],
            ['name' => 'description', 'label' => 'Description', 'type' => 'textarea', 'group' => self::GROUP_IDENTITY],

            // Pricing — what it sells for and in what pack.
            ['name' => 'rate_type', 'label' => 'Priced per', 'type' => 'select', 'group' => self::GROUP_PRICING],
            ['name' => 'default_rate', 'label' => 'Selling price', 'type' => 'number', 'group' => self::GROUP_PRICING],
            ['name' => 'cost_price', 'label' => 'Cost price', 'type' => 'number', 'group' => self::GROUP_PRICING, 'hint' => 'Your purchase cost, for margin checks. Never shown to customers.'],
            ['name' => 'tax_inclusive', 'label' => 'Selling price includes tax', 'type' => 'boolean', 'group' => self::GROUP_PRICING],
            ['name' => 'minimum_order_quantity', 'label' => 'Minimum order qty', 'type' => 'number', 'group' => self::GROUP_PRICING, 'default' => 1],
            ['name' => 'pack_size', 'label' => 'Pack size', 'type' => 'text', 'group' => self::GROUP_PRICING, 'hint' => 'e.g. "Box of 12" or "1.2 m length".'],
            ['name' => 'unit_label', 'label' => 'Unit label', 'type' => 'text', 'group' => self::GROUP_PRICING, 'hint' => 'e.g. piece, box, metre, sq ft.'],

            // Physical — trade-specific attributes.
            ['name' => 'metal_type', 'label' => 'Metal', 'type' => 'text', 'group' => self::GROUP_PHYSICAL],
            ['name' => 'purity', 'label' => 'Purity', 'type' => 'text', 'group' => self::GROUP_PHYSICAL],
            ['name' => 'size_label', 'label' => 'Size', 'type' => 'text', 'group' => self::GROUP_PHYSICAL],
            ['name' => 'finish', 'label' => 'Finish', 'type' => 'text', 'group' => self::GROUP_PHYSICAL],
            ['name' => 'grade', 'label' => 'Grade', 'type' => 'text', 'group' => self::GROUP_PHYSICAL],
            ['name' => 'color', 'label' => 'Colour', 'type' => 'text', 'group' => self::GROUP_PHYSICAL],
            ['name' => 'material', 'label' => 'Material', 'type' => 'text', 'group' => self::GROUP_PHYSICAL],
            ['name' => 'thickness', 'label' => 'Thickness', 'type' => 'text', 'group' => self::GROUP_PHYSICAL],
            ['name' => 'specification', 'label' => 'Specification', 'type' => 'text', 'group' => self::GROUP_PHYSICAL],
            ['name' => 'warranty_months', 'label' => 'Warranty (months)', 'type' => 'number', 'group' => self::GROUP_PHYSICAL],
            ['name' => 'default_net_weight', 'label' => 'Net weight', 'type' => 'number', 'group' => self::GROUP_PHYSICAL],
            ['name' => 'default_gross_weight', 'label' => 'Gross weight', 'type' => 'number', 'group' => self::GROUP_PHYSICAL],
            ['name' => 'default_length', 'label' => 'Length', 'type' => 'number', 'group' => self::GROUP_PHYSICAL, 'hint' => 'cm — used with width for area pricing.'],
            ['name' => 'default_width', 'label' => 'Width', 'type' => 'number', 'group' => self::GROUP_PHYSICAL, 'hint' => 'cm — used with length for area pricing.'],
            ['name' => 'default_wastage_percent', 'label' => 'Default wastage %', 'type' => 'number', 'group' => self::GROUP_PHYSICAL],

            // Stock — opt-in per product, so made-to-order work is unaffected.
            ['name' => 'stock_tracked', 'label' => 'Track stock for this product', 'type' => 'boolean', 'group' => self::GROUP_STOCK],
            ['name' => 'stock_quantity', 'label' => 'Quantity in stock', 'type' => 'number', 'group' => self::GROUP_STOCK, 'default' => 0],
            ['name' => 'reorder_level', 'label' => 'Reorder level', 'type' => 'number', 'group' => self::GROUP_STOCK, 'default' => 0, 'hint' => 'Flagged as low when stock falls to this.'],
            ['name' => 'stock_unit', 'label' => 'Stock unit', 'type' => 'text', 'group' => self::GROUP_STOCK],

            // Media & extensibility.
            ['name' => 'image_path', 'label' => 'Product image', 'type' => 'image', 'group' => self::GROUP_IDENTITY],
            ['name' => 'attributes', 'label' => 'Extra attributes', 'type' => 'attributes', 'group' => self::GROUP_IDENTITY, 'hint' => 'Any other key/value details you want to keep.'],
        ];
    }

    /**
     * @return list<array{name:string,label:string,type:string,group:string,hint?:string,default?:int|float}>
     */
    public static function allForIndustry(?string $industry = null, ?bool $showAll = null): array
    {
        $industry = Industry::normalize($industry);

        if ($showAll ?? self::showAll()) {
            return self::all();
        }

        return array_values(array_filter(
            self::all(),
            function (array $field) use ($industry): bool {
                if (! in_array($field['group'], self::INDUSTRY_GATED_GROUPS, true)) {
                    return true;
                }

                return self::physicalApplies($field['name'], $industry);
            },
        ));
    }

    /**
     * @return list<string>
     */
    public static function names(?string $industry = null, ?bool $showAll = null): array
    {
        return array_column(self::allForIndustry($industry, $showAll), 'name');
    }

    /**
     * Numeric columns, so the importer knows what to coerce and validate.
     *
     * @return list<string>
     */
    public static function numericNames(?string $industry = null, ?bool $showAll = null): array
    {
        return array_values(array_filter(
            self::allForIndustry($industry, $showAll),
            fn (array $field) => $field['type'] === 'number',
        ));
    }

    /**
     * @return list<string>
     */
    public static function booleanNames(?string $industry = null, ?bool $showAll = null): array
    {
        return array_values(array_filter(
            self::allForIndustry($industry, $showAll),
            fn (array $field) => $field['type'] === 'boolean',
        ));
    }

    /**
     * The tenant-level override that reveals every field.
     */
    public static function showAll(): bool
    {
        return (bool) BusinessSetting::current()->show_all_catalog_fields;
    }

    /**
     * @return array<string,string>
     */
    public static function groups(): array
    {
        return [
            self::GROUP_IDENTITY => 'Product details',
            self::GROUP_PRICING => 'Pricing & packing',
            self::GROUP_PHYSICAL => 'Specification',
            self::GROUP_STOCK => 'Stock',
        ];
    }
}
