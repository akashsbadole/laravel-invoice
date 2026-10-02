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
import { AttributesEditor } from '@/components/attributes-editor';
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
import {
    CATALOG_FIELD_GROUPS,
    rateTypeLabel,
} from '@/lib/industries';
import type { CatalogFieldSpec, IndustryConfig } from '@/lib/industries';
import { download as catalogDownload, template as catalogTemplate } from '@/routes/catalog';
import { dashboard } from '@/routes';
import type { Paginated } from '@/types/customer';
import type { CatalogItem, CatalogStatus } from '@/types/invoice';

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
                    <AddItemDialog industry={industry} fields={fields} />
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
                                        </div>
                                    </div>
                                    <div className="flex items-center gap-2">
                                        {item.status === 'draft' && (
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
                                        {item.stock_tracked && (
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

/**
 * One registry-driven input. Rendering from the spec is what keeps the form,
 * the CSV and the validator describing the same set of fields.
 */
function FieldInput({
    field,
    idPrefix,
    industry,
    item,
    errors,
}: {
    field: CatalogFieldSpec;
    idPrefix: string;
    industry: IndustryConfig;
    item?: CatalogItem;
    errors: Record<string, string>;
}) {
    const id = `${idPrefix}-${field.name}`;
    const value = (item as Record<string, unknown> | undefined)?.[
        field.name
    ];
    const labelId = `${id}-hint`;

    let control: React.ReactNode;

    switch (field.type) {
        case 'textarea':
            control = (
                <Textarea
                    id={id}
                    name={field.name}
                    rows={2}
                    defaultValue={(value as string) ?? ''}
                />
            );
            break;

        case 'number':
            control = (
                <Input
                    id={id}
                    name={field.name}
                    type="number"
                    step="0.001"
                    min={0}
                    defaultValue={
                        value === null || value === undefined
                            ? ''
                            : String(value)
                    }
                />
            );
            break;

        case 'select':
            control = (
                <Select
                    name={field.name}
                    defaultValue={
                        (value as string) ??
                        industry.rate_types[0] ??
                        'per_piece'
                    }
                >
                    <SelectTrigger id={id} className="w-full">
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
            );
            break;

        case 'boolean':
            return (
                <div className="flex items-center gap-2 pt-6">
                    <input
                        type="checkbox"
                        id={id}
                        name={field.name}
                        defaultChecked={Boolean(value)}
                        className="size-4"
                    />
                    <Label htmlFor={id} className="font-normal">
                        {field.label}
                    </Label>
                </div>
            );

        case 'image':
            // Image upload is bespoke rather than a plain input.
            return null;

        case 'attributes':
            control = <AttributesEditor initial={item?.attributes ?? {}} />;
            break;

        default:
            control = (
                <Input
                    id={id}
                    name={field.name}
                    defaultValue={(value as string) ?? ''}
                />
            );
    }

    return (
        <div className="grid gap-2">
            <Label htmlFor={id}>{field.label}</Label>
            {control}
            {field.hint && (
                <p
                    id={labelId}
                    className="text-xs text-muted-foreground"
                >
                    {field.hint}
                </p>
            )}
            <InputError message={errors[field.name]} />
        </div>
    );
}

/**
 * Repeatable key/value rows for the free-form `attributes` column.
 */
function ItemFields({
    item,
    errors,
    idPrefix,
    industry,
    fields,
}: {
    item?: CatalogItem;
    errors: Record<string, string>;
    idPrefix: string;
    industry: IndustryConfig;
    fields: CatalogFieldSpec[];
}) {
    const groups = Object.entries(CATALOG_FIELD_GROUPS)
        .map(([key, label]) => ({
            key: key as CatalogFieldSpec['group'],
            label,
            fields: fields.filter((f) => f.group === key),
        }))
        .filter((group) => group.fields.length > 0);

    return (
        <div className="space-y-5">
            <ImageField idPrefix={idPrefix} item={item} errors={errors} />

            {groups.map((group) => (
                <div key={group.key} className="space-y-3">
                    <p className="text-sm font-medium text-muted-foreground">
                        {group.label}
                    </p>
                    <div className="grid gap-3 sm:grid-cols-2">
                        {group.fields
                            .filter((f) => f.type !== 'image')
                            .map((field) => (
                                <FieldInput
                                    key={field.name}
                                    field={field}
                                    idPrefix={idPrefix}
                                    industry={industry}
                                    item={item}
                                    errors={errors}
                                />
                            ))}
                    </div>
                </div>
            ))}
        </div>
    );
}

function ImageField({
    idPrefix,
    item,
    errors,
}: {
    idPrefix: string;
    item?: CatalogItem;
    errors: Record<string, string>;
}) {
    const [preview, setPreview] = useState<string | null>(
        item?.image_url ?? null,
    );
    const [remove, setRemove] = useState(false);

    return (
        <div className="grid gap-2">
            <Label htmlFor={`${idPrefix}-image`}>Product image</Label>
            <div className="flex items-center gap-3">
                {preview && !remove ? (
                    <img
                        src={preview}
                        alt=""
                        className="size-16 rounded-md border object-cover"
                    />
                ) : (
                    <div className="flex size-16 items-center justify-center rounded-md border text-muted-foreground">
                        <ImageIcon className="size-5" />
                    </div>
                )}
                <input
                    id={`${idPrefix}-image`}
                    name="image"
                    type="file"
                    accept="image/png,image/jpeg,image/webp"
                    className="block w-full text-sm text-muted-foreground file:mr-3 file:rounded-md file:border-0 file:bg-secondary file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-secondary-foreground"
                    onChange={(e) => {
                        const file = e.target.files?.[0];
                        if (file) {
                            setPreview(URL.createObjectURL(file));
                            setRemove(false);
                        }
                    }}
                />
            </div>
            {item?.image_url && (
                <label className="flex items-center gap-2 text-xs text-muted-foreground">
                    <input
                        type="checkbox"
                        name="remove_image"
                        checked={remove}
                        onChange={(e) => setRemove(e.target.checked)}
                        className="size-4"
                    />
                    Remove the current image
                </label>
            )}
            <InputError message={errors.image} />
        </div>
    );
}

/**
 * Record a stock movement. Stock is never edited directly — the dialog only
 * produces ledger entries, so the balance always has an explanation.
 */
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

function AddItemDialog({
    industry,
    fields,
}: {
    industry: IndustryConfig;
    fields: CatalogFieldSpec[];
}) {
    const [open, setOpen] = useState(false);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button size="sm">
                    <Plus className="size-4" />
                    Add item
                </Button>
            </DialogTrigger>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
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
                            <ItemFields
                                errors={errors}
                                idPrefix="new"
                                industry={industry}
                                fields={fields}
                            />
                            <div className="flex items-start gap-2">
                                <input
                                    type="checkbox"
                                    id="new-status"
                                    name="status"
                                    value="draft"
                                    className="mt-0.5 size-4"
                                />
                                <Label
                                    htmlFor="new-status"
                                    className="font-normal"
                                >
                                    Save as draft (not visible in quotations
                                    until activated)
                                </Label>
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
                                    Add
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
                            <ItemFields
                                item={item}
                                errors={errors}
                                idPrefix={`edit-${item.id}`}
                                industry={industry}
                                fields={fields}
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
                                            Inactive
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
                    Rows are matched by <code>item_code</code>: existing codes
                    are updated, new ones are added. Only <code>name</code> is
                    required, and you may include just the columns you have.
                    Download the CSV template for the exact columns.
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
                                    <Button
                                        variant="secondary"
                                        type="button"
                                    >
                                        Cancel
                                    </Button>
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
