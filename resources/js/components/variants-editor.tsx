import { Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { CatalogVariant } from '@/types/invoice';

type Row = {
    id: number | null;
    label: string;
    item_code: string;
    rate: string;
    stock_quantity: string;
    is_active: boolean;
};

function toRow(variant?: CatalogVariant): Row {
    return {
        id: variant?.id ?? null,
        label: variant?.label ?? '',
        item_code: variant?.item_code ?? '',
        rate: variant?.rate === null || variant?.rate === undefined ? '' : String(variant.rate),
        stock_quantity: String(variant?.stock_quantity ?? 0),
        is_active: variant?.is_active ?? true,
    };
}

/**
 * Repeatable rows for the sellable forms of one product — a ring in four
 * sizes, a pendant in three purities — posted as variants[<index>][...].
 *
 * Controlled rather than keyed by index: dropping a row renumbers every row
 * after it, and native inputs would otherwise keep the state of the row that
 * used to sit there.
 *
 * The editor is only mounted by the catalog dialogs, so a client that never
 * renders it sends no `variants` key at all and leaves the product alone. A
 * product whose rows are all deleted posts `clear_variants` instead, because
 * an HTML form cannot carry an empty array.
 */
export function VariantsEditor({
    initial = [],
    errors = {},
    defaultRate,
}: {
    initial?: CatalogVariant[];
    errors?: Record<string, string | undefined>;
    defaultRate?: string | null;
}) {
    const [rows, setRows] = useState<Row[]>(() => initial.map(toRow));

    const update = (index: number, patch: Partial<Row>) => {
        setRows((current) => current.map((row, i) => (i === index ? { ...row, ...patch } : row)));
    };

    return (
        <div className="grid gap-3">
            <div className="grid gap-1">
                <Label>Variants</Label>
                <p className="text-xs text-muted-foreground">
                    Sizes, colours or purities of this product. Each one keeps
                    its own code, price and stock.
                </p>
            </div>

            {rows.length === 0 && initial.length > 0 && (
                <input type="hidden" name="clear_variants" value="1" />
            )}

            {rows.map((row, index) => (
                <div key={index} className="grid gap-3 rounded-md border p-3 sm:grid-cols-2">
                    {row.id !== null && (
                        <input type="hidden" name={`variants[${index}][id]`} value={row.id} />
                    )}

                    <div className="grid gap-1.5">
                        <Label htmlFor={`variant-label-${index}`} className="text-xs">
                            Name
                        </Label>
                        <Input
                            id={`variant-label-${index}`}
                            name={`variants[${index}][label]`}
                            value={row.label}
                            placeholder="e.g. Size 12"
                            onChange={(e) => update(index, { label: e.target.value })}
                        />
                        <InputError message={errors[`variants.${index}.label`]} />
                    </div>

                    <div className="grid gap-1.5">
                        <Label htmlFor={`variant-code-${index}`} className="text-xs">
                            Code
                        </Label>
                        <Input
                            id={`variant-code-${index}`}
                            name={`variants[${index}][item_code]`}
                            value={row.item_code}
                            placeholder="e.g. RING-12"
                            onChange={(e) => update(index, { item_code: e.target.value })}
                        />
                        <InputError message={errors[`variants.${index}.item_code`]} />
                    </div>

                    <div className="grid gap-1.5">
                        <Label htmlFor={`variant-rate-${index}`} className="text-xs">
                            Price override
                        </Label>
                        <Input
                            id={`variant-rate-${index}`}
                            name={`variants[${index}][rate]`}
                            type="number"
                            min="0"
                            step="any"
                            inputMode="decimal"
                            value={row.rate}
                            placeholder={defaultRate ? String(defaultRate) : 'Same as product'}
                            onChange={(e) => update(index, { rate: e.target.value })}
                        />
                        <p className="text-xs text-muted-foreground">
                            Leave blank to charge the product&apos;s own price.
                        </p>
                        <InputError message={errors[`variants.${index}.rate`]} />
                    </div>

                    <div className="grid gap-1.5">
                        <Label htmlFor={`variant-stock-${index}`} className="text-xs">
                            Stock
                        </Label>
                        <Input
                            id={`variant-stock-${index}`}
                            name={`variants[${index}][stock_quantity]`}
                            type="number"
                            min="0"
                            step="any"
                            inputMode="decimal"
                            value={row.stock_quantity}
                            onChange={(e) => update(index, { stock_quantity: e.target.value })}
                        />
                        <InputError message={errors[`variants.${index}.stock_quantity`]} />
                    </div>

                    <div className="grid gap-1.5">
                        <Label className="text-xs">On sale</Label>
                        <Select
                            name={`variants[${index}][is_active]`}
                            value={row.is_active ? '1' : '0'}
                            onValueChange={(value) => update(index, { is_active: value === '1' })}
                        >
                            <SelectTrigger className="w-full">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="1">Active</SelectItem>
                                <SelectItem value="0">Hidden (keep on old invoices)</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <div className="flex items-end justify-end">
                        <Button
                            type="button"
                            size="sm"
                            variant="ghost"
                            aria-label={`Remove variant ${index + 1}`}
                            onClick={() => setRows((current) => current.filter((_, i) => i !== index))}
                        >
                            <Trash2 className="size-4" />
                        </Button>
                    </div>
                </div>
            ))}

            <div>
                <Button
                    type="button"
                    size="sm"
                    variant="outline"
                    onClick={() => setRows((current) => [...current, toRow()])}
                >
                    <Plus className="size-4" />
                    Add variant
                </Button>
            </div>
        </div>
    );
}
