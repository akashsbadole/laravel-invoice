import { Head, router, usePage } from '@inertiajs/react';
import { ArrowLeft, Plus, Trash2 } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { excelImport, index as catalogIndex } from '@/routes/catalog';
import { dashboard } from '@/routes';

type PreviewRow = Record<string, string>;

const selectClasses =
    'h-9 w-full rounded-md border border-input bg-transparent px-2 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring';

export default function ExcelPreviewPage({
    token,
    columns,
    rows,
    truncated,
    rateTypes,
    statuses,
}: {
    token: string;
    columns: string[];
    rows: Record<string, string | null>[];
    truncated: boolean;
    rateTypes: string[];
    statuses: { value: string; label: string }[];
}) {
    const { errors } = usePage<{ errors?: Record<string, string> }>().props;
    const [processing, setProcessing] = useState(false);
    const [data, setData] = useState<PreviewRow[]>(() =>
        rows.map((row) => {
            const next: PreviewRow = {};
            for (const column of columns) {
                next[column] = row[column] ?? '';
            }
            return next;
        }),
    );

    const missingNames = data.filter((row) => row.name.trim() === '').length;

    function setCell(index: number, key: string, value: string) {
        setData((current) =>
            current.map((row, i) => (i === index ? { ...row, [key]: value } : row)),
        );
    }

    function deleteRow(index: number) {
        setData((current) => current.filter((_, i) => i !== index));
    }

    function addRow() {
        setData((current) => {
            const next: PreviewRow = {};
            for (const column of columns) {
                next[column] = '';
            }
            next.rate_type = rateTypes[0] ?? '';
            next.status = 'active';

            return [...current, next];
        });
    }

    function submit(e: FormEvent) {
        e.preventDefault();
        setProcessing(true);

        router.post(
            excelImport(),
            { token, rows: data },
            {
                preserveScroll: true,
                onFinish: () => setProcessing(false),
            },
        );
    }

    return (
        <>
            <Head title="Excel import preview" />

            <div className="space-y-6 p-4 md:p-6">
                <Heading
                    variant="small"
                    title="Excel import preview"
                    description="Check the sheet before anything is written: edit cells, add new rows or delete rows you do not want. Rows with a matching item code update that product."
                />

                {truncated && (
                    <p className="rounded-md border border-amber-500/50 bg-amber-500/10 p-3 text-sm">
                        This sheet has more rows than the preview supports —
                        only the first 1000 are shown. Split the file and import
                        the rest separately.
                    </p>
                )}

                <form onSubmit={submit} className="space-y-4">
                    <div className="flex flex-wrap items-center justify-between gap-2">
                        <div className="flex items-center gap-2">
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                asChild
                            >
                                <a href={catalogIndex()}>
                                    <ArrowLeft className="size-4" />
                                    Cancel
                                </a>
                            </Button>
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                onClick={addRow}
                            >
                                <Plus className="size-4" />
                                Add row
                            </Button>
                            <span className="text-sm text-muted-foreground">
                                {data.length} row(s)
                            </span>
                        </div>

                        <Button
                            type="submit"
                            disabled={processing || data.length === 0}
                        >
                            {processing
                                ? 'Importing…'
                                : `Import ${data.length} row(s)`}
                        </Button>
                    </div>

                    {missingNames > 0 && (
                        <p className="text-sm text-amber-600 dark:text-amber-500">
                            {missingNames} row(s) have no name and will be
                            skipped.
                        </p>
                    )}

                    {errors &&
                        Object.values(errors).length > 0 && (
                            <p className="text-sm text-destructive">
                                {Object.values(errors)[0]}
                            </p>
                        )}

                    <Card>
                        <CardContent className="p-0">
                            <div className="overflow-x-auto rounded-md border">
                                <table className="min-w-full text-sm">
                                    <thead className="bg-muted/50 text-left text-xs uppercase text-muted-foreground">
                                        <tr>
                                            <th className="w-12 px-3 py-2">
                                                #
                                            </th>
                                            <th className="px-3 py-2">
                                                Name
                                            </th>
                                            <th className="px-3 py-2">
                                                Item code
                                            </th>
                                            <th className="px-3 py-2">
                                                Rate type
                                            </th>
                                            <th className="px-3 py-2 text-right">
                                                Rate
                                            </th>
                                            <th className="px-3 py-2 text-right">
                                                Cost
                                            </th>
                                            <th className="px-3 py-2">
                                                Status
                                            </th>
                                            <th className="w-12 px-3 py-2" />
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y">
                                        {data.map((row, index) => (
                                            <tr key={index}>
                                                <td className="px-3 py-1.5 text-muted-foreground">
                                                    {index + 1}
                                                </td>
                                                <td className="min-w-40 px-2 py-1.5">
                                                    <Label
                                                        htmlFor={`row-${index}-name`}
                                                        className="sr-only"
                                                    >
                                                        Name
                                                    </Label>
                                                    <Input
                                                        id={`row-${index}-name`}
                                                        value={row.name ?? ''}
                                                        onChange={(e) =>
                                                            setCell(
                                                                index,
                                                                'name',
                                                                e.target.value,
                                                            )
                                                        }
                                                        placeholder="Required"
                                                        className="h-9"
                                                    />
                                                </td>
                                                <td className="min-w-32 px-2 py-1.5">
                                                    <Label
                                                        htmlFor={`row-${index}-code`}
                                                        className="sr-only"
                                                    >
                                                        Item code
                                                    </Label>
                                                    <Input
                                                        id={`row-${index}-code`}
                                                        value={
                                                            row.item_code ?? ''
                                                        }
                                                        onChange={(e) =>
                                                            setCell(
                                                                index,
                                                                'item_code',
                                                                e.target.value,
                                                            )
                                                        }
                                                        className="h-9"
                                                    />
                                                </td>
                                                <td className="min-w-32 px-2 py-1.5">
                                                    <Label
                                                        htmlFor={`row-${index}-rate-type`}
                                                        className="sr-only"
                                                    >
                                                        Rate type
                                                    </Label>
                                                    <select
                                                        id={`row-${index}-rate-type`}
                                                        value={
                                                            row.rate_type ?? ''
                                                        }
                                                        onChange={(e) =>
                                                            setCell(
                                                                index,
                                                                'rate_type',
                                                                e.target.value,
                                                            )
                                                        }
                                                        className={selectClasses}
                                                    >
                                                        {!rateTypes.includes(
                                                            row.rate_type ?? '',
                                                        ) && (
                                                            <option value="">
                                                                —
                                                            </option>
                                                        )}
                                                        {rateTypes.map(
                                                            (type) => (
                                                                <option
                                                                    key={type}
                                                                    value={type}
                                                                >
                                                                    {type}
                                                                </option>
                                                            ),
                                                        )}
                                                    </select>
                                                </td>
                                                <td className="min-w-28 px-2 py-1.5">
                                                    <Label
                                                        htmlFor={`row-${index}-rate`}
                                                        className="sr-only"
                                                    >
                                                        Rate
                                                    </Label>
                                                    <Input
                                                        id={`row-${index}-rate`}
                                                        value={
                                                            row.default_rate ??
                                                            ''
                                                        }
                                                        onChange={(e) =>
                                                            setCell(
                                                                index,
                                                                'default_rate',
                                                                e.target.value,
                                                            )
                                                        }
                                                        inputMode="decimal"
                                                        className="h-9 text-right"
                                                    />
                                                </td>
                                                <td className="min-w-28 px-2 py-1.5">
                                                    <Label
                                                        htmlFor={`row-${index}-cost`}
                                                        className="sr-only"
                                                    >
                                                        Cost
                                                    </Label>
                                                    <Input
                                                        id={`row-${index}-cost`}
                                                        value={
                                                            row.cost_price ?? ''
                                                        }
                                                        onChange={(e) =>
                                                            setCell(
                                                                index,
                                                                'cost_price',
                                                                e.target.value,
                                                            )
                                                        }
                                                        inputMode="decimal"
                                                        className="h-9 text-right"
                                                    />
                                                </td>
                                                <td className="min-w-32 px-2 py-1.5">
                                                    <Label
                                                        htmlFor={`row-${index}-status`}
                                                        className="sr-only"
                                                    >
                                                        Status
                                                    </Label>
                                                    <select
                                                        id={`row-${index}-status`}
                                                        value={
                                                            row.status ?? ''
                                                        }
                                                        onChange={(e) =>
                                                            setCell(
                                                                index,
                                                                'status',
                                                                e.target.value,
                                                            )
                                                        }
                                                        className={selectClasses}
                                                    >
                                                        {!statuses.some(
                                                            (status) =>
                                                                status.value ===
                                                                row.status,
                                                        ) && (
                                                            <option value="">
                                                                active
                                                            </option>
                                                        )}
                                                        {statuses.map(
                                                            (status) => (
                                                                <option
                                                                    key={
                                                                        status.value
                                                                    }
                                                                    value={
                                                                        status.value
                                                                    }
                                                                >
                                                                    {
                                                                        status.label
                                                                    }
                                                                </option>
                                                            ),
                                                        )}
                                                    </select>
                                                </td>
                                                <td className="px-2 py-1.5">
                                                    <Button
                                                        type="button"
                                                        variant="ghost"
                                                        size="sm"
                                                        aria-label={`Delete row ${index + 1}`}
                                                        onClick={() =>
                                                            deleteRow(index)
                                                        }
                                                    >
                                                        <Trash2 className="size-4" />
                                                    </Button>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </CardContent>
                    </Card>

                    <p className="text-xs text-muted-foreground">
                        Columns not shown here import exactly as they appear in
                        your sheet. A row whose item code already exists
                        updates that product instead of adding a duplicate.
                    </p>

                    <div className="flex justify-end">
                        <Button
                            type="submit"
                            disabled={processing || data.length === 0}
                        >
                            {processing
                                ? 'Importing…'
                                : `Import ${data.length} row(s)`}
                        </Button>
                    </div>
                </form>
            </div>
        </>
    );
}

ExcelPreviewPage.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Product catalog', href: catalogIndex() },
    ],
};
