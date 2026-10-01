import { Head, Link } from '@inertiajs/react';
import { AlertTriangle, ArrowRight, Bell, Plus } from 'lucide-react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { dashboard } from '@/routes';
import { index as catalogIndex } from '@/routes/catalog';
import { create as createCustomer } from '@/routes/customers';
import { create as createInvoice, show as showInvoice } from '@/routes/invoices';
import { index as remindersIndex } from '@/routes/reminders';
import { rateTypeLabel } from '@/lib/industries';
import type { MetalRate } from '@/types/invoice';

const currency = new Intl.NumberFormat('en-IN', { style: 'currency', currency: 'INR', maximumFractionDigits: 0 });

type Stats = {
    total_invoices: number;
    invoices_this_month: number;
    paid: number;
    unpaid: number;
    partially_paid: number;
    overdue_count: number;
    overdue_amount: number;
    total_sales: number;
    total_collected: number;
    total_outstanding: number;
};

type MonthPoint = { label: string; sales: number; collected: number };

type RecentInvoice = {
    id: number;
    invoice_number: string;
    invoice_date: string;
    status: string;
    grand_total: string;
    balance_amount: string;
    customer: { id: number; full_name: string };
};

type RecentCustomer = { id: number; full_name: string; mobile_number: string; created_at: string };

type Followup = {
    id: number;
    followup_date: string;
    notes: string | null;
    customer: { id: number; full_name: string };
    assignee: { id: number; name: string } | null;
};

type OverdueInvoice = {
    id: number;
    invoice_number: string;
    due_date: string;
    balance_amount: string;
    customer: { id: number; full_name: string };
};

export default function Dashboard({
    stats,
    months,
    recentInvoices,
    recentCustomers,
    upcomingFollowups,
    overdueInvoices,
    rates,
    reminderCount,
    catalog,
}: {
    stats: Stats;
    months: MonthPoint[];
    recentInvoices: RecentInvoice[];
    recentCustomers: RecentCustomer[];
    upcomingFollowups: Followup[];
    overdueInvoices: OverdueInvoice[];
    rates: MetalRate[];
    reminderCount: number;
    catalog: {
        total: number;
        active: number;
        recent: {
            id: number;
            name: string;
            brand: string | null;
            rate_type: string;
            default_rate: string | null;
        }[];
    };
}) {
    const maxSales = Math.max(...months.map((m) => Math.max(m.sales, m.collected)), 1);

    return (
        <>
            <Head title="Dashboard" />

            <div className="space-y-6 p-4 md:p-6">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <Heading title="Dashboard" description="Today's snapshot" />
                    <div className="flex gap-2">
                        <Button variant="outline" asChild className="relative">
                            <Link href={remindersIndex()}>
                                <Bell className="size-4" />
                                Reminders
                                {reminderCount > 0 && (
                                    <Badge className="absolute -top-2 -right-2 size-5 justify-center rounded-full p-0">
                                        {reminderCount}
                                    </Badge>
                                )}
                            </Link>
                        </Button>
                        <Button asChild>
                            <Link href={createInvoice()}>
                                <Plus className="size-4" />
                                New invoice
                            </Link>
                        </Button>
                    </div>
                </div>

                {stats.overdue_count > 0 && (
                    <Card className="border-red-200 bg-red-50 dark:border-red-900 dark:bg-red-950/40">
                        <CardContent className="flex items-center gap-3 py-3">
                            <AlertTriangle className="size-5 shrink-0 text-red-600 dark:text-red-400" />
                            <p className="text-sm text-red-800 dark:text-red-200">
                                {stats.overdue_count} invoice{stats.overdue_count === 1 ? '' : 's'} overdue,{' '}
                                {currency.format(stats.overdue_amount)} outstanding.
                            </p>
                        </CardContent>
                    </Card>
                )}

                <div className="grid grid-cols-2 gap-3 md:grid-cols-4">
                    <Stat label="Invoices this month" value={stats.invoices_this_month} />
                    <Stat label="Total sales" value={currency.format(stats.total_sales)} />
                    <Stat label="Collected" value={currency.format(stats.total_collected)} />
                    <Stat label="Outstanding" value={currency.format(stats.total_outstanding)} emphasize={stats.total_outstanding > 0} />
                </div>

                <div className="grid grid-cols-3 gap-3 md:grid-cols-3">
                    <Stat label="Paid" value={stats.paid} small />
                    <Stat label="Unpaid" value={stats.unpaid} small />
                    <Stat label="Partially paid" value={stats.partially_paid} small />
                </div>

                <div className="grid gap-4 lg:grid-cols-3">
                    <Card className="lg:col-span-2">
                        <CardHeader>
                            <CardTitle>Sales vs collected (6 months)</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="flex h-40 items-end gap-3">
                                {months.map((m) => (
                                    <div key={m.label} className="flex flex-1 flex-col items-center gap-1">
                                        <div className="flex h-32 w-full items-end justify-center gap-1">
                                            <div
                                                className="w-1/2 rounded-t bg-primary"
                                                style={{ height: `${Math.max((m.sales / maxSales) * 100, 2)}%` }}
                                                title={`Sales: ${currency.format(m.sales)}`}
                                            />
                                            <div
                                                className="w-1/2 rounded-t bg-emerald-400"
                                                style={{ height: `${Math.max((m.collected / maxSales) * 100, 2)}%` }}
                                                title={`Collected: ${currency.format(m.collected)}`}
                                            />
                                        </div>
                                        <span className="text-xs text-muted-foreground">{m.label}</span>
                                    </div>
                                ))}
                            </div>
                            <div className="mt-3 flex gap-4 text-xs text-muted-foreground">
                                <span><span className="mr-1 inline-block size-2 rounded-full bg-primary" />Sales</span>
                                <span><span className="mr-1 inline-block size-2 rounded-full bg-emerald-400" />Collected</span>
                            </div>
                        </CardContent>
                    </Card>

                    {rates.length > 0 && (
                        <Card>
                            <CardHeader>
                                <CardTitle>Today's metal rates</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-2">
                                {rates.map((r) => (
                                    <div key={r.id} className="flex justify-between text-sm">
                                        <span>{r.metal_type} {r.purity}</span>
                                        <span className="font-medium">â‚¹{r.rate_per_gram}/g</span>
                                    </div>
                                ))}
                            </CardContent>
                        </Card>
                    )}
                </div>

                <div className="grid gap-4 lg:grid-cols-2">
                    <Card>
                        <CardHeader className="flex-row items-center justify-between">
                            <CardTitle>Recent invoices</CardTitle>
                            <Link href="/invoices" className="text-sm text-muted-foreground hover:underline">
                                View all <ArrowRight className="inline size-3" />
                            </Link>
                        </CardHeader>
                        <CardContent className="space-y-1">
                            {recentInvoices.length === 0 ? (
                                <p className="text-sm text-muted-foreground">No invoices yet.</p>
                            ) : (
                                recentInvoices.map((inv) => (
                                    <Link
                                        key={inv.id}
                                        href={showInvoice(inv.id)}
                                        className="flex items-center justify-between rounded-md px-2 py-1.5 text-sm hover:bg-muted"
                                    >
                                        <span>{inv.invoice_number} Â· {inv.customer.full_name}</span>
                                        <span className="font-medium">{currency.format(Number(inv.grand_total))}</span>
                                    </Link>
                                ))
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex-row items-center justify-between">
                            <CardTitle>Overdue invoices</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-1">
                            {overdueInvoices.length === 0 ? (
                                <p className="text-sm text-muted-foreground">Nothing overdue. Nice.</p>
                            ) : (
                                overdueInvoices.map((inv) => (
                                    <Link
                                        key={inv.id}
                                        href={showInvoice(inv.id)}
                                        className="flex items-center justify-between rounded-md px-2 py-1.5 text-sm hover:bg-muted"
                                    >
                                        <span>{inv.invoice_number} Â· {inv.customer.full_name}</span>
                                        <span className="font-medium text-red-600 dark:text-red-400">
                                            {currency.format(Number(inv.balance_amount))}
                                        </span>
                                    </Link>
                                ))
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex-row items-center justify-between">
                            <CardTitle>Recent customers</CardTitle>
                            <Button size="sm" variant="outline" asChild>
                                <Link href={createCustomer()}>
                                    <Plus className="size-4" />
                                    New
                                </Link>
                            </Button>
                        </CardHeader>
                        <CardContent className="space-y-1">
                            {recentCustomers.length === 0 ? (
                                <p className="text-sm text-muted-foreground">No customers yet.</p>
                            ) : (
                                recentCustomers.map((c) => (
                                    <Link
                                        key={c.id}
                                        href={`/customers/${c.id}`}
                                        className="flex items-center justify-between rounded-md px-2 py-1.5 text-sm hover:bg-muted"
                                    >
                                        <span>{c.full_name}</span>
                                        <span className="text-muted-foreground">{c.mobile_number}</span>
                                    </Link>
                                ))
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex-row items-center justify-between">
                            <CardTitle>Product catalog</CardTitle>
                            <div className="flex items-center gap-2">
                                <Button size="sm" variant="outline" asChild>
                                    <Link href={catalogIndex()}>
                                        Manage
                                    </Link>
                                </Button>
                            </div>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            <div className="flex gap-4 text-sm">
                                <span>
                                    <span className="text-muted-foreground">
                                        Products
                                    </span>{' '}
                                    <span className="font-semibold">
                                        {catalog.total}
                                    </span>
                                </span>
                                <span>
                                    <span className="text-muted-foreground">
                                        Active
                                    </span>{' '}
                                    <span className="font-semibold">
                                        {catalog.active}
                                    </span>
                                </span>
                            </div>

                            {catalog.total === 0 ? (
                                <p className="text-sm text-muted-foreground">
                                    No products yet. Add what you sell, then
                                    build quotations straight from the catalog.
                                </p>
                            ) : (
                                <div className="space-y-1">
                                    {catalog.recent.map((item) => (
                                        <Link
                                            key={item.id}
                                            href={catalogIndex()}
                                            className="flex items-center justify-between rounded-md px-2 py-1.5 text-sm hover:bg-muted"
                                        >
                                            <span className="truncate">
                                                {item.name}
                                                {item.brand && (
                                                    <span className="text-muted-foreground">
                                                        {' '}
                                                        Â· {item.brand}
                                                    </span>
                                                )}
                                            </span>
                                            {item.default_rate && (
                                                <span className="shrink-0 font-medium">
                                                    {currency.format(
                                                        Number(
                                                            item.default_rate,
                                                        ),
                                                    )}
                                                    /{rateTypeLabel(
                                                        item.rate_type,
                                                    ).replace('Per ', '').toLowerCase()}
                                                </span>
                                            )}
                                        </Link>
                                    ))}
                                </div>
                            )}

                            <Button size="sm" variant="outline" asChild className="w-full">
                                <Link href={catalogIndex()}>
                                    <Plus className="size-4" />
                                    Add product
                                </Link>
                            </Button>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex-row items-center justify-between">
                            <CardTitle>Upcoming follow-ups</CardTitle>
                            <Link href={remindersIndex()} className="text-sm text-muted-foreground hover:underline">
                                View all <ArrowRight className="inline size-3" />
                            </Link>
                        </CardHeader>
                        <CardContent className="space-y-1">
                            {upcomingFollowups.length === 0 ? (
                                <p className="text-sm text-muted-foreground">Nothing scheduled this week.</p>
                            ) : (
                                upcomingFollowups.map((f) => (
                                    <Link
                                        key={f.id}
                                        href={`/customers/${f.customer.id}`}
                                        className="flex items-center justify-between rounded-md px-2 py-1.5 text-sm hover:bg-muted"
                                    >
                                        <span>{f.customer.full_name}</span>
                                        <span className="text-muted-foreground">
                                            {new Date(f.followup_date).toLocaleDateString()}
                                        </span>
                                    </Link>
                                ))
                            )}
                        </CardContent>
                    </Card>
                </div>
            </div>
        </>
    );
}

function Stat({ label, value, emphasize, small }: { label: string; value: string | number; emphasize?: boolean; small?: boolean }) {
    return (
        <Card className="min-w-0 overflow-hidden">
            <CardContent className="min-w-0 p-4">
                <p className="truncate text-xs text-muted-foreground" title={String(value)}>{label}</p>
                <p
                    className={`mt-1 truncate font-semibold tabular-nums ${small ? 'text-base' : 'text-lg md:text-xl'} ${emphasize ? 'text-brand-dark dark:text-brand-light' : ''}`}
                    title={String(value)}
                >
                    {value}
                </p>
            </CardContent>
        </Card>
    );
}

Dashboard.layout = {
    breadcrumbs: [{ title: 'Dashboard', href: dashboard() }],
};
