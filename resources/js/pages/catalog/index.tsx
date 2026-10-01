import { Form, Head, Link } from '@inertiajs/react';
import { Download, FileDown, Plus, Upload } from 'lucide-react';
import { useState } from 'react';
import CatalogItemController from '@/actions/App/Http/Controllers/CatalogItemController';
import CatalogItemImportController from '@/actions/App/Http/Controllers/CatalogItemImportController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { download as catalogDownload, template as catalogTemplate } from '@/routes/catalog';
import { dashboard } from '@/routes';
import {
    GENERIC_CATALOG_FIELDS,
    itemFieldLabel,
    rateTypeLabel,
} from '@/lib/industries';
import type { IndustryConfig } from '@/lib/industries';
import type { Paginated } from '@/types/customer';
import type { CatalogItem } from '@/types/invoice';

export default function CatalogPage({
    items,
    filters,
    industry,
}: {
    items: Paginated<CatalogItem>;
    filters: { search?: string };
    industry: IndustryConfig;
}) {
    return (
        <>
            <Head title="Product catalog" />

            <div className="space-y-6 p-4 md:p-6">
                <Heading
                    variant="small"
                    title="Product catalog"
                    description="What you sell, at what price. Build quotations straight from these products. This is not stock — no quantities are tracked."
                />

                <div className="flex flex-wrap gap-2">
                    <AddItemDialog industry={industry} />
                    <ImportDialog />
                    <Button size="sm" variant="outline" asChild>
                        <a href={catalogDownload()}>
                            <Download className="size-4" />
                            Export CSV
                        </a>
                    </Button>
                    <Button size="sm" variant="outline" asChild>
                        <a href={catalogTemplate()}>
                            <FileDown className="size-4" />
                            CSV template
                        </a>
                    </Button>
                </div>

                <Form
                    {...CatalogItemController.index.form()}
                    options={{ preserveState: true }}
                    className="flex gap-2"
                >
                    {() => (
                        <>
                            <Input
                                name="search"
                                defaultValue={filters.search}
                                placeholder="Search name or code"
                            />
                            <Button type="submit" variant="secondary">
                                Search
                            </Button>
                        </>
                    )}
                </Form>

                <Card>
                    <CardContent className="space-y-2 p-4">
                        {items.data.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                No catalog items yet. Add one or import a CSV.
                            </p>
                        ) : (
                            items.data.map((item) => (
                                <div
                                    key={item.id}
                                    className="flex flex-wrap items-center justify-between gap-2 rounded-md border p-3"
                                >
                                    <div>
                                        <div className="flex items-center gap-2">
                                            <span className="font-medium">{item.name}</span>
                                            {item.item_code && (
                                                <Badge variant="outline" className="text-xs">
                                                    {item.item_code}
                                                </Badge>
                                            )}
                                            {item.is_active === false && (
                                                <Badge variant="secondary" className="text-xs">
                                                    inactive
                                                </Badge>
                                            )}
                                        </div>
                                        <p className="text-xs text-muted-foreground">
                                            {(
                                                industry.uses_weight_fields
                                                    ? [item.metal_type, item.purity]
                                                    : [item.brand, item.model_number, item.size_label]
                                            )
                                                .filter(Boolean)
                                                .join(' · ')}
                                            {item.default_rate &&
                                                ` · ₹${item.default_rate}/${item.rate_type.replace('per_', '')}`}
                                        </p>
                                    </div>
                                    <div className="flex gap-2">
                                        <EditItemDialog item={item} industry={industry} />
                                        <Form {...CatalogItemController.destroy.form(item.id)}>
                                            {({ processing }) => (
                                                <Button size="sm" variant="ghost" disabled={processing}>
                                                    Delete
                                                </Button>
                                            )}
                                        </Form>
                                    </div>
                                </div>
                            ))
                        )}
                    </CardContent>
                </Card>

                {items.last_page > 1 && (
                    <nav className="flex flex-wrap justify-center gap-1">
                        {items.links.map((link, i) => (
                            <Link
                                key={i}
                                href={link.url ?? '#'}
                                preserveScroll
                                className={`rounded-md px-3 py-1.5 text-sm ${
                                    link.active
                                        ? 'bg-primary text-primary-foreground'
                                        : 'text-muted-foreground hover:bg-muted'
                                } ${!link.url ? 'pointer-events-none opacity-40' : ''}`}
                                dangerouslySetInnerHTML={{ __html: link.label }}
                            />
                        ))}
                    </nav>
                )}
            </div>
        </>
    );
}

function ItemFields({
    item,
    errors,
    idPrefix,
    industry,
}: {
    item?: CatalogItem;
    errors: Record<string, string>;
    idPrefix: string;
    industry: IndustryConfig;
}) {
    const usesWeights = industry.uses_weight_fields;
    const isArea = industry.rate_types.some((t) => t === 'per_sqft' || t === 'per_sqm');
    const generic = GENERIC_CATALOG_FIELDS.filter((f) =>
        industry.item_fields.includes(f),
    );

    return (
        <div className="space-y-3">
            <div className="grid gap-2">
                <Label htmlFor={`${idPrefix}-name`}>Name</Label>
                <Input id={`${idPrefix}-name`} name="name" defaultValue={item?.name} required />
                <InputError message={errors.name} />
            </div>
            <div className="grid gap-3 sm:grid-cols-2">
                <div className="grid gap-2">
                    <Label htmlFor={`${idPrefix}-code`}>Item code / SKU</Label>
                    <Input id={`${idPrefix}-code`} name="item_code" defaultValue={item?.item_code ?? ''} />
                    <InputError message={errors.item_code} />
                </div>
                <div className="grid gap-2">
                    <Label htmlFor={`${idPrefix}-hsn`}>HSN code</Label>
                    <Input id={`${idPrefix}-hsn`} name="hsn_code" defaultValue={item?.hsn_code ?? ''} />
                </div>
            </div>

            {usesWeights ? (
                <div className="grid gap-3 sm:grid-cols-2">
                    <div className="grid gap-2">
                        <Label htmlFor={`${idPrefix}-metal`}>Metal</Label>
                        <Input id={`${idPrefix}-metal`} name="metal_type" defaultValue={item?.metal_type ?? ''} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor={`${idPrefix}-purity`}>Purity</Label>
                        <Input id={`${idPrefix}-purity`} name="purity" defaultValue={item?.purity ?? ''} />
                    </div>
                </div>
            ) : (
                <div className="grid gap-3 sm:grid-cols-2">
                    {generic.map((field) => (
                        <div key={field} className="grid gap-2">
                            <Label htmlFor={`${idPrefix}-${field}`}>
                                {itemFieldLabel(field)}
                            </Label>
                            <Input
                                id={`${idPrefix}-${field}`}
                                name={field}
                                defaultValue={item?.[field] ?? ''}
                            />
                        </div>
                    ))}
                    <div className="grid gap-2">
                        <Label htmlFor={`${idPrefix}-model`}>Model no.</Label>
                        <Input
                            id={`${idPrefix}-model`}
                            name="model_number"
                            defaultValue={item?.model_number ?? ''}
                        />
                    </div>
                </div>
            )}

            <div className="grid gap-3 sm:grid-cols-2">
                <div className="grid gap-2">
                    <Label htmlFor={`${idPrefix}-ratetype`}>Rate basis</Label>
                    <Select
                        name="rate_type"
                        defaultValue={item?.rate_type ?? industry.rate_types[0] ?? 'per_piece'}
                    >
                        <SelectTrigger id={`${idPrefix}-ratetype`} className="w-full">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            {industry.rate_types.map((type) => (
                                <SelectItem key={type} value={type}>
                                    {rateTypeLabel(type)}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <InputError message={errors.rate_type} />
                </div>
                <div className="grid gap-2">
                    <Label htmlFor={`${idPrefix}-rate`}>Default rate</Label>
                    <Input
                        id={`${idPrefix}-rate`}
                        name="default_rate"
                        type="number"
                        step="0.01"
                        min={0}
                        defaultValue={item?.default_rate ?? ''}
                    />
                </div>
            </div>

            {usesWeights ? (
                <div className="grid gap-3 sm:grid-cols-2">
                    <div className="grid gap-2">
                        <Label htmlFor={`${idPrefix}-net`}>Typical net wt (g)</Label>
                        <Input
                            id={`${idPrefix}-net`}
                            name="default_net_weight"
                            type="number"
                            step="0.001"
                            min={0}
                            defaultValue={item?.default_net_weight ?? ''}
                        />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor={`${idPrefix}-gross`}>Typical gross wt (g)</Label>
                        <Input
                            id={`${idPrefix}-gross`}
                            name="default_gross_weight"
                            type="number"
                            step="0.001"
                            min={0}
                            defaultValue={item?.default_gross_weight ?? ''}
                        />
                    </div>
                </div>
            ) : (
                isArea && (
                    <div className="grid gap-3 sm:grid-cols-3">
                        <div className="grid gap-2">
                            <Label htmlFor={`${idPrefix}-len`}>Typical length (cm)</Label>
                            <Input
                                id={`${idPrefix}-len`}
                                name="default_length"
                                type="number"
                                step="0.01"
                                min={0}
                                defaultValue={item?.default_length ?? ''}
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor={`${idPrefix}-wid`}>Typical width (cm)</Label>
                            <Input
                                id={`${idPrefix}-wid`}
                                name="default_width"
                                type="number"
                                step="0.01"
                                min={0}
                                defaultValue={item?.default_width ?? ''}
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor={`${idPrefix}-wastage`}>Typical wastage %</Label>
                            <Input
                                id={`${idPrefix}-wastage`}
                                name="default_wastage_percent"
                                type="number"
                                step="0.01"
                                min={0}
                                max={100}
                                defaultValue={item?.default_wastage_percent ?? ''}
                            />
                        </div>
                    </div>
                )
            )}

            <div className="grid gap-2">
                <Label htmlFor={`${idPrefix}-desc`}>Description</Label>
                <Textarea id={`${idPrefix}-desc`} name="description" rows={2} defaultValue={item?.description ?? ''} />
            </div>
        </div>
    );
}

function AddItemDialog({ industry }: { industry: IndustryConfig }) {
    const [open, setOpen] = useState(false);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button size="sm">
                    <Plus className="size-4" />
                    Add item
                </Button>
            </DialogTrigger>
            <DialogContent className="max-h-[90vh] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle>New catalog item</DialogTitle>
                </DialogHeader>
                <Form
                    {...CatalogItemController.store.form()}
                    resetOnSuccess
                    onSuccess={() => setOpen(false)}
                    className="space-y-4"
                >
                    {({ processing, errors }) => (
                        <>
                            <ItemFields errors={errors} idPrefix="new" industry={industry} />
                            <DialogFooter>
                                <DialogClose asChild>
                                    <Button variant="secondary" type="button">Cancel</Button>
                                </DialogClose>
                                <Button disabled={processing}>Add</Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

function EditItemDialog({
    item,
    industry,
}: {
    item: CatalogItem;
    industry: IndustryConfig;
}) {
    const [open, setOpen] = useState(false);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button size="sm" variant="outline">Edit</Button>
            </DialogTrigger>
            <DialogContent className="max-h-[90vh] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle>Edit {item.name}</DialogTitle>
                </DialogHeader>
                <Form
                    {...CatalogItemController.update.form(item.id)}
                    onSuccess={() => setOpen(false)}
                    className="space-y-4"
                >
                    {({ processing, errors }) => (
                        <>
                            <ItemFields
                                item={item}
                                errors={errors}
                                idPrefix={`edit-${item.id}`}
                                industry={industry}
                            />
                            <div className="flex items-center gap-2">
                                <input
                                    type="checkbox"
                                    id={`active-${item.id}`}
                                    name="is_active"
                                    defaultChecked={item.is_active !== false}
                                    className="size-4"
                                />
                                <Label htmlFor={`active-${item.id}`}>Active (shown in invoice picker)</Label>
                            </div>
                            <DialogFooter>
                                <DialogClose asChild>
                                    <Button variant="secondary" type="button">Close</Button>
                                </DialogClose>
                                <Button disabled={processing}>Save</Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

function ImportDialog() {
    const [open, setOpen] = useState(false);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button size="sm" variant="outline">
                    <Upload className="size-4" />
                    Import CSV
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Import catalog from CSV</DialogTitle>
                </DialogHeader>
                <p className="text-sm text-muted-foreground">
                    Rows are matched by <code>item_code</code>: existing codes are updated, new ones
                    are added. Only <code>name</code> is required. Download the CSV template for the
                    exact columns.
                </p>
                <Form
                    {...CatalogItemImportController.importCsv.form()}
                    onSuccess={() => setOpen(false)}
                    className="space-y-4"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="csv-file">CSV file</Label>
                                <input
                                    id="csv-file"
                                    name="file"
                                    type="file"
                                    accept=".csv,text/csv"
                                    required
                                    className="block w-full text-sm text-muted-foreground file:mr-3 file:rounded-md file:border-0 file:bg-secondary file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-secondary-foreground"
                                />
                                <InputError message={errors.file} />
                            </div>
                            <DialogFooter>
                                <DialogClose asChild>
                                    <Button variant="secondary" type="button">Cancel</Button>
                                </DialogClose>
                                <Button disabled={processing}>
                                    {processing ? 'Importing…' : 'Import'}
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

CatalogPage.layout = {
    breadcrumbs: [{ title: 'Dashboard', href: dashboard() }],
};
