import { Form, Head } from '@inertiajs/react';
import { Download } from 'lucide-react';
import Heading from '@/components/heading';
import ReportController from '@/actions/App/Http/Controllers/ReportController';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { download as downloadReport } from '@/routes/reports';
import { dashboard } from '@/routes';
import type { Staff } from '@/types/customer';
import type { ReportData, ReportFilters, ReportTypeOption } from '@/types/report';

const currency = new Intl.NumberFormat('en-IN', { maximumFractionDigits: 2 });

function fmt(value: string | number | null, type: string): string {
    if (value === null || value === '') return '';
    if (type === 'money') return currency.format(Number(value));
    return String(value);
}

export default function ReportsPage({
    report,
    types,
    filters,
    customers,
    staff,
}: {
    report: ReportData;
    types: ReportTypeOption[];
    filters: ReportFilters;
    customers: { id: number; full_name: string }[];
    staff: Staff[];
}) {
    function exportUrl(format: 'csv' | 'xlsx' | 'pdf') {
        return downloadReport({
            query: { ...filters, format },
        }).url;
    }

    function gstUrl(kind: 'gstr-1' | 'gstr-3b') {
        const params = new URLSearchParams();
        if (filters.from) params.append('from', filters.from);
        if (filters.to) params.append('to', filters.to);
        const qs = params.toString();
        return `/reports/${kind}${qs ? `?${qs}` : ''}`;
    }

    return (
        <>
            <Head title="Reports" />

            <div className="space-y-6 p-4 md:p-6">
                <Heading title="Reports" description={report.title} />

                <Card>
                    <CardContent>
                        <Form
                            {...ReportController.index.form()}
                            options={{ preserveState: true }}
                            className="grid gap-3 sm:grid-cols-3 lg:grid-cols-6"
                        >
                            {() => (
                                <>
                                    <div className="grid gap-1.5 lg:col-span-2">
                                        <label className="text-sm font-medium">Report</label>
                                        <Select name="type" defaultValue={filters.type}>
                                            <SelectTrigger className="w-full">
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {types.map((t) => (
                                                    <SelectItem key={t.value} value={t.value}>
                                                        {t.label}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    </div>
                                    <div className="grid gap-1.5">
                                        <label className="text-sm font-medium">From</label>
                                        <Input type="date" name="from" defaultValue={filters.from} />
                                    </div>
                                    <div className="grid gap-1.5">
                                        <label className="text-sm font-medium">To</label>
                                        <Input type="date" name="to" defaultValue={filters.to} />
                                    </div>
                                    <div className="grid gap-1.5">
                                        <label className="text-sm font-medium">Customer</label>
                                        <Select name="customer_id" defaultValue={filters.customer_id}>
                                            <SelectTrigger className="w-full">
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="all">All customers</SelectItem>
                                                {customers.map((c) => (
                                                    <SelectItem key={c.id} value={String(c.id)}>
                                                        {c.full_name}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    </div>
                                    <div className="grid gap-1.5">
                                        <label className="text-sm font-medium">Staff</label>
                                        <Select name="staff_id" defaultValue={filters.staff_id}>
                                            <SelectTrigger className="w-full">
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="all">Everyone</SelectItem>
                                                {staff.map((s) => (
                                                    <SelectItem key={s.id} value={String(s.id)}>
                                                        {s.name}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    </div>
                                    <div className="flex items-end gap-2 sm:col-span-3 lg:col-span-6">
                                        <Button type="submit">Run report</Button>
                                        <div className="ml-auto flex gap-2">
                                            <Button type="button" variant="outline" asChild>
                                                <a href={exportUrl('csv')}>
                                                    <Download className="size-4" />
                                                    CSV
                                                </a>
                                            </Button>
                                            <Button type="button" variant="outline" asChild>
                                                <a href={exportUrl('xlsx')}>
                                                    <Download className="size-4" />
                                                    Excel
                                                </a>
                                            </Button>
                                            <Button type="button" variant="outline" asChild>
                                                <a href={exportUrl('pdf')}>
                                                    <Download className="size-4" />
                                                    PDF
                                                </a>
                                            </Button>
                                            <Button type="button" variant="outline" asChild title="GSTR-1 JSON for the GST portal">
                                                <a href={gstUrl('gstr-1')}>
                                                    GSTR-1
                                                </a>
                                            </Button>
                                            <Button type="button" variant="outline" asChild title="GSTR-3B summary JSON">
                                                <a href={gstUrl('gstr-3b')}>
                                                    GSTR-3B
                                                </a>
                                            </Button>
                                        </div>
                                    </div>
                                </>
                            )}
                        </Form>
                    </CardContent>
                </Card>

                <Card className="overflow-hidden">
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead className="border-b bg-muted/40 text-left text-muted-foreground">
                                <tr>
                                    {report.columns.map((col) => (
                                        <th
                                            key={col.key}
                                            className={`px-3 py-2 font-medium ${['money', 'number'].includes(col.type) ? 'text-right' : ''}`}
                                        >
                                            {col.label}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {report.rows.length === 0 ? (
                                    <tr>
                                        <td colSpan={report.columns.length} className="px-3 py-8 text-center text-muted-foreground">
                                            No data for the selected filters.
                                        </td>
                                    </tr>
                                ) : (
                                    report.rows.map((row, i) => (
                                        <tr key={i} className="hover:bg-muted/30">
                                            {report.columns.map((col) => (
                                                <td
                                                    key={col.key}
                                                    className={`px-3 py-2 ${['money', 'number'].includes(col.type) ? 'text-right' : ''}`}
                                                >
                                                    {fmt(row[col.key], col.type)}
                                                </td>
                                            ))}
                                        </tr>
                                    ))
                                )}
                            </tbody>
                            {report.totals && (
                                <tfoot className="border-t bg-muted/40 font-semibold">
                                    <tr>
                                        {report.columns.map((col) => (
                                            <td
                                                key={col.key}
                                                className={`px-3 py-2 ${['money', 'number'].includes(col.type) ? 'text-right' : ''}`}
                                            >
                                                {fmt(report.totals?.[col.key] ?? null, col.type)}
                                            </td>
                                        ))}
                                    </tr>
                                </tfoot>
                            )}
                        </table>
                    </div>
                </Card>
            </div>
        </>
    );
}

ReportsPage.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Reports', href: '/reports' },
    ],
};
