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

export default function QuotationPipeline({
    stages,
    openValue,
    needsChase,
    expiringSoon,
    followUpEnabled,
    followUpDays,
    dueCount,
}: {
    stages: Stage[];
    openValue: number;
    needsChase: QuotationRow[];
    expiringSoon: QuotationRow[];
    followUpEnabled: boolean;
    followUpDays: number;
    dueCount: number;
}) {
    function sendFollowUp(row: QuotationRow) {
        router.post(nudge(row.id), {}, { preserveScroll: true });
    }

    return (
        <>
            <Head title="Quotations" />

            <div className="space-y-6 p-4 md:p-6">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <Heading
                        title="Quotations"
                        description="What is open, what it is worth, and what needs a chase."
                    />
                    <div className="flex flex-wrap gap-2">
                        <Button asChild variant="outline" className="w-full sm:w-auto">
                            <Link
                                href={invoicesIndex() + '?document_type=quotation'}
                            >
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

                <div className="grid gap-4 sm:grid-cols-2">
                    <Card>
                        <CardContent className="pt-6">
                            <p className="text-sm text-muted-foreground">
                                Open pipeline value
                            </p>
                            <p className="mt-1 text-2xl font-semibold">
                                {currency.format(openValue)}
                            </p>
                            <p className="mt-1 text-xs text-muted-foreground">
                                Draft and sent quotations still in play.
                            </p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent className="pt-6">
                            <p className="text-sm text-muted-foreground">
                                Follow-ups due
                            </p>
                            <p className="mt-1 text-2xl font-semibold">
                                {dueCount}
                            </p>
                            <p className="mt-1 text-xs text-muted-foreground">
                                {followUpEnabled
                                    ? `Chased automatically ${followUpDays} day(s) after sending.`
                                    : 'Automatic chasing is off — turn it on in settings.'}
                            </p>
                        </CardContent>
                    </Card>
                </div>

                <div className="grid gap-3 sm:grid-cols-3 lg:grid-cols-6">
                    {stages.map((stage) => (
                        <div
                            key={stage.value}
                            className="rounded-lg border p-3 text-center"
                        >
                            <Badge
                                variant="secondary"
                                className={statusColors[stage.value] ?? ''}
                            >
                                {stage.label}
                            </Badge>
                            <p className="mt-2 text-lg font-semibold">
                                {stage.count}
                            </p>
                            <p className="text-xs text-muted-foreground">
                                {currency.format(stage.total)}
                            </p>
                        </div>
                    ))}
                </div>

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
