import { Plus, Trash2 } from 'lucide-react';
import { useMemo, useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

/**
 * Repeatable key/value rows for a free-form `attributes` column.
 *
 * Rows post as attributes[<index>][key|value]; App\Support\Attributes
 * flattens them to a string map server-side. Indexed by row rather than by key
 * on purpose: a key that changes as you type would otherwise reshape the
 * posted array mid-edit and collide with a sibling row.
 */
export function AttributesEditor({
    initial = {},
    label = 'Extra attributes',
    hint = 'Anything else you want to keep — these do not affect pricing.',
}: {
    initial?: Record<string, string> | null;
    label?: string;
    hint?: string;
}) {
    const [rows, setRows] = useState<string[][]>(() => {
        const entries = Object.entries(initial ?? {});

        return entries.length > 0 ? entries : [['', '']];
    });

    const update = (index: number, key: string, value: string) => {
        setRows((current) =>
            current.map((row, i) => (i === index ? [key, value] : row)),
        );
    };

    return (
        <div className="grid gap-2">
            <Label>{label}</Label>
            <p className="-mt-1 text-xs text-muted-foreground">{hint}</p>

            {rows.map(([key, value], index) => (
                <div
                    key={index}
                    className="flex items-center gap-2"
                >
                    <Input
                        name={`attributes[${index}][key]`}
                        defaultValue={key}
                        placeholder="Name"
                        aria-label={`Attribute ${index + 1} name`}
                        onChange={(e) => update(index, e.target.value, value)}
                    />
                    <Input
                        name={`attributes[${index}][value]`}
                        defaultValue={value}
                        placeholder="Value"
                        aria-label={`Attribute ${index + 1} value`}
                        onChange={(e) => update(index, key, e.target.value)}
                    />
                    <Button
                        type="button"
                        size="icon"
                        variant="ghost"
                        aria-label={`Remove attribute ${index + 1}`}
                        onClick={() =>
                            setRows((current) =>
                                current.length === 1
                                    ? [['', '']]
                                    : current.filter((_, i) => i !== index),
                            )
                        }
                    >
                        <Trash2 className="size-4" />
                    </Button>
                </div>
            ))}

            <div>
                <Button
                    type="button"
                    size="sm"
                    variant="outline"
                    onClick={() => setRows((c) => [...c, ['', '']])}
                >
                    <Plus className="size-4" />
                    Add attribute
                </Button>
            </div>
        </div>
    );
}

/**
 * Controlled variant for pages that keep the whole form in Inertia `useForm`
 * state instead of posting native inputs. Posts nothing on its own; the parent
 * serialises the map into the `attributes` field.
 */
export function AttributesField({
    value,
    onChange,
    label = 'Extra attributes',
    hint = 'Site reference, job number, or anything else this document needs.',
}: {
    value: Record<string, string>;
    onChange: (next: Record<string, string>) => void;
    label?: string;
    hint?: string;
}) {
    const rows = useMemo(() => {
        const entries = Object.entries(value);

        return entries.length > 0 ? entries : [['', '']];
    }, [value]);

    const commit = (next: string[][]) => {
        // Drop half-filled rows so blank trailing keys never persist.
        onChange(
            Object.fromEntries(
                next
                    .map(([key, rowValue]) => [
                        key.trim(),
                        (rowValue ?? '').trim(),
                    ])
                    .filter(([key, rowValue]) => key !== '' && rowValue !== ''),
            ),
        );
    };

    const update = (index: number, key: string, rowValue: string) => {
        commit(
            rows.map((row, i) => (i === index ? [key, rowValue] : row)),
        );
    };

    return (
        <div className="grid gap-2">
            <Label>{label}</Label>
            <p className="-mt-1 text-xs text-muted-foreground">{hint}</p>

            {rows.map(([key, rowValue], index) => (
                <div key={index} className="flex items-center gap-2">
                    <Input
                        value={key}
                        placeholder="Name"
                        aria-label={`Attribute ${index + 1} name`}
                        onChange={(e) => update(index, e.target.value, rowValue)}
                    />
                    <Input
                        value={rowValue}
                        placeholder="Value"
                        aria-label={`Attribute ${index + 1} value`}
                        onChange={(e) => update(index, key, e.target.value)}
                    />
                    <Button
                        type="button"
                        size="icon"
                        variant="ghost"
                        aria-label={`Remove attribute ${index + 1}`}
                        onClick={() =>
                            commit(
                                rows.filter((_, i) => i !== index),
                            )
                        }
                    >
                        <Trash2 className="size-4" />
                    </Button>
                </div>
            ))}

            <div>
                <Button
                    type="button"
                    size="sm"
                    variant="outline"
                    onClick={() => commit([...rows, ['', '']])}
                >
                    <Plus className="size-4" />
                    Add attribute
                </Button>
            </div>
        </div>
    );
}

/**
 * Read-only rendering of a stored attribute map, so a customer or invoice
 * record shows its custom fields without an edit form.
 */
export function AttributesList({
    attributes,
    empty = '—',
}: {
    attributes?: Record<string, string> | null;
    empty?: string;
}) {
    const entries = Object.entries(attributes ?? {});

    if (entries.length === 0) {
        return (
            <p className="text-sm text-muted-foreground">{empty}</p>
        );
    }

    return (
        <dl className="grid gap-1 text-sm sm:grid-cols-2">
            {entries.map(([key, value]) => (
                <div key={key} className="flex gap-2">
                    <dt className="text-muted-foreground">{key}:</dt>
                    <dd className="min-w-0 break-words font-medium">
                        {value}
                    </dd>
                </div>
            ))}
        </dl>
    );
}