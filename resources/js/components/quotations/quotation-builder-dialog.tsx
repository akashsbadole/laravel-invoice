import { Form } from '@inertiajs/react';
import { Package, Search } from 'lucide-react';
import { useMemo, useState } from 'react';
import QuotationController from '@/actions/App/Http/Controllers/QuotationController';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';
import { rateTypeLabel } from '@/lib/industries';
import { index as catalogIndex } from '@/routes/catalog';
import type { QuotationProduct } from '@/types/invoice';

const currency = new Intl.NumberFormat('en-IN', {
    style: 'currency',
    currency: 'INR',
    maximumFractionDigits: 2,
});

/**
 * Catalog-first quotation builder: pick products, set quantities, and the
 * quotation form opens pre-filled. Nothing is persisted until it is saved.
 */
export function QuotationBuilderDialog({ products }: { products: QuotationProduct[] }) {
    const [open, setOpen] = useState(false);
    const [query, setQuery] = useState('');
    const [picked, setPicked] = useState<Record<number, { quantity: number; rate: number | null }>>({});

    const filtered = useMemo(() => {
        const term = query.trim().toLowerCase();

        if (!term) return products;

        return products.filter((product) =>
            [product.name, product.brand, product.item_code]
                .filter(Boolean)
                .some((value) => String(value).toLowerCase().includes(term)),
        );
    }, [products, query]);

    const pickedCount = Object.keys(picked).length;

    function toggle(product: QuotationProduct) {
        setPicked((current) => {
            if (current[product.id]) {
                const rest = { ...current };
                delete rest[product.id];
                return rest;
            }

            return {
                ...current,
                [product.id]: {
                    quantity: 1,
                    rate:
                        product.default_rate === null
                            ? null
                            : Number(product.default_rate),
                },
            };
        });
    }

    function setField(
        id: number,
        field: 'quantity' | 'rate',
        value: string,
    ) {
        setPicked((current) => ({
            ...current,
            [id]: {
                ...current[id],
                [field]: field === 'quantity' ? Number(value) : Number(value),
            },
        }));
    }

    return (
        <Dialog
            open={open}
            onOpenChange={(next) => {
                setOpen(next);
                if (!next) {
                    setPicked({});
                    setQuery('');
                }
            }}
        >
            <DialogTrigger asChild>
                <Button variant="outline" className="w-full sm:w-auto">
                    <Package className="size-4" />
                    New quotation
                </Button>
            </DialogTrigger>

            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>Build a quotation from your catalog</DialogTitle>
                    <DialogDescription>
                        Pick the products you are quoting. Quantities and rates
                        can be adjusted before the quotation opens.
                    </DialogDescription>
                </DialogHeader>

                {products.length === 0 ? (
                    <div className="rounded-md border border-dashed p-6 text-center text-sm text-muted-foreground">
                        Your catalog is empty. Add products first, then come
                        back to quote them.
                        <div className="mt-3">
                            <Button size="sm" variant="outline" asChild>
                                <a href={catalogIndex()}>Go to catalog</a>
                            </Button>
                        </div>
                    </div>
                ) : (
                    <Form
                        {...QuotationController.draft.form()}
                        onSuccess={() => setOpen(false)}
                        className="space-y-4"
                    >
                        {({ processing, errors }) => (
                            <>
                                <div className="grid gap-1.5">
                                    <Label htmlFor="quotation-product-search">
                                        Search products
                                    </Label>
                                    <div className="relative">
                                        <Search className="pointer-events-none absolute top-2.5 left-2.5 size-4 text-muted-foreground" />
                                        <Input
                                            id="quotation-product-search"
                                            value={query}
                                            onChange={(event) =>
                                                setQuery(event.target.value)
                                            }
                                            placeholder="Name, brand or code"
                                            className="pl-8"
                                        />
                                    </div>
                                </div>

                                <ul className="divide-y rounded-md border">
                                    {filtered.length === 0 && (
                                        <li className="p-4 text-sm text-muted-foreground">
                                            No product matches “{query}”.
                                        </li>
                                    )}

                                    {filtered.map((product) => {
                                        const selection = picked[product.id];

                                        return (
                                            <li
                                                key={product.id}
                                                className={cn(
                                                    'flex flex-wrap items-center gap-3 p-3',
                                                    selection && 'bg-accent/40',
                                                )}
                                            >
                                                <input
                                                    type="checkbox"
                                                    id={`product-${product.id}`}
                                                    name="items[][catalog_item_id]"
                                                    value={product.id}
                                                    checked={Boolean(selection)}
                                                    onChange={() => toggle(product)}
                                                    className="size-4 shrink-0"
                                                />
                                                <label
                                                    htmlFor={`product-${product.id}`}
                                                    className="min-w-0 flex-1 cursor-pointer"
                                                >
                                                    <span className="block truncate text-sm font-medium">
                                                        {product.name}
                                                    </span>
                                                    <span className="block text-xs text-muted-foreground">
                                                        {[product.brand, product.item_code]
                                                            .filter(Boolean)
                                                            .join(' · ') || '—'}
                                                        {product.default_rate !== null &&
                                                            ` · ${currency.format(
                                                                Number(
                                                                    product.default_rate,
                                                                ),
                                                            )}/${rateTypeLabel(
                                                                product.rate_type,
                                                            )
                                                                .replace('Per ', '')
                                                                .toLowerCase()}`}
                                                    </span>
                                                </label>

                                                {selection && (
                                                    <div className="flex items-center gap-2">
                                                        <div className="w-20">
                                                            <Input
                                                                type="number"
                                                                name={`items[${product.id}][quantity]`}
                                                                min={1}
                                                                value={selection.quantity}
                                                                onChange={(event) =>
                                                                    setField(
                                                                        product.id,
                                                                        'quantity',
                                                                        event
                                                                            .target
                                                                            .value,
                                                                    )
                                                                }
                                                                aria-label={`Quantity for ${product.name}`}
                                                            />
                                                        </div>
                                                        <div className="w-28">
                                                            <Input
                                                                type="number"
                                                                name={`items[${product.id}][rate]`}
                                                                step="0.01"
                                                                min={0}
                                                                value={selection.rate ?? ''}
                                                                onChange={(event) =>
                                                                    setField(
                                                                        product.id,
                                                                        'rate',
                                                                        event
                                                                            .target
                                                                            .value,
                                                                    )
                                                                }
                                                                aria-label={`Rate for ${product.name}`}
                                                            />
                                                        </div>
                                                    </div>
                                                )}
                                            </li>
                                        );
                                    })}
                                </ul>

                                {errors.items && (
                                    <p className="text-sm text-destructive">
                                        {errors.items}
                                    </p>
                                )}

                                <DialogFooter className="gap-2">
                                    <Button
                                        type="button"
                                        variant="secondary"
                                        onClick={() => setOpen(false)}
                                    >
                                        Cancel
                                    </Button>
                                    <Button
                                        type="submit"
                                        disabled={
                                            processing || pickedCount === 0
                                        }
                                    >
                                        {pickedCount === 0
                                            ? 'Pick products'
                                            : `Create quotation (${pickedCount})`}
                                    </Button>
                                </DialogFooter>
                            </>
                        )}
                    </Form>
                )}
            </DialogContent>
        </Dialog>
    );
}