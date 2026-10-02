import { useState } from 'react';
import { ImageIcon } from 'lucide-react';
import { AttributesEditor } from '@/components/attributes-editor';
import InputError from '@/components/input-error';
import { VariantsEditor } from '@/components/variants-editor';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import {
    CATALOG_FIELD_GROUPS,
    rateTypeLabel,
} from '@/lib/industries';
import type { CatalogFieldSpec, IndustryConfig } from '@/lib/industries';
import type { CatalogItem } from '@/types/invoice';

/**
 * Shared form fields for creating and editing catalog items.
 *
 * Renders one registry-driven input per industry field so the form, the CSV
 * import and the server validator all describe the same set of fields.
 *
 * The status field is intentionally not included — create uses a draft
 * checkbox while edit uses a full status Select.
 */
export function CatalogItemForm({
    item,
    errors,
    industry,
    fields,
    idPrefix = 'new',
}: {
    item?: CatalogItem;
    errors: Record<string, string>;
    industry: IndustryConfig;
    fields: CatalogFieldSpec[];
    idPrefix?: string;
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

            <Card>
                <CardHeader>
                    <CardTitle>Variants</CardTitle>
                </CardHeader>
                <CardContent>
                    <VariantsEditor
                        initial={item?.variants ?? []}
                        errors={errors}
                        defaultRate={item?.default_rate ?? null}
                    />
                </CardContent>
            </Card>
        </div>
    );
}

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
