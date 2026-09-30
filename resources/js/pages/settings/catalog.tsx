import { Form, Head, Link } from '@inertiajs/react';
import { Download, Plus, Upload } from 'lucide-react';
import { useState } from 'react';
import CatalogItemController from '@/actions/App/Http/Controllers/Settings/CatalogItemController';
import CatalogItemImportController from '@/actions/App/Http/Controllers/Settings/CatalogItemImportController';
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
import { template as catalogTemplate } from '@/routes/catalog';
import type { Paginated } from '@/types/customer';
import type { CatalogItem } from '@/types/invoice';

export default function CatalogPage({
    items,
    filters,
}: {
    items: Paginated<CatalogItem>;
    filters: { search?: string };
}) {
    return (
        <>
            <Head title="Item catalog" />

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title="Item catalog"
                    description="Saved designs you can pick from when building an invoice. This is not stock — no quantities are tracked."
                />

                <div className="flex flex-wrap gap-2">
                    <AddItemDialog />
                    <ImportDialog />
                    <Button size="sm" variant="outline" asChild>
                        <a href={catalogTemplate().url}>
                            <Download className="size-4" />
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
                                            {[item.metal_type, item.purity].filter(Boolean).join(' · ')}
                                            {item.default_rate && ` · ₹${item.default_rate}/${item.rate_type.replace('per_', '')}`}
                                        </p>
                                    </div>
                                    <div className="flex gap-2">
                                        <EditItemDialog item={item} />
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
}: {
    item?: CatalogItem;
    errors: Record<string, string>;
    idPrefix: string;
}) {
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
            <div className="grid gap-3 sm:grid-cols-2">
                <div className="grid gap-2">
                    <Label htmlFor={`${idPrefix}-ratetype`}>Rate basis</Label>
                    <Select name="rate_type" defaultValue={item?.rate_type ?? 'per_gram'}>
                        <SelectTrigger id={`${idPrefix}-ratetype`} className="w-full">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="per_gram">Per gram</SelectItem>
                            <SelectItem value="per_carat">Per carat</SelectItem>
                            <SelectItem value="per_piece">Per piece</SelectItem>
                            <SelectItem value="fixed">Fixed</SelectItem>
                        </SelectContent>
                    </Select>
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
            <div className="grid gap-2">
                <Label htmlFor={`${idPrefix}-desc`}>Description</Label>
                <Textarea id={`${idPrefix}-desc`} name="description" rows={2} defaultValue={item?.description ?? ''} />
            </div>
        </div>
    );
}

function AddItemDialog() {
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
                            <ItemFields errors={errors} idPrefix="new" />
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

function EditItemDialog({ item }: { item: CatalogItem }) {
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
                            <ItemFields item={item} errors={errors} idPrefix={`edit-${item.id}`} />
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
