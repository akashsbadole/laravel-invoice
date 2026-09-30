import type { ChangeEvent } from 'react';
import { Trash2, Zap } from 'lucide-react';
import { computeItem } from '@/lib/invoice-calculations';
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
import type { CatalogItem, ChargeType, InvoiceItemForm, MetalRate, PricingMode } from '@/types/invoice';

const currency = new Intl.NumberFormat('en-IN', { maximumFractionDigits: 2 });

export default function InvoiceItemEditor({
    item,
    index,
    pricingMode,
    itemChargeTypes,
    catalogItems,
    metalRates,
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
    errors?: Record<string, string>;
    onChange: (index: number, patch: Partial<InvoiceItemForm>) => void;
    onRemove: (index: number) => void;
    canRemove: boolean;
}) {
    const chargeTypesById = new Map(itemChargeTypes.map((ct) => [ct.id, ct]));
    const computed = computeItem(item, pricingMode, chargeTypesById);

    function field<K extends keyof InvoiceItemForm>(key: K) {
        return {
            value: item[key] as never,
            onChange: (
                e: ChangeEvent<HTMLInputElement>,
            ) =>
                onChange(index, {
                    [key]:
                        e.target.type === 'number'
                            ? Number(e.target.value)
                            : e.target.value,
                } as Partial<InvoiceItemForm>),
        };
    }

    function applyCatalogItem(catalogId: string) {
        const catalog = catalogItems.find((c) => String(c.id) === catalogId);
        if (!catalog) return;
        onChange(index, {
            item_name: catalog.name,
            item_code: catalog.item_code ?? '',
            hsn_code: catalog.hsn_code ?? '',
            metal_type: catalog.metal_type ?? '',
            purity: catalog.purity ?? '',
            description: catalog.description ?? '',
            rate_type: catalog.rate_type,
            rate: Number(catalog.default_rate ?? item.rate),
            net_weight: Number(catalog.default_net_weight ?? item.net_weight),
            gross_weight: Number(catalog.default_gross_weight ?? item.gross_weight),
        });
    }

    const matchingRate = metalRates.find(
        (r) =>
            r.metal_type.toLowerCase() === (item.metal_type || '').toLowerCase() &&
            r.purity.toLowerCase() === (item.purity || '').toLowerCase(),
    );

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
                        <Label>Pick from catalog (optional)</Label>
                        <Select onValueChange={applyCatalogItem}>
                            <SelectTrigger className="w-full">
                                <SelectValue placeholder="Search your saved designs…" />
                            </SelectTrigger>
                            <SelectContent>
                                {catalogItems.map((c) => (
                                    <SelectItem key={c.id} value={String(c.id)}>
                                        {c.name}
                                        {c.item_code ? ` (${c.item_code})` : ''}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                )}
                <div className="flex items-start justify-between gap-2">
                    <div className="grid flex-1 gap-2 sm:grid-cols-2">
                        <div className="grid gap-1.5">
                            <Label>Item name</Label>
                            <Input
                                placeholder="e.g. Gold ring"
                                required
                                {...field('item_name')}
                            />
                            <InputError message={errors.item_name} />
                        </div>
                        <div className="grid gap-1.5">
                            <Label>Item code / SKU (optional)</Label>
                            <Input placeholder="RG-001" {...field('item_code')} />
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
                                <SelectItem value="per_gram">Per gram</SelectItem>
                                <SelectItem value="per_carat">Per carat</SelectItem>
                                <SelectItem value="per_piece">Per piece</SelectItem>
                                <SelectItem value="fixed">Fixed / manual</SelectItem>
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
