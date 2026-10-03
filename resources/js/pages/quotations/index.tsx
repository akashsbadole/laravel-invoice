import { Head, Link, router } from '@inertiajs/react';
import { BellRing, Eye, Package } from 'lucide-react';
import type { ReactNode } from 'react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { index as invoicesIndex, show as invoiceShow } from '@/routes/invoices';
import { create as createQuotation, nudge } from '@/routes/quotations';

const currency = new Intl.NumberFormat('en-IN', {
    style: 'currency',
    currency: 'INR',
    maximumFractionDigits: 0,
});

type Stage = {
    value: string;
    label: string;
    count: number;
    total: number;
};

type QuotationRow = {
    id: number;
    invoice_number: string;
    customer: string;
    grand_total: number;
    status: string;
    status_label: string;
    is_open: boolean;
    valid_until: string | null;
    viewed_at: string | null;
    has_link: boolean;
    age_days: number;
};

const statusColors: Record<string, string> = {
    draft: 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300',
    sent: 'bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-300',
    accepted: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300',
    rejected: 'bg-red-100 text-red-700 dark:bg-red-950 dark:text-red-300',
    expired: 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400',
    converted: 'bg-blue-100 text-blue-700 dark:bg-blue-950 dark:text-blue-300',
};

import { useState } from 'react';
import { CheckCircle2, ArrowRightLeft } from 'lucide-react';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Form } from '@inertiajs/react';
import InvoiceController from '@/actions/App/Http/Controllers/InvoiceController';

type ExtendedQuotationRow = QuotationRow & {
    customer_id?: number;
    customer_phone?: string;
    share_token?: string;
    converted_to_id?: number | null;
    can_convert?: boolean;
};

type Analytics = {
    total_quotations: number;
    accepted_count: number;
    converted_count: number;
    conversion_rate: number;
};

export default function QuotationPipeline({
    stages,
    openValue,
    needsChase,
    expiringSoon,
    followUpEnabled,
    followUpDays,
    dueCount,
    analytics,
    acceptedQuotations = [],
    quotations = [],
}: {
    stages: Stage[];
    openValue: number;
    needsChase: ExtendedQuotationRow[];
    expiringSoon: ExtendedQuotationRow[];
    followUpEnabled: boolean;
    followUpDays: number;
    dueCount: number;
    analytics?: Analytics;
    acceptedQuotations?: ExtendedQuotationRow[];
    quotations?: ExtendedQuotationRow[];
}) {
    function sendFollowUp(row: ExtendedQuotationRow) {
        router.post(nudge(row.id), {}, { preserveScroll: true });
    }

    return (
        <>
            <Head title="Quotations Pipeline" />

            <div className="space-y-6 p-4 md:p-6">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <Heading
                        title="Quotations Pipeline"
                        description="Track open quotations, client decisions, and convert quotes to paying sales invoices."
                    />
                    <div className="flex flex-wrap gap-2">
                        <Button asChild variant="outline" className="w-full sm:w-auto">
                            <Link href={invoicesIndex() + '?document_type=quotation'}>
                                All quotations
                            </Link>
                        </Button>
                        <Button asChild className="w-full sm:w-auto">
                            <Link href={createQuotation()}>
                                <Package className="size-4" />
                                New quotation
                            </Link>
                        </Button>
                    </div>
                </div>

                {/* KPI Analytics Cards */}
                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <Card className="border-slate-200/80 dark:border-slate-800">
                        <CardContent className="pt-6">
                            <p className="text-xs uppercase font-semibold text-slate-400 tracking-wider">
                                Open Pipeline Value
                            </p>
                            <p className="mt-1 text-2xl font-bold font-mono text-indigo-600 dark:text-indigo-400">
                                {currency.format(openValue)}
                            </p>
                            <p className="mt-1 text-xs text-muted-foreground">
                                Active draft &amp; sent quotes
                            </p>
                        </CardContent>
                    </Card>

                    <Card className="border-slate-200/80 dark:border-slate-800">
                        <CardContent className="pt-6">
                            <p className="text-xs uppercase font-semibold text-slate-400 tracking-wider">
                                Conversion Rate
                            </p>
                            <p className="mt-1 text-2xl font-bold font-mono text-emerald-600 dark:text-emerald-400">
                                {analytics?.conversion_rate ?? 0}%
                            </p>
                            <p className="mt-1 text-xs text-muted-foreground">
                                {analytics?.converted_count ?? 0} converted / {analytics?.total_quotations ?? 0} total
                            </p>
                        </CardContent>
                    </Card>

                    <Card className="border-slate-200/80 dark:border-slate-800">
                        <CardContent className="pt-6">
                            <p className="text-xs uppercase font-semibold text-slate-400 tracking-wider">
                                Accepted &amp; Ready
                            </p>
                            <p className="mt-1 text-2xl font-bold font-mono text-emerald-700 dark:text-emerald-300">
                                {acceptedQuotations.length}
                            </p>
                            <p className="mt-1 text-xs text-muted-foreground">
                                Awaiting 1-click invoice conversion
                            </p>
                        </CardContent>
                    </Card>

                    <Card className="border-slate-200/80 dark:border-slate-800">
                        <CardContent className="pt-6">
                            <p className="text-xs uppercase font-semibold text-slate-400 tracking-wider">
                                Follow-ups Due
                            </p>
                            <p className="mt-1 text-2xl font-bold font-mono text-amber-600 dark:text-amber-400">
                                {dueCount}
                            </p>
                            <p className="mt-1 text-xs text-muted-foreground">
                                {followUpEnabled
                                    ? `Auto-chasing ${followUpDays}d after sending`
                                    : 'Auto-chasing is off in settings'}
                            </p>
                        </CardContent>
                    </Card>
                </div>

                {/* Stage Breakdown Bar */}
                <div className="grid gap-3 sm:grid-cols-3 lg:grid-cols-6">
                    {stages.map((stage) => (
                        <div
                            key={stage.value}
                            className="rounded-xl border border-slate-200/80 dark:border-slate-800 p-3.5 text-center bg-white dark:bg-slate-900 shadow-sm"
                        >
                            <Badge
                                variant="secondary"
                                className={`capitalize text-xs font-semibold px-2.5 py-0.5 ${statusColors[stage.value] ?? ''}`}
                            >
                                {stage.label}
                            </Badge>
                            <p className="mt-2 text-xl font-bold font-mono text-slate-900 dark:text-slate-100">
                                {stage.count}
                            </p>
                            <p className="text-xs text-slate-500 font-mono">
                                {currency.format(stage.total)}
                            </p>
                        </div>
                    ))}
                </div>

                {/* High Priority Section: Accepted Quotations Ready to Convert */}
                {acceptedQuotations.length > 0 && (
                    <Card className="border-emerald-300 dark:border-emerald-900/60 bg-emerald-50/30 dark:bg-emerald-950/20 shadow-sm">
                        <CardContent className="p-6">
                            <div className="flex items-center gap-2 mb-1 text-emerald-800 dark:text-emerald-300 font-bold">
                                <CheckCircle2 className="size-5 text-emerald-600" />
                                <h3>Accepted Quotations — Ready for Invoice Conversion</h3>
                                <Badge className="bg-emerald-600 text-white ml-2">{acceptedQuotations.length}</Badge>
                            </div>
                            <p className="text-xs text-slate-600 dark:text-slate-400 mb-4">
                                Customers have accepted these quotations. Convert them to sales invoices with 1 click to complete the deal.
                            </p>

                            <div className="divide-y divide-emerald-200/60 dark:divide-emerald-900/50">
                                {acceptedQuotations.map((row) => (
                                    <div key={row.id} className="flex flex-wrap items-center justify-between gap-3 py-3">
                                        <div>
                                            <div className="flex items-center gap-2">
                                                <Link href={invoiceShow(row.id)} className="font-semibold text-slate-900 dark:text-slate-100 hover:underline">
                                                    {row.invoice_number}
                                                </Link>
                                                <Badge className="bg-emerald-100 text-emerald-800 dark:bg-emerald-900/50 dark:text-emerald-300 text-xs">
                                                    Accepted
                                                </Badge>
                                            </div>
                                            <p className="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                                                {row.customer} · <span className="font-mono font-medium text-slate-700 dark:text-slate-300">{currency.format(row.grand_total)}</span>
                                            </p>
                                        </div>

                                        <div className="flex items-center gap-2">
                                            <ConvertDialog quote={row} />
                                            <Button size="sm" variant="outline" asChild>
                                                <Link href={invoiceShow(row.id)}>View Details</Link>
                                            </Button>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </CardContent>
                    </Card>
                )}

                {/* All Quotations Table with Quick Actions & Conversion */}
                <Card className="border-slate-200/80 dark:border-slate-800 shadow-sm">
                    <CardContent className="p-6 space-y-4">
                        <div className="flex items-center justify-between">
                            <h3 className="font-bold text-slate-900 dark:text-slate-100 text-base">All Quotations in Pipeline</h3>
                            <span className="text-xs text-slate-500">{quotations.length} total quotes</span>
                        </div>

                        {quotations.length === 0 ? (
                            <p className="text-sm text-slate-500 text-center py-8">No quotations created yet.</p>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="w-full text-sm">
                                    <thead>
                                        <tr className="border-b border-slate-200 dark:border-slate-800 text-xs uppercase font-semibold text-slate-500 dark:text-slate-400">
                                            <th className="pb-3 text-left">Quote #</th>
                                            <th className="pb-3 text-left">Customer</th>
                                            <th className="pb-3 text-left">Status</th>
                                            <th className="pb-3 text-right">Amount</th>
                                            <th className="pb-3 text-right">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-slate-100 dark:divide-slate-800/60">
                                        {quotations.map((row) => (
                                            <tr key={row.id} className="hover:bg-slate-50/50 dark:hover:bg-slate-800/30">
                                                <td className="py-3 font-mono font-medium">
                                                    <Link href={invoiceShow(row.id)} className="hover:underline text-slate-900 dark:text-slate-100">
                                                        {row.invoice_number}
                                                    </Link>
                                                </td>
                                                <td className="py-3 text-slate-700 dark:text-slate-300">
                                                    {row.customer}
                                                </td>
                                                <td className="py-3">
                                                    <Badge className={`capitalize text-xs font-semibold px-2 py-0.5 ${statusColors[row.status] ?? ''}`}>
                                                        {row.status_label}
                                                    </Badge>
                                                </td>
                                                <td className="py-3 text-right font-mono font-semibold text-slate-900 dark:text-slate-100">
                                                    {currency.format(row.grand_total)}
                                                </td>
                                                <td className="py-3 text-right space-x-2">
                                                    {row.converted_to_id ? (
                                                        <Button size="sm" variant="ghost" className="text-xs text-blue-600 font-mono" asChild>
                                                            <Link href={invoiceShow(row.converted_to_id)}>
                                                                Converted ✓
                                                            </Link>
                                                        </Button>
                                                    ) : (
                                                        <ConvertDialog quote={row} />
                                                    )}
                                                    <Button size="sm" variant="outline" asChild>
                                                        <Link href={invoiceShow(row.id)}>View</Link>
                                                    </Button>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </CardContent>
                </Card>

                <ChaseList
                    title="Opened, not answered"
                    description="The customer looked and said nothing — where quotes usually go cold."
                    icon={<Eye className="size-4" />}
                    rows={needsChase}
                    onNudge={sendFollowUp}
                />

                <ChaseList
                    title="Expiring soon"
                    description="Still open, and the validity window is closing."
                    icon={<BellRing className="size-4" />}
                    rows={expiringSoon}
                    onNudge={sendFollowUp}
                />
            </div>
        </>
    );
}

/**
 * 1-Click Convert to Invoice Dialog
 */
function ConvertDialog({ quote }: { quote: ExtendedQuotationRow }) {
    const [open, setOpen] = useState(false);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button size="sm" className="bg-emerald-600 hover:bg-emerald-700 text-white font-medium shadow-sm">
                    <ArrowRightLeft className="size-3.5 mr-1" />
                    Convert to Invoice
                </Button>
            </DialogTrigger>
            <DialogContent className="max-w-md">
                <DialogHeader>
                    <DialogTitle>Convert Quotation {quote.invoice_number}</DialogTitle>
                </DialogHeader>
                <p className="text-xs text-slate-500">
                    Converting this quotation will issue a new sales invoice with the same line items, charges, and customer pricing for <strong>{quote.customer}</strong> ({currency.format(quote.grand_total)}).
                </p>

                <Form
                    {...InvoiceController.convert.form(quote.id)}
                    onSuccess={() => setOpen(false)}
                    className="space-y-4 pt-2"
                >
                    {({ processing }) => (
                        <>
                            <div className="grid gap-1.5">
                                <Label htmlFor="doc_type" className="text-xs font-semibold">Target Document Type</Label>
                                <Select name="document_type" defaultValue="general_invoice">
                                    <SelectTrigger id="doc_type">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="general_invoice">Sales Invoice (General)</SelectItem>
                                        <SelectItem value="jewelry_invoice">Jewelry Invoice</SelectItem>
                                        <SelectItem value="delivery_challan">Delivery Challan</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>

                            <div className="grid gap-1.5">
                                <Label htmlFor="due_date" className="text-xs font-semibold">Payment Due Date (optional)</Label>
                                <Input
                                    id="due_date"
                                    name="due_date"
                                    type="date"
                                    defaultValue={new Date(Date.now() + 7 * 86400000).toISOString().slice(0, 10)}
                                />
                            </div>

                            <div className="flex items-center gap-2 pt-1">
                                <input
                                    type="checkbox"
                                    id="apply_advances"
                                    name="apply_advances"
                                    value="1"
                                    defaultChecked
                                    className="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500"
                                />
                                <Label htmlFor="apply_advances" className="text-xs text-slate-700 dark:text-slate-300 font-medium">
                                    Auto-apply available customer advances if present
                                </Label>
                            </div>

                            <DialogFooter className="gap-2 pt-2">
                                <DialogClose asChild>
                                    <Button type="button" variant="secondary">Cancel</Button>
                                </DialogClose>
                                <Button disabled={processing} className="bg-emerald-600 hover:bg-emerald-700 text-white font-semibold">
                                    {processing ? 'Converting…' : 'Convert Now'}
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

/**
 * A titled list of quotations worth a nudge, each with a one-click follow-up.
 * Renders nothing when empty, so the board never shows an empty box.
 */
function ChaseList({
    title,
    description,
    icon,
    rows,
    onNudge,
}: {
    title: string;
    description: string;
    icon: ReactNode;
    rows: QuotationRow[];
    onNudge: (row: QuotationRow) => void;
}) {
    if (rows.length === 0) {
        return null;
    }

    return (
        <Card>
            <CardContent className="pt-6">
                <div className="flex items-center gap-2">
                    {icon}
                    <h3 className="text-sm font-semibold">{title}</h3>
                    <Badge variant="secondary">{rows.length}</Badge>
                </div>
                <p className="mt-1 text-xs text-muted-foreground">{description}</p>

                <div className="mt-4 divide-y">
                    {rows.map((row) => (
                        <div
                            key={row.id}
                            className="flex flex-wrap items-center justify-between gap-3 py-3"
                        >
                            <div className="min-w-0">
                                <Link
                                    href={invoiceShow(row.id)}
                                    className="text-sm font-medium hover:underline"
                                >
                                    {row.invoice_number}
                                </Link>
                                <p className="truncate text-xs text-muted-foreground">
                                    {row.customer} ·{' '}
                                    {currency.format(row.grand_total)}
                                    {row.valid_until
                                        ? ` · valid until ${row.valid_until}`
                                        : ''}
                                </p>
                            </div>
                            <Button
                                size="sm"
                                variant="outline"
                                onClick={() => onNudge(row)}
                            >
                                <BellRing className="size-4" />
                                Send follow-up
                            </Button>
                        </div>
                    ))}
                </div>
            </CardContent>
        </Card>
    );
}
