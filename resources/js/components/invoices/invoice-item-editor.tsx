import { useMemo, useState } from 'react';
import type { ChangeEvent } from 'react';
import { Trash2, X, Zap } from 'lucide-react';
import { computeArea, computeItem } from '@/lib/invoice-calculations';
import {
    GENERIC_ITEM_FIELDS,
    itemFieldLabel,
    rateTypeLabel,
} from '@/lib/industries';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import InputError from '@/components/input-error';
import type { IndustryConfig } from '@/lib/industries';
import type { CatalogItem, ChargeType, InvoiceItemForm, MetalRate, PricingMode } from '@/types/invoice';

const currency = new Intl.NumberFormat('en-IN', { maximumFractionDigits: 2 });

const NUMERIC_ITEM_FIELDS = new Set([
    'warranty_months',
    'length',
    'width',
    'height',
    'wastage_percent',
    'boxes',
]);

export default function InvoiceItemEditor({
    item,
    index,
    pricingMode,
    itemChargeTypes,
    catalogItems,
    metalRates,
    industry,
    groupDiscountPercent = 0,
    errors = {},
    onChange,
    onRemove,
    canRemove,
}: {
    item: InvoiceItemForm;
    index: number;
    pricingMode: PricingMode;
    itemChargeTypes: ChargeType[];
    catalogItems: CatalogItem[];
    metalRates: MetalRate[];
    industry: IndustryConfig;
    /** Customer-group percentage, so this line totals like the invoice does. */
    groupDiscountPercent?: number;
    errors?: Record<string, string>;
    onChange: (index: number, patch: Partial<InvoiceItemForm>) => void;
    onRemove: (index: number) => void;
    canRemove: boolean;
}) {
    const chargeTypesById = new Map(itemChargeTypes.map((ct) => [ct.id, ct]));
    const computed = computeItem(item, pricingMode, chargeTypesById, groupDiscountPercent);
    const area = computeArea(item);
    const isExchange = item.line_type === 'exchange_credit';

    const [catalogQuery, setCatalogQuery] = useState('');

    const selectedCatalog = useMemo(
        () => catalogItems.find((c) => c.id === item.catalog_item_id),
        [catalogItems, item.catalog_item_id],
    );
    const variants = useMemo(
        () =>
            (selectedCatalog?.variants ?? []).filter((v) =>
                // An inactive size stays on invoices it already appears on,
                // but never in the picker for a new line.
                item.catalog_variant_id ? true : v.is_active,
            ),
        [selectedCatalog, item.catalog_variant_id],
    );

    const filteredCatalog = useMemo(() => {
        const query = catalogQuery.trim().toLowerCase();

        if (!query) return catalogItems;

        return catalogItems.filter((c) =>
            [c.name, c.brand, c.item_code, c.model_number, c.hsn_code]
                .filter(Boolean)
                .some((value) => String(value).toLowerCase().includes(query)),
        );
    }, [catalogItems, catalogQuery]);

    const visibleGenericFields = useMemo(
        () => GENERIC_ITEM_FIELDS.filter((f) => industry.item_fields.includes(f)),
        [industry.item_fields],
    );

    const attributeEntries = Object.entries(item.attributes ?? {});

    function field<K extends keyof InvoiceItemForm>(key: K) {
        return {
            value: item[key] as never,
            onChange: (
                e: ChangeEvent<HTMLInputElement>,
            ) =>
                onChange(index, {
                    [key]:
                        e.target.type === 'number'
                            ? e.target.value === ''
                                ? null
                                : Number(e.target.value)
                            : e.target.value,
                } as Partial<InvoiceItemForm>),
        };
    }

    function catalogPatch(catalog: CatalogItem): Partial<InvoiceItemForm> {
        return {
            item_name: catalog.name,
            brand: catalog.brand ?? '',
            item_code: catalog.item_code ?? '',
            model_number: catalog.model_number ?? '',
            hsn_code: catalog.hsn_code ?? '',
            size_label: catalog.size_label ?? '',
            finish: catalog.finish ?? '',
            grade: catalog.grade ?? '',
            specification: catalog.specification ?? '',
            metal_type: catalog.metal_type ?? '',
            purity: catalog.purity ?? '',
            description: catalog.description ?? '',
            attributes: catalog.attributes ?? {},
            rate_type: catalog.rate_type,
            rate: Number(catalog.default_rate ?? item.rate),
            net_weight: Number(catalog.default_net_weight ?? item.net_weight),
            gross_weight: Number(catalog.default_gross_weight ?? item.gross_weight),
            length: catalog.default_length === null ? null : Number(catalog.default_length),
            width: catalog.default_width === null ? null : Number(catalog.default_width),
            wastage_percent:
                catalog.default_wastage_percent === null
                    ? null
                    : Number(catalog.default_wastage_percent),
        };
    }

    function applyCatalogItem(catalogId: string) {
        const catalog = catalogItems.find((c) => String(c.id) === catalogId);
        if (!catalog) return;
        onChange(index, {
            catalog_item_id: catalog.id,
            catalog_variant_id: null,
            ...catalogPatch(catalog),
        });
    }

    /**
     * Swap one sellable form of a product for another — a size, a purity, a
     * colour. The SKU and the price both follow the variant when it carries
     * its own; picking the bare product falls back to the product's own.
     */
    function applyVariant(variantId: string) {
        const catalog = catalogItems.find((c) => c.id === item.catalog_item_id);
        if (!catalog) return;

        const variants = catalog.variants ?? [];
        const variant = variants.find((v) => String(v.id) === variantId);

        if (!variant) {
            onChange(index, { catalog_variant_id: null, ...catalogPatch(catalog) });
            return;
        }

        const rate =
            variant.rate === null || variant.rate === undefined
                ? catalogPatch(catalog).rate
                : Number(variant.rate);

        onChange(index, {
            catalog_item_id: catalog.id,
            catalog_variant_id: variant.id,
            ...catalogPatch(catalog),
            rate,
            item_name: `${catalog.name} (${variant.label})`,
            item_code: variant.item_code ?? catalog.item_code ?? '',
        });
    }

    function clearCatalogItem() {
        setCatalogQuery('');
        onChange(index, { catalog_item_id: null, catalog_variant_id: null });
    }

    function setAttribute(key: string, value: string) {
        const next = { ...item.attributes };
        if (value.trim() === '') {
            delete next[key];
        } else {
            next[key] = value;
        }
        onChange(index, { attributes: next });
    }

    function addAttributeKey() {
        let key = 'spec';
        let suffix = 1;
        while (item.attributes?.[key] !== undefined) {
            key = `spec ${++suffix}`;
        }
        onChange(index, { attributes: { ...item.attributes, [key]: '' } });
    }

    function removeAttribute(key: string) {
        const next = { ...item.attributes };
        delete next[key];
        onChange(index, { attributes: next });
    }

    const matchingRate = industry.uses_metal_rates
        ? metalRates.find(
              (r) =>
                  r.metal_type.toLowerCase() === (item.metal_type || '').toLowerCase() &&
                  r.purity.toLowerCase() === (item.purity || '').toLowerCase(),
          )
        : undefined;

    function useMetalRate() {
        if (matchingRate) {
            onChange(index, { rate: Number(matchingRate.rate_per_gram), rate_type: 'per_gram' });
        }
    }

    function chargeRate(chargeTypeId: number): number {
        return item.charges.find((c) => c.charge_type_id === chargeTypeId)?.rate ?? Number(
            chargeTypesById.get(chargeTypeId)?.default_rate ?? 0,
        );
    }

    function toggleCharge(chargeTypeId: number, enabled: boolean) {
        if (enabled) {
            const defaultRate = Number(chargeTypesById.get(chargeTypeId)?.default_rate ?? 0);
            onChange(index, {
                charges: [...item.charges, { charge_type_id: chargeTypeId, rate: defaultRate }],
            });
        } else {
            onChange(index, {
                charges: item.charges.filter((c) => c.charge_type_id !== chargeTypeId),
            });
        }
    }

    function setChargeRate(chargeTypeId: number, rate: number) {
        onChange(index, {
            charges: item.charges.map((c) =>
                c.charge_type_id === chargeTypeId ? { ...c, rate } : c,
            ),
        });
    }

    return (
        <Card>
            <CardContent className="space-y-4">
                {catalogItems.length > 0 && (
                    <div className="grid gap-1.5">
                        <div className="flex items-center justify-between gap-2">
                            <Label htmlFor={`catalog-${item.key}`}>
                                Pick from catalog (optional)
                            </Label>
                            {item.catalog_item_id && (
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    className="h-6 text-xs text-muted-foreground"
                                    onClick={clearCatalogItem}
                                >
                                    <X className="size-3" />
                                    Clear link
                                </Button>
                            )}
                        </div>
                        <Input
                            id={`catalog-${item.key}`}
                            value={catalogQuery}
                            placeholder="Type to search your catalog by name, brand or code…"
                            onChange={(e) => setCatalogQuery(e.target.value)}
                        />
                        {filteredCatalog.length === 0 ? (
                            <p className="text-xs text-muted-foreground">
                                No catalog item matches “{catalogQuery}”.
                            </p>
                        ) : (
                            <Select
                                value={item.catalog_item_id ? String(item.catalog_item_id) : ''}
                                onValueChange={applyCatalogItem}
                            >
                                <SelectTrigger className="w-full">
                                    <SelectValue placeholder="Select a product…" />
                                </SelectTrigger>
                                <SelectContent>
                                    {filteredCatalog.slice(0, 50).map((c) => (
                                        <SelectItem key={c.id} value={String(c.id)}>
                                            {c.name}
                                            {c.brand ? ` · ${c.brand}` : ''}
                                            {c.item_code ? ` (${c.item_code})` : ''}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        )}
                        {variants.length > 0 && (
                            <div className="grid gap-1.5">
                                <Label htmlFor={`variant-${item.key}`}>
                                    Variant or size
                                </Label>
                                <Select
                                    value={
                                        item.catalog_variant_id
                                            ? String(item.catalog_variant_id)
                                            : ''
                                    }
                                    onValueChange={applyVariant}
                                >
                                    <SelectTrigger
                                        id={`variant-${item.key}`}
                                        className="w-full"
                                    >
                                        <SelectValue placeholder={`${selectedCatalog?.name ?? 'Base product'} (no variant)`} />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="">
                                            {`${selectedCatalog?.name ?? 'Base product'} (no variant)`}
                                        </SelectItem>
                                        {variants.map((v) => (
                                            <SelectItem
                                                key={v.id}
                                                value={String(v.id)}
                                            >
                                                {v.label}
                                                {v.item_code
                                                    ? ` (${v.item_code})`
                                                    : ''}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                        )}
                    </div>
                )}
                <div className="flex items-start justify-between gap-2">
                    <div className="grid flex-1 gap-2 sm:grid-cols-2">
                        <div className="grid gap-1.5">
                            <Label>Item name</Label>
                            <Input
                                placeholder={
                                    isExchange
                                        ? 'e.g. Old gold exchange'
                                        : industry.uses_weight_fields
                                          ? 'e.g. Gold ring'
                                          : 'e.g. Vitrified floor tile'
                                }
                                required
                                {...field('item_name')}
                            />
                            <InputError message={errors.item_name} />
                        </div>
                        <div className="grid gap-1.5">
                            <Label>Item code / SKU (optional)</Label>
                            <Input placeholder="SKU-001" {...field('item_code')} />
                        </div>
                    </div>
                    {canRemove && (
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            className="mt-6 text-muted-foreground hover:text-destructive"
                            onClick={() => onRemove(index)}
                        >
                            <Trash2 className="size-4" />
                        </Button>
                    )}
                </div>

                {industry.uses_weight_fields && (
                    <label className="flex cursor-pointer items-start gap-2 rounded-md border border-dashed p-3 text-sm">
                        <input
                            type="checkbox"
                            checked={isExchange}
                            onChange={(e) =>
                                onChange(index, {
                                    line_type: e.target.checked
                                        ? 'exchange_credit'
                                        : 'sale',
                                    charges: e.target.checked ? [] : item.charges,
                                    discount: e.target.checked ? 0 : item.discount,
                                    tax_rate: e.target.checked ? 0 : item.tax_rate,
                                })
                            }
                            className="mt-0.5 size-4"
                        />
                        <span>
                            <span className="block font-medium">
                                Old gold / exchange credit
                            </span>
                            <span className="block text-xs text-muted-foreground">
                                Deducts the value of metal the customer hands
                                back. No making charges, no GST on this line.
                            </span>
                        </span>
                    </label>
                )}

                {industry.uses_weight_fields && (
                    <>
                        <div className="grid gap-3 sm:grid-cols-3">
                            <div className="grid gap-1.5">
                                <Label>Metal type</Label>
                                <Input placeholder="Gold" {...field('metal_type')} />
                            </div>
                            <div className="grid gap-1.5">
                                <Label>Purity</Label>
                                <Input placeholder="22K" {...field('purity')} />
                            </div>
                            <div className="grid gap-1.5">
                                <Label>HUID (hallmark)</Label>
                                <Input placeholder="AB12CD34" {...field('huid_number')} />
                            </div>
                        </div>

                        <div className="grid gap-3 sm:grid-cols-4">
                            <div className="grid gap-1.5">
                                <Label>Gross wt (g)</Label>
                                <Input type="number" step="0.001" min={0} {...field('gross_weight')} />
                            </div>
                            <div className="grid gap-1.5">
                                <Label>Net wt (g)</Label>
                                <Input type="number" step="0.001" min={0} {...field('net_weight')} />
                                <InputError message={errors.net_weight} />
                            </div>
                            <div className="grid gap-1.5">
                                <Label>Stone wt (g)</Label>
                                <Input type="number" step="0.001" min={0} {...field('stone_weight')} />
                            </div>
                            <div className="grid gap-1.5">
                                <Label>Qty</Label>
                                <Input type="number" min={1} {...field('quantity')} />
                            </div>
                        </div>
                    </>
                )}

                {!industry.uses_weight_fields && (
                    <div className="grid gap-3 sm:grid-cols-3">
                        {visibleGenericFields.map((name) => (
                            <div key={name} className="grid gap-1.5">
                                <Label>{itemFieldLabel(name)}</Label>
                                <Input
                                    type={NUMERIC_ITEM_FIELDS.has(name) ? 'number' : 'text'}
                                    step={name === 'warranty_months' ? 1 : '0.01'}
                                    min={0}
                                    {...field(name)}
                                />
                                <InputError message={errors[name]} />
                            </div>
                        ))}
                        <div className="grid gap-1.5">
                            <Label>Qty</Label>
                            <Input type="number" min={1} {...field('quantity')} />
                        </div>
                    </div>
                )}

                {industry.rate_types.some((t) => t === 'per_sqft' || t === 'per_sqm') && (
                    <div className="grid gap-3 rounded-md border border-dashed p-3 sm:grid-cols-4">
                        <div className="grid gap-1.5 sm:col-span-2">
                            <Label>Dimensions (cm)</Label>
                            <div className="flex gap-2">
                                <Input
                                    type="number"
                                    step="0.01"
                                    min={0}
                                    placeholder="Length"
                                    {...field('length')}
                                />
                                <span className="self-center text-muted-foreground">×</span>
                                <Input
                                    type="number"
                                    step="0.01"
                                    min={0}
                                    placeholder="Width"
                                    {...field('width')}
                                />
                            </div>
                            <InputError message={errors.length} />
                        </div>
                        <div className="grid gap-1.5">
                            <Label>Wastage %</Label>
                            <Input
                                type="number"
                                step="0.01"
                                min={0}
                                max={100}
                                {...field('wastage_percent')}
                            />
                        </div>
                        <div className="grid gap-1.5">
                            <Label>Boxes</Label>
                            <Input type="number" step="0.01" min={0} {...field('boxes')} />
                        </div>
                        <p className="text-xs text-muted-foreground sm:col-span-4">
                            {area > 0
                                ? `Area: ${area.toFixed(2)} sq ft (${(
                                      area * 0.09290304
                                  ).toFixed(3)} sq m) before wastage.`
                                : 'Enter length and width to bill by area.'}
                        </p>
                    </div>
                )}

                {industry.uses_stone_fields && (
                    <>
                        <div className="grid gap-3 sm:grid-cols-3">
                            <div className="grid gap-1.5">
                                <Label>Stone clarity (optional)</Label>
                                <Input placeholder="VS1" {...field('stone_clarity')} />
                            </div>
                            <div className="grid gap-1.5">
                                <Label>Stone color (optional)</Label>
                                <Input placeholder="F" {...field('stone_color')} />
                            </div>
                            <div className="grid gap-1.5">
                                <Label>Stone carat (optional)</Label>
                                <Input type="number" step="0.001" min={0} {...field('stone_carat')} />
                            </div>
                        </div>

                        <div className="grid gap-3 sm:grid-cols-2">
                            <div className="grid gap-1.5">
                                <Label>Certificate no. (optional)</Label>
                                <Input placeholder="GIA-123456" {...field('certificate_number')} />
                            </div>
                            <div className="grid gap-1.5">
                                <Label>HSN code (optional)</Label>
                                <Input placeholder="7113" {...field('hsn_code')} />
                            </div>
                        </div>
                    </>
                )}

                {!industry.uses_weight_fields && (
                    <div className="grid gap-1.5">
                        <Label>HSN code (optional)</Label>
                        <Input placeholder="6910" {...field('hsn_code')} />
                        <InputError message={errors.hsn_code} />
                    </div>
                )}

                {!industry.uses_weight_fields && !industry.uses_stone_fields && (
                    <div className="grid gap-2 rounded-md border border-dashed p-3">
                        <div className="flex items-center justify-between">
                            <Label>Custom attributes</Label>
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                className="h-7 text-xs"
                                onClick={addAttributeKey}
                            >
                                Add field
                            </Button>
                        </div>
                        {attributeEntries.length === 0 ? (
                            <p className="text-xs text-muted-foreground">
                                Record anything specific to your trade — grade,
                                finish, voltage, thread size.
                            </p>
                        ) : (
                            attributeEntries.map(([key, value]) => (
                                <div key={key} className="flex items-center gap-2">
                                    <Input
                                        value={key}
                                        aria-label="Attribute name"
                                        onChange={(e) => {
                                            const next = { ...item.attributes };
                                            delete next[key];
                                            next[e.target.value] = value;
                                            onChange(index, { attributes: next });
                                        }}
                                    />
                                    <Input
                                        value={value}
                                        aria-label={`Value for ${key}`}
                                        onChange={(e) => setAttribute(key, e.target.value)}
                                    />
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        className="shrink-0 text-muted-foreground hover:text-destructive"
                                        onClick={() => removeAttribute(key)}
                                    >
                                        <X className="size-4" />
                                    </Button>
                                </div>
                            ))
                        )}
                    </div>
                )}

                <div className="grid gap-3 border-t pt-4 sm:grid-cols-3">
                    <div className="grid gap-1.5">
                        <Label>Rate basis</Label>
                        <Select
                            value={item.rate_type}
                            onValueChange={(value) =>
                                onChange(index, { rate_type: value as InvoiceItemForm['rate_type'] })
                            }
                            disabled={pricingMode === 'manual'}
                        >
                            <SelectTrigger className="w-full">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {(industry.rate_types.length > 0
                                    ? industry.rate_types
                                    : ['per_piece', 'fixed']
                                ).map((type) => (
                                    <SelectItem key={type} value={type}>
                                        {rateTypeLabel(type)}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                    <div className="grid gap-1.5">
                        <Label>
                            {pricingMode === 'manual' ? 'Amount per unit' : 'Rate'}
                        </Label>
                        <div className="flex gap-1">
                            <Input type="number" step="0.01" min={0} {...field('rate')} />
                            {matchingRate && (
                                <Button
                                    type="button"
                                    size="icon"
                                    variant="outline"
                                    className="shrink-0"
                                    title={`Use today's rate: ₹${matchingRate.rate_per_gram}/g`}
                                    onClick={useMetalRate}
                                >
                                    <Zap className="size-4" />
                                </Button>
                            )}
                        </div>
                        <InputError message={errors.rate} />
                    </div>
                    <div className="grid gap-1.5">
                        <Label>Item tax rate %</Label>
                        <Input type="number" step="0.01" min={0} max={100} {...field('tax_rate')} />
                    </div>
                </div>

                <div className="grid gap-1.5 sm:w-1/3">
                    <Label>Item discount (₹)</Label>
                    <Input type="number" step="0.01" min={0} {...field('discount')} />
                    {/* The group price is already in the totals, so the empty
                        box has to say where the discount came from. */}
                    {groupDiscountPercent > 0 && !isExchange && (item.discount || 0) <= 0 && (
                        <p className="text-xs text-muted-foreground">
                            {groupDiscountPercent}% group discount applied — ₹
                            {currency.format(computed.discount)}
                        </p>
                    )}
                </div>

                {pricingMode === 'jewelry_calculated' && itemChargeTypes.length > 0 && (
                    <div className="space-y-2 border-t pt-4">
                        <Label className="text-sm">Charges</Label>
                        <div className="grid gap-2 sm:grid-cols-2">
                            {itemChargeTypes.map((ct) => {
                                const enabled = item.charges.some((c) => c.charge_type_id === ct.id);
                                return (
                                    <div
                                        key={ct.id}
                                        className="flex items-center gap-2 rounded-md border p-2"
                                    >
                                        <input
                                            type="checkbox"
                                            id={`charge-${index}-${ct.id}`}
                                            checked={enabled}
                                            onChange={(e) => toggleCharge(ct.id, e.target.checked)}
                                            className="size-4"
                                        />
                                        <label
                                            htmlFor={`charge-${index}-${ct.id}`}
                                            className="flex-1 text-sm"
                                        >
                                            {ct.name}
                                            <span className="ml-1 text-xs text-muted-foreground">
                                                (
                                                {ct.calculation_type === 'percentage'
                                                    ? '%'
                                                    : ct.calculation_type === 'per_gram'
                                                      ? '₹/g'
                                                      : ct.calculation_type === 'per_carat'
                                                        ? '₹/ct'
                                                        : '₹'}
                                                )
                                            </span>
                                        </label>
                                        {enabled && (
                                            <Input
                                                type="number"
                                                step="0.01"
                                                className="w-20"
                                                value={chargeRate(ct.id)}
                                                onChange={(e) =>
                                                    setChargeRate(ct.id, Number(e.target.value))
                                                }
                                            />
                                        )}
                                    </div>
                                );
                            })}
                        </div>
                    </div>
                )}

                <div className="flex flex-wrap items-center justify-end gap-4 border-t pt-3 text-sm">
                    <span className="text-muted-foreground">
                        Base ₹{currency.format(computed.base_value)}
                    </span>
                    {computed.charges_total > 0 && (
                        <span className="text-muted-foreground">
                            Charges ₹{currency.format(computed.charges_total)}
                        </span>
                    )}
                    {computed.tax > 0 && (
                        <span className="text-muted-foreground">
                            Tax ₹{currency.format(computed.tax)}
                        </span>
                    )}
                    <span className="font-semibold">
                        Line total ₹{currency.format(computed.total)}
                    </span>
                </div>
            </CardContent>
        </Card>
    );
}
