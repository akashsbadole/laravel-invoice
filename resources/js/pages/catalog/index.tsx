import { Form, Head, Link } from '@inertiajs/react';
import {
    AlertTriangle,
    Check,
    Clock,
    Download,
    FileDown,
    ImageIcon,
    Plus,
    Upload,
} from 'lucide-react';
import { useState } from 'react';
import CatalogItemController from '@/actions/App/Http/Controllers/CatalogItemController';
import CatalogItemImportController from '@/actions/App/Http/Controllers/CatalogItemImportController';
import { CatalogItemForm } from '@/components/catalog/catalog-item-form';
import Heading from '@/components/heading';
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
import InputError from '@/components/input-error';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    create as catalogCreate,
    excelExport as excelDownload,
    excelTemplate,
    template as catalogTemplate,
    exportMethod as catalogDownload,
} from '@/routes/catalog';
import { dashboard } from '@/routes';
import type { CatalogFieldSpec, IndustryConfig } from '@/lib/industries';
import type { Paginated } from '@/types/customer';
import type { CatalogItem } from '@/types/invoice';
export default function CatalogPage({
    items,
    filters,
    industry,
    fields,
    lowStockCount,
    draftCount,
}: {
    items: Paginated<CatalogItem>;
    filters: { search?: string; status?: string };
    industry: IndustryConfig;
    fields: CatalogFieldSpec[];
    lowStockCount: number;
    draftCount: number;
}) {
    const statuses = [
        { key: '', label: 'All' },
        { key: 'active', label: 'Active' },
        { key: 'draft', label: `Draft (${draftCount})` },
        { key: 'inactive', label: 'Inactive' },
        { key: 'discontinued', label: 'Discontinued' },
        { key: 'low_stock', label: `Low stock (${lowStockCount})` },
    ];

    return (
        <>
            <Head title="Product catalog" />

            <div className="space-y-6 p-4 md:p-6">
                <Heading
                    variant="small"
                    title="Product catalog"
                    description="What you sell, at what price. Build quotations straight from these products."
                />

                <div className="flex flex-wrap gap-2">
                    <Button size="sm" asChild>
                        <a href={catalogCreate()}>
                            <Plus className="size-4" />
                            Add item
                        </a>
                    </Button>
                    <ImportDialog />
                    <Button size="sm" variant="outline" asChild>
                        <a href={catalogDownload()}>
                            <Download className="size-4" />
                            Export CSV
                        </a>
                    </Button>
                    <Button size="sm" variant="outline" asChild>
                        <a href={excelDownload()}>
                            <Download className="size-4" />
                            Export Excel
                        </a>
                    </Button>
                    <Button size="sm" variant="outline" asChild>
                        <a href={excelTemplate()}>
                            <FileDown className="size-4" />
                            Excel template
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
                    className="flex flex-wrap items-center gap-2"
                >
                    {() => (
                        <>
                            <Input
                                name="search"
                                defaultValue={filters.search}
                                placeholder="Search name, code, brand or barcode"
                                className="max-w-xs"
                            />
                            <Select name="status" defaultValue={filters.status ?? ''}>
                                <SelectTrigger className="w-44">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {statuses.map((option) => (
                                        <SelectItem
                                            key={option.key}
                                            value={option.key}
                                        >
                                            {option.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
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
                                    className="flex flex-wrap items-center justify-between gap-3 rounded-md border p-3"
                                >
                                    <div className="flex min-w-0 items-center gap-3">
                                        {item.image_url ? (
                                            <img
                                                src={item.image_url}
                                                alt=""
                                                className="size-12 shrink-0 rounded-md border object-cover"
                                            />
                                        ) : (
                                            <div className="flex size-12 shrink-0 items-center justify-center rounded-md border text-muted-foreground">
                                                <ImageIcon className="size-5" />
                                            </div>
                                        )}
                                        <div className="min-w-0">
                                            <div className="flex flex-wrap items-center gap-2">
                                                <span className="font-medium">
                                                    {item.name}
                                                </span>
                                                {item.item_code && (
                                                    <Badge
                                                        variant="outline"
                                                        className="text-xs"
                                                    >
                                                        {item.item_code}
                                                    </Badge>
                                                )}
                                                {item.status === 'draft' && (
                                                    <Badge
                                                        variant="outline"
                                                        className="text-xs"
                                                    >
                                                        <Clock className="size-3" />
                                                        draft
                                                    </Badge>
                                                )}
                                                {item.status === 'inactive' && (
                                                    <Badge
                                                        variant="secondary"
                                                        className="text-xs"
                                                    >
                                                        inactive
                                                    </Badge>
                                                )}
                                                {item.status ===
                                                    'discontinued' && (
                                                    <Badge
                                                        variant="secondary"
                                                        className="text-xs"
                                                    >
                                                        discontinued
                                                    </Badge>
                                                )}
                                                {item.is_low_stock && (
                                                    <Badge
                                                        variant="destructive"
                                                        className="text-xs"
                                                    >
                                                        <AlertTriangle className="size-3" />
                                                        low stock
                                                    </Badge>
                                                )}
                                            </div>
                                            <p className="text-xs text-muted-foreground">
                                                {(
                                                    industry.uses_weight_fields
                                                        ? [
                                                              item.metal_type,
                                                              item.purity,
                                                          ]
                                                        : [
                                                              item.brand,
                                                              item.model_number,
                                                              item.size_label,
                                                          ]
                                                )
                                                    .filter(Boolean)
                                                    .join(' · ')}
                                                {item.default_rate &&
                                                    ` · ₹${item.default_rate}/${item.rate_type.replace(
                                                        'per_',
                                                        '',
                                                    )}`}
                                                {item.stock_tracked &&
                                                    ` · stock ${item.stock_quantity}${
                                                        item.stock_unit
                                                            ? ` ${item.stock_unit}`
                                                            : ''
                                                    }`}
                                                </p>
                                                {(item.variants?.length ?? 0) > 0 && (
                                                    <Badge
                                                        variant="outline"
                                                        className="w-fit"
                                                    >
                                                        {item.variants!.length}{' '}
                                                        {item.variants!.length === 1
                                                            ? 'variant'
                                                            : 'variants'}
                                                    </Badge>
                                                )}
                                            </div>
                                    </div>
                                    <div className="flex items-center gap-2">
                                        {item.status !== 'active' && (
                                            <Form
                                                {...CatalogItemController.activate.form(
                                                    item.id,
                                                )}
                                            >
                                                {({ processing }) => (
                                                    <Button
                                                        size="sm"
                                                        variant="secondary"
                                                        disabled={processing}
                                                    >
                                                        <Check className="size-3" />
                                                        Activate
                                                    </Button>
                                                )}
                                            </Form>
                                        )}
                                        {item.stock_tracked &&
                                            !item.variants?.length && (
                                                <StockDialog item={item} />
                                            )}
                                        <EditItemDialog
                                            item={item}
                                            industry={industry}
                                            fields={fields}
                                        />
                                        <Form
                                            {...CatalogItemController.destroy.form(
                                                item.id,
                                            )}
                                        >
                                            {({ processing }) => (
                                                <Button
                                                    size="sm"
                                                    variant="ghost"
                                                    disabled={processing}
                                                >
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

function StockDialog({ item }: { item: CatalogItem }) {
    const [open, setOpen] = useState(false);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button size="sm" variant="outline">
                    Stock
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Adjust stock — {item.name}</DialogTitle>
                </DialogHeader>
                <p className="text-sm text-muted-foreground">
                    Currently{' '}
                    <strong>
                        {item.stock_quantity}
                        {item.stock_unit ? ` ${item.stock_unit}` : ''}
                    </strong>
                    {Number(item.reorder_level) > 0 && (
                        <> · reorder at {item.reorder_level}</>
                    )}
                </p>
                <Form
                    {...CatalogItemController.adjustStock.form(item.id)}
                    onSuccess={() => setOpen(false)}
                    className="space-y-4"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor={`stock-type-${item.id}`}>
                                    Movement
                                </Label>
                                <Select name="type" defaultValue="in">
                                    <SelectTrigger
                                        id={`stock-type-${item.id}`}
                                        className="w-full"
                                    >
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="in">
                                            Stock in (received)
                                        </SelectItem>
                                        <SelectItem value="out">
                                            Stock out (issued)
                                        </SelectItem>
                                        <SelectItem value="adjustment">
                                            Correction (set exact count)
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                <InputError message={errors.type} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor={`stock-qty-${item.id}`}>
                                    Quantity
                                </Label>
                                <Input
                                    id={`stock-qty-${item.id}`}
                                    name="quantity"
                                    type="number"
                                    step="0.001"
                                    required
                                />
                                <InputError message={errors.quantity} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor={`stock-note-${item.id}`}>
                                    Note (optional)
                                </Label>
                                <Input
                                    id={`stock-note-${item.id}`}
                                    name="note"
                                    placeholder="e.g. Purchase invoice 42"
                                />
                                <InputError message={errors.note} />
                            </div>
                            <DialogFooter>
                                <DialogClose asChild>
                                    <Button
                                        variant="secondary"
                                        type="button"
                                    >
                                        Cancel
                                    </Button>
                                </DialogClose>
                                <Button disabled={processing}>
                                    {processing ? 'Saving…' : 'Record'}
                                </Button>
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
    fields,
}: {
    item: CatalogItem;
    industry: IndustryConfig;
    fields: CatalogFieldSpec[];
}) {
    const [open, setOpen] = useState(false);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button size="sm" variant="outline">
                    Edit
                </Button>
            </DialogTrigger>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
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
                            <CatalogItemForm
                                item={item}
                                errors={errors}
                                industry={industry}
                                fields={fields}
                                idPrefix={`edit-${item.id}`}
                            />
                            <div className="grid gap-2">
                                <Label htmlFor={`status-${item.id}`}>
                                    Status
                                </Label>
                                <Select
                                    name="status"
                                    defaultValue={item.status ?? 'active'}
                                >
                                    <SelectTrigger
                                        id={`status-${item.id}`}
                                        className="w-full"
                                    >
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="draft">
                                            Draft (not visible in quotations)
                                        </SelectItem>
                                        <SelectItem value="active">
                                            Active
                                        </SelectItem>
                                        <SelectItem value="inactive">
                                            Inactive (temporarily off sale)
                                        </SelectItem>
                                        <SelectItem value="discontinued">
                                            Discontinued (retired)
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                            <DialogFooter>
                                <DialogClose asChild>
                                    <Button
                                        variant="secondary"
                                        type="button"
                                    >
                                        Close
                                    </Button>
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
    const fileInput =
        'block w-full text-sm text-muted-foreground file:mr-3 file:rounded-md file:border-0 file:bg-secondary file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-secondary-foreground';

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button size="sm" variant="outline">
                    <Upload className="size-4" />
                    Import
                </Button>
            </DialogTrigger>
            <DialogContent className="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>Import catalog</DialogTitle>
                </DialogHeader>
                <p className="text-sm text-muted-foreground">
                    Rows are matched by <code>item_code</code>: existing codes
                    are updated, new ones are added. Only <code>name</code> is
                    required, and you may include just the columns you have.
                </p>

                <section className="space-y-2 rounded-md border p-3">
                    <div className="flex items-center justify-between gap-2">
                        <h3 className="text-sm font-semibold">
                            Excel (with preview)
                        </h3>
                        <a
                            href={excelTemplate()}
                            className="text-xs underline underline-offset-2"
                        >
                            Template
                        </a>
                    </div>
                    <p className="text-xs text-muted-foreground">
                        Upload an .xlsx/.xls sheet, review it, add or delete
                        rows, then import.
                    </p>
                    <Form
                        {...CatalogItemImportController.excelPreviewUpload.form()}
                        onSuccess={() => setOpen(false)}
                    >
                        {({ processing, errors }) => (
                            <div className="grid gap-2">
                                <Label htmlFor="excel-file">Excel file</Label>
                                <input
                                    id="excel-file"
                                    name="file"
                                    type="file"
                                    accept=".xlsx,.xls,.csv"
                                    required
                                    className={fileInput}
                                />
                                <InputError message={errors.file} />
                                <Button
                                    disabled={processing}
                                    className="justify-self-end"
                                >
                                    {processing ? 'Reading…' : 'Preview'}
                                </Button>
                            </div>
                        )}
                    </Form>
                </section>

                <section className="space-y-2 rounded-md border p-3">
                    <div className="flex items-center justify-between gap-2">
                        <h3 className="text-sm font-semibold">
                            CSV (quick import)
                        </h3>
                        <a
                            href={catalogTemplate()}
                            className="text-xs underline underline-offset-2"
                        >
                            Template
                        </a>
                    </div>
                    <Form
                        {...CatalogItemImportController.importCsv.form()}
                        onSuccess={() => setOpen(false)}
                    >
                        {({ processing, errors }) => (
                            <div className="grid gap-2">
                                <Label htmlFor="csv-file">CSV file</Label>
                                <input
                                    id="csv-file"
                                    name="file"
                                    type="file"
                                    accept=".csv,text/csv"
                                    required
                                    className={fileInput}
                                />
                                <InputError message={errors.file} />
                                <Button
                                    disabled={processing}
                                    className="justify-self-end"
                                >
                                    {processing ? 'Importing…' : 'Import'}
                                </Button>
                            </div>
                        )}
                    </Form>
                </section>

                <DialogFooter>
                    <DialogClose asChild>
                        <Button variant="secondary" type="button">
                            Close
                        </Button>
                    </DialogClose>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

CatalogPage.layout = {
    breadcrumbs: [{ title: 'Dashboard', href: dashboard() }],
};
