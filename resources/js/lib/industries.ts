import type { RateType } from '@/types/invoice';

export type IndustryKey = string;

export type IndustryOption = {
    key: IndustryKey;
    label: string;
    description: string;
};

/**
 * Mirror of one config/industries.php entry, served by
 * InvoiceController::formProps() so the UI hides trade-specific fields
 * without duplicating the registry.
 */
export type IndustryConfig = IndustryOption & {
    uses_metal_rates: boolean;
    uses_weight_fields: boolean;
    uses_stone_fields: boolean;
    document_type: string;
    pricing_mode: string;
    rate_types: RateType[];
    item_fields: string[];
    template_flags: string[];
    charge_types: string[];
};

/**
 * Item fields that only make sense once the industry is known. Keys must
 * match App\Support\Industry::itemFields().
 */
export const JEWELRY_ONLY_FIELDS = new Set([
    'metal_type',
    'purity',
    'huid_number',
    'stone_clarity',
    'stone_color',
    'stone_carat',
    'certificate_number',
    'gross_weight',
    'net_weight',
    'stone_weight',
]);

/**
 * Mirror of config/industries.php, served to the browser by
 * InvoiceController::formProps() so the UI can hide trade-specific fields
 * without duplicating the registry.
 */
export const INDUSTRY_ITEM_FIELD_LABELS: Record<string, string> = {
    metal_type: 'Metal type',
    purity: 'Purity',
    huid_number: 'HUID',
    stone_clarity: 'Stone clarity',
    stone_color: 'Stone colour',
    stone_carat: 'Stone carat',
    certificate_number: 'Certificate no.',
    gross_weight: 'Gross weight (g)',
    net_weight: 'Net weight (g)',
    stone_weight: 'Stone weight (g)',
    brand: 'Brand',
    model_number: 'Model no.',
    serial_number: 'Serial no.',
    warranty_months: 'Warranty (months)',
    size_label: 'Size',
    finish: 'Finish',
    grade: 'Grade',
    specification: 'Specification',
    batch_number: 'Batch no.',
    length: 'Length (cm)',
    width: 'Width (cm)',
    height: 'Height (cm)',
    wastage_percent: 'Wastage %',
    boxes: 'Boxes',
};

export const RATE_TYPE_LABELS: Record<string, string> = {
    per_gram: 'Per gram (× net wt)',
    per_carat: 'Per carat (× carat)',
    per_piece: 'Per piece (× qty)',
    fixed: 'Fixed amount',
    per_sqft: 'Per sq ft (× area)',
    per_sqm: 'Per sq m (× area)',
    per_meter: 'Per metre (× length)',
    per_kg: 'Per kg (× weight)',
    per_box: 'Per box (× boxes)',
    per_unit: 'Per unit (× qty)',
};

export function rateTypeLabel(value: string): string {
    return RATE_TYPE_LABELS[value] ?? value;
}

export function itemFieldLabel(field: string): string {
    return INDUSTRY_ITEM_FIELD_LABELS[field] ?? field;
}

/**
 * Generic product fields a non-jewelry tenant sees, in display order.
 */
/**
 * Catalog products carry a narrower set than invoice lines — serial numbers
 * and warranties belong to the sold unit, not the catalog product.
 */
export const GENERIC_CATALOG_FIELDS = [
    'brand',
    'size_label',
    'finish',
    'grade',
    'specification',
    'unit_label',
] as const;

export const GENERIC_ITEM_FIELDS = [
    'brand',
    'model_number',
    'serial_number',
    'warranty_months',
    'size_label',
    'finish',
    'grade',
    'specification',
    'batch_number',
] as const;

/**
 * Area / dimension fields, shown for tiles, marble and flooring.
 */
export const AREA_ITEM_FIELDS = ['length', 'width', 'height', 'wastage_percent', 'boxes'] as const;

export function isAreaIndustry(config: IndustryConfig): boolean {
    return config.rate_types.some((type) => type === 'per_sqft' || type === 'per_sqm');
}