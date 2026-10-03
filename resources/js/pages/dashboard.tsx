import { Head, Link } from '@inertiajs/react';
import { AlertTriangle, ArrowRight, Bell, MessageCircle, Plus, TrendingUp, TrendingDown, DollarSign, Receipt, Clock } from 'lucide-react';
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
import { buildPublicInvoiceUrl, buildWhatsAppShareUrl } from '@/lib/share-invoice';
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

type AcceptedQuotation = {
    id: number;
    invoice_number: string;
    invoice_date: string;
    status: string;
    grand_total: string;
    quotation_response: string | null;
    customer: { id: number; full_name: string; mobile_number: string };
};

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

type WaitingQuote = {
    id: number;
    invoice_number: string;
    customer: string;
    mobile_number: string;
    grand_total: number;
    age_days: number;
    viewed_at: string | null;
    token: string | null;
};

export default function Dashboard({
    stats,
    months,
    acceptedQuotationsToConvert = [],
    waitingOnCustomer = [],
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
    acceptedQuotationsToConvert?: AcceptedQuotation[];
    waitingOnCustomer?: WaitingQuote[];
    recentInvoices: RecentInvoice[];
    recentCustomers: RecentCustomer[];
    upcomingFollowups: Followup[];
    overdueInvoices: OverdueInvoice[];
    rates: MetalRate[];
    reminderCount: number;
    catalog: {
        total: number;
        active: number;
        drafts: number;
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

                {acceptedQuotationsToConvert.length > 0 && (
                    <Card className="border-emerald-300 bg-emerald-50/70 dark:border-emerald-900 dark:bg-emerald-950/40 shadow-sm">
                        <CardContent className="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 py-4">
                            <div className="flex items-center gap-3">
                                <div className="size-9 rounded-full bg-emerald-600 text-white flex items-center justify-center font-bold text-lg shrink-0">
                                    ✓
                                </div>
                                <div>
                                    <p className="font-bold text-slate-900 dark:text-slate-100 text-sm">
                                        {acceptedQuotationsToConvert.length} Accepted Quotation{acceptedQuotationsToConvert.length === 1 ? '' : 's'} Ready for Invoice Conversion!
                                    </p>
                                    <p className="text-xs text-slate-600 dark:text-slate-400 mt-0.5">
                                        Customers accepted these quotes. Click convert to turn them into sales invoices and collect payment.
                                    </p>
                                </div>
                            </div>
                            <Button size="sm" className="bg-emerald-600 hover:bg-emerald-700 text-white shadow-sm shrink-0" asChild>
                                <Link href="/quotations">
                                    View &amp; Convert Quotes <ArrowRight className="size-4 ml-1" />
                                </Link>
                            </Button>
                        </CardContent>
                    </Card>
                )}

                {waitingOnCustomer.length > 0 && (
                    <Card className="border-amber-200 bg-amber-50/60 dark:border-amber-900/50 dark:bg-amber-950/30 shadow-sm">
                        <CardHeader className="pb-2">
                            <CardTitle className="text-sm font-semibold text-amber-900 dark:text-amber-200">
                                {waitingOnCustomer.length} quotation{waitingOnCustomer.length === 1 ? '' : 's'} waiting on a customer
                            </CardTitle>
                            <p className="text-xs text-amber-700/90 dark:text-amber-400/80">
                                Sent 2+ days ago, still no answer. Nudge the right customer instead of chasing everyone.
                            </p>
                        </CardHeader>
                        <CardContent className="space-y-1">
                            {waitingOnCustomer.map((q) => (
                                <div key={q.id} className="flex flex-wrap items-center justify-between gap-2 rounded-lg px-2.5 py-2 text-sm hover:bg-amber-100/60 dark:hover:bg-amber-950/40 transition-colors">
                                    <Link
                                        href={showInvoice(q.id)}
                                        className="truncate text-slate-700 dark:text-slate-300 hover:text-slate-900 dark:hover:text-slate-100"
                                    >
                                        {q.invoice_number} · <span className="text-slate-500">{q.customer}</span>
                                        <span className="ml-2 text-[11px] rounded-full border border-amber-200 dark:border-amber-800 px-1.5 py-0.5 text-amber-700 dark:text-amber-400">
                                            {q.viewed_at ? 'opened · not answered' : 'not opened'} · {q.age_days}d ago
                                        </span>
                                    </Link>
                                    <div className="flex items-center gap-2">
                                        <span className="font-semibold text-slate-900 dark:text-slate-100 tabular-nums">
                                            {currency.format(q.grand_total)}
                                        </span>
                                        {q.mobile_number && (
                                            <Button size="sm" variant="outline" asChild className="border-emerald-200 text-emerald-700 hover:bg-emerald-50 dark:border-emerald-800 dark:text-emerald-300 dark:hover:bg-emerald-950/30">
                                                <a
                                                    href={buildWhatsAppShareUrl(q.mobile_number, nudgeMessage(q))}
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                >
                                                    <MessageCircle className="size-4" />
                                                    Nudge
                                                </a>
                                            </Button>
                                        )}
                                    </div>
                                </div>
                            ))}
                        </CardContent>
                    </Card>
                )}

                {stats.overdue_count > 0 && (
                    <Card className="border-blue-200 bg-gradient-to-r from-blue-50 to-indigo-50 dark:from-blue-950/40 dark:to-indigo-950/30 shadow-sm">
                        <CardContent className="flex items-center gap-3 py-3">
                            <div className="size-8 rounded-full bg-blue-100 dark:bg-blue-900/50 flex items-center justify-center shrink-0">
                                <AlertTriangle className="size-4 text-blue-600 dark:text-blue-400" />
                            </div>
                            <p className="text-sm text-blue-800 dark:text-blue-200 font-medium">
                                {stats.overdue_count} invoice{stats.overdue_count === 1 ? '' : 's'} overdue,{' '}
                                <span className="font-semibold">{currency.format(stats.overdue_amount)}</span> outstanding.
                            </p>
                        </CardContent>
                    </Card>
                )}

                <div className="grid grid-cols-2 gap-3 md:grid-cols-4">
                    <StatCard label="Invoices this month" value={stats.invoices_this_month} icon={<Receipt className="size-4 text-blue-500" />} trend="up" />
                    <StatCard label="Total sales" value={currency.format(stats.total_sales)} icon={<DollarSign className="size-4 text-indigo-500" />} trend="up" />
                    <StatCard label="Amount collected" value={currency.format(stats.total_collected)} icon={<TrendingUp className="size-4 text-purple-500" />} trend="up" />
                    <StatCard label="Outstanding" value={currency.format(stats.total_outstanding)} icon={<Clock className="size-4 text-blue-500" />} emphasize={stats.total_outstanding > 0} trend={stats.total_outstanding > 0 ? 'up' : 'neutral'} />
                </div>

                <div className="grid grid-cols-3 gap-3 md:grid-cols-3">
                    <StatCard label="Paid" value={stats.paid} icon={<TrendingUp className="size-4 text-emerald-500" />} small />
                    <StatCard label="Unpaid" value={stats.unpaid} icon={<TrendingDown className="size-4 text-red-500" />} small />
                    <StatCard label="Partially paid" value={stats.partially_paid} icon={<Clock className="size-4 text-blue-500" />} small />
                </div>

                <div className="grid gap-4 lg:grid-cols-3">
                    <Card className="shadow-sm hover:shadow-md transition-shadow border-blue-200/80 dark:border-blue-800/60">
                        <CardHeader>
                            <CardTitle className="text-sm font-semibold text-blue-700 dark:text-blue-200">Sales vs collected (6 months)</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="flex h-40 items-end gap-3">
                                {months.map((m) => (
                                    <div key={m.label} className="flex h-32 w-full flex-col items-center justify-end gap-1">
                                        <div className="flex w-full items-end justify-center gap-1">
                                            <div
                                                className="w-1/2 rounded-t-lg bg-gradient-to-t from-blue-600 to-blue-500 shadow-sm transition-all hover:from-blue-700 hover:to-blue-600"
                                                style={{ height: `${Math.max((m.sales / maxSales) * 100, 2)}%` }}
                                                title={`Sales: ${currency.format(m.sales)}`}
                                            />
                                            <div
                                                className="w-1/2 rounded-t-lg bg-gradient-to-t from-indigo-400 to-indigo-300 shadow-sm transition-all hover:from-indigo-500 hover:to-indigo-400"
                                                style={{ height: `${Math.max((m.collected / maxSales) * 100, 2)}%` }}
                                                title={`Collected: ${currency.format(m.collected)}`}
                                            />
                                        </div>
                                        <span className="text-xs text-slate-500 dark:text-slate-400 font-medium">{m.label}</span>
                                    </div>
                                ))}
                            </div>
                            <div className="mt-3 flex gap-4 text-xs text-slate-600 dark:text-slate-400">
                                <span className="flex items-center gap-1.5">
                                    <span className="inline-block size-2.5 rounded-full bg-blue-500" />
                                    Sales
                                </span>
                                <span className="flex items-center gap-1.5">
                                    <span className="inline-block size-2.5 rounded-full bg-indigo-400" />
                                    Collected
                                </span>
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
                                        <span className="font-medium">₹{r.rate_per_gram}/g</span>
                                    </div>
                                ))}
                            </CardContent>
                        </Card>
                    )}
                </div>

                <div className="grid gap-4 lg:grid-cols-2">
                    <Card className="shadow-sm hover:shadow-md transition-shadow border-blue-200/80 dark:border-blue-800/60">
                        <CardHeader className="flex-row items-center justify-between">
                            <CardTitle className="text-sm font-semibold text-blue-700 dark:text-blue-200">Recent invoices</CardTitle>
                            <Link href="/invoices" className="text-sm text-blue-700 dark:text-blue-300 hover:underline font-medium">
                                View all <ArrowRight className="inline size-3" />
                            </Link>
                        </CardHeader>
                        <CardContent className="space-y-1">
                            {recentInvoices.length === 0 ? (
                                <p className="text-sm text-slate-400 italic">No invoices yet.</p>
                            ) : (
                                recentInvoices.map((inv) => (
                                    <Link
                                        key={inv.id}
                                        href={showInvoice(inv.id)}
                                        className="flex items-center justify-between rounded-lg px-2.5 py-2 text-sm hover:bg-blue-50/60 dark:hover:bg-blue-950/30 transition-colors group"
                                    >
                                        <span className="truncate text-slate-700 dark:text-slate-300 group-hover:text-slate-900 dark:group-hover:text-slate-100">
                                            {inv.invoice_number} · <span className="text-slate-500">{inv.customer.full_name}</span>
                                        </span>
                                        <span className="font-semibold text-slate-900 dark:text-slate-100 tabular-nums">{currency.format(Number(inv.grand_total))}</span>
                                    </Link>
                                ))
                            )}
                        </CardContent>
                    </Card>

                    <Card className="shadow-sm hover:shadow-md transition-shadow border-blue-200/80 dark:border-blue-800/60">
                        <CardHeader>
                            <CardTitle className="text-sm font-semibold text-blue-700 dark:text-blue-200">Overdue invoices</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-1">
                            {overdueInvoices.length === 0 ? (
                                <p className="text-sm text-slate-400 italic">Nothing overdue. Nice.</p>
                            ) : (
                                overdueInvoices.map((inv) => (
                                    <Link
                                        key={inv.id}
                                        href={showInvoice(inv.id)}
                                        className="flex items-center justify-between rounded-lg px-2.5 py-2 text-sm hover:bg-red-50/70 dark:hover:bg-red-950/30 transition-colors group"
                                    >
                                        <span className="truncate text-slate-700 dark:text-slate-300 group-hover:text-slate-900 dark:group-hover:text-slate-100">
                                            {inv.invoice_number} · <span className="text-slate-500">{inv.customer.full_name}</span>
                                        </span>
                                        <span className="font-semibold text-red-600 dark:text-red-400 tabular-nums">
                                            {currency.format(Number(inv.balance_amount))}
                                        </span>
                                    </Link>
                                ))
                            )}
                        </CardContent>
                    </Card>

                    <Card className="shadow-sm hover:shadow-md transition-shadow border-blue-200/80 dark:border-blue-800/60">
                        <CardHeader className="flex-row items-center justify-between">
                            <CardTitle className="text-sm font-semibold text-blue-700 dark:text-blue-200">Recent customers</CardTitle>
                            <Button size="sm" variant="outline" asChild className="border-blue-200 text-blue-700 hover:bg-blue-50 dark:border-blue-800 dark:text-blue-300 dark:hover:bg-blue-950/30">
                                <Link href={createCustomer()}>
                                    <Plus className="size-4" />
                                    New
                                </Link>
                            </Button>
                        </CardHeader>
                        <CardContent className="space-y-1">
                            {recentCustomers.length === 0 ? (
                                <p className="text-sm text-slate-400 italic">No customers yet.</p>
                            ) : (
                                recentCustomers.map((c) => (
                                    <Link
                                        key={c.id}
                                        href={`/customers/${c.id}`}
                                        className="flex items-center justify-between rounded-lg px-2.5 py-2 text-sm hover:bg-blue-50/60 dark:hover:bg-blue-950/30 transition-colors group"
                                    >
                                        <div className="flex items-center gap-2">
                                            <div className="size-8 rounded-full bg-blue-100 dark:bg-blue-900/40 flex items-center justify-center text-blue-700 dark:text-blue-300 font-semibold text-xs">
                                                {c.full_name.split(' ').map(n => n[0]).join('').slice(0, 2).toUpperCase()}
                                            </div>
                                            <span className="text-slate-700 dark:text-slate-300 group-hover:text-slate-900 dark:group-hover:text-slate-100">{c.full_name}</span>
                                        </div>
                                        <span className="text-slate-400 text-xs font-mono">{c.mobile_number}</span>
                                    </Link>
                                ))
                            )}
                        </CardContent>
                    </Card>

                    <Card className="shadow-sm hover:shadow-md transition-shadow border-blue-200/80 dark:border-blue-800/60">
                        <CardHeader className="flex-row items-center justify-between">
                            <CardTitle className="text-sm font-semibold text-blue-700 dark:text-blue-200">Product catalog</CardTitle>
                            <div className="flex items-center gap-2">
                                <Button size="sm" variant="outline" asChild className="border-blue-200 text-blue-700 hover:bg-blue-50 dark:border-blue-800 dark:text-blue-300 dark:hover:bg-blue-950/30">
                                    <Link href={catalogIndex()}>
                                        Manage
                                    </Link>
                                </Button>
                            </div>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            <div className="flex gap-4 text-sm">
                                <span className="flex items-center gap-1.5">
                                    <span className="text-slate-400 text-xs uppercase tracking-wider">Products</span>{' '}
                                    <span className="font-bold text-slate-900 dark:text-slate-100 tabular-nums">{catalog.total}</span>
                                </span>
                                <span className="flex items-center gap-1.5">
                                    <span className="text-slate-400 text-xs uppercase tracking-wider">Active</span>{' '}
                                    <span className="font-bold text-emerald-600 dark:text-emerald-400 tabular-nums">{catalog.active}</span>
                                </span>
                                <span className="flex items-center gap-1.5">
                                    <span className="text-slate-400 text-xs uppercase tracking-wider">Drafts</span>{' '}
                                    <span className="font-bold text-blue-600 dark:text-blue-400 tabular-nums">{catalog.drafts}</span>
                                </span>
                            </div>

                            {catalog.total === 0 ? (
                                <p className="text-sm text-slate-400 italic">
                                    No products yet. Add what you sell, then build quotations straight from the catalog.
                                </p>
                            ) : (
                                <div className="space-y-1.5">
                                    {catalog.recent.map((item) => (
                                        <Link
                                            key={item.id}
                                            href={catalogIndex()}
                                            className="flex items-center justify-between rounded-lg px-2.5 py-2 text-sm hover:bg-blue-50/60 dark:hover:bg-blue-950/30 transition-colors group"
                                        >
                                            <span className="truncate text-slate-700 dark:text-slate-300 group-hover:text-slate-900 dark:group-hover:text-slate-100">
                                                {item.name}
                                                {item.brand && (
                                                    <span className="text-slate-400"> · {item.brand}</span>
                                                )}
                                            </span>
                                            {item.default_rate && (
                                                <span className="shrink-0 font-semibold text-slate-900 dark:text-slate-100 tabular-nums">
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

                            <Button size="sm" variant="outline" asChild className="w-full border-dashed border-2 border-blue-200 dark:border-blue-800 text-blue-700 hover:border-blue-400 hover:text-blue-800 dark:hover:border-blue-600 dark:hover:text-blue-200">
                                <Link href={catalogIndex()}>
                                    <Plus className="size-4 mr-1.5" />
                                    Add product
                                </Link>
                            </Button>
                        </CardContent>
                    </Card>

                    <Card className="shadow-sm hover:shadow-md transition-shadow border-blue-200/80 dark:border-blue-800/60">
                        <CardHeader className="flex-row items-center justify-between">
                            <CardTitle className="text-sm font-semibold text-blue-700 dark:text-blue-200">Upcoming follow-ups</CardTitle>
                            <Link href={remindersIndex()} className="text-sm text-blue-700 dark:text-blue-300 hover:underline font-medium">
                                View all <ArrowRight className="inline size-3" />
                            </Link>
                        </CardHeader>
                        <CardContent className="space-y-1">
                            {upcomingFollowups.length === 0 ? (
                                <p className="text-sm text-slate-400 italic">Nothing scheduled this week.</p>
                            ) : (
                                upcomingFollowups.map((f) => (
                                    <Link
                                        key={f.id}
                                        href={`/customers/${f.customer.id}`}
                                        className="flex items-center justify-between rounded-lg px-2.5 py-2 text-sm hover:bg-blue-50/60 dark:hover:bg-blue-950/30 transition-colors group"
                                    >
                                        <div className="flex items-center gap-2">
                                            <div className="size-8 rounded-full bg-blue-100 dark:bg-blue-900/40 flex items-center justify-center text-blue-700 dark:text-blue-300">
                                                <Clock className="size-4" />
                                            </div>
                                            <span className="text-slate-700 dark:text-slate-300 group-hover:text-slate-900 dark:group-hover:text-slate-100">{f.customer.full_name}</span>
                                        </div>
                                        <span className="text-xs text-slate-500 dark:text-slate-400 font-medium tabular-nums">
                                            {new Date(f.followup_date).toLocaleDateString('en-IN', { day: 'numeric', month: 'short' })}
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

function nudgeMessage(q: WaitingQuote): string {
    const parts = [
        `Hi ${q.customer.split(' ')[0]}, just a gentle check-in on quotation ${q.invoice_number} for ${currency.format(q.grand_total)}.`,
        "Have you had a chance to look it over? Any feedback would help us shape it better for you.",
    ];
    if (q.token) {
        parts.push(`View it again here: ${buildPublicInvoiceUrl(q.token)}`);
    }
    return parts.join('\n\n');
}

function StatCard({ label, value, icon, emphasize, small, trend }: { label: string; value: string | number; icon?: React.ReactNode; emphasize?: boolean; small?: boolean; trend?: 'up' | 'down' | 'neutral' }) {
    return (
        <Card className={`min-w-0 overflow-hidden transition-all hover:shadow-md bg-white dark:bg-slate-900 border-blue-200/80 dark:border-blue-800/60 ${emphasize ? 'ring-2 ring-blue-400 dark:ring-blue-600' : ''}`}>
            <CardContent className="min-w-0 p-4">
                <div className="flex items-center justify-between">
                    <p className="truncate text-xs text-slate-500 dark:text-slate-400 font-medium" title={String(value)}>{label}</p>
                    {icon && <div className="shrink-0">{icon}</div>}
                </div>
                <p
                    className={`mt-2 truncate font-bold tabular-nums ${small ? 'text-base' : 'text-lg md:text-xl'} ${emphasize ? 'text-blue-600 dark:text-blue-400' : 'text-slate-900 dark:text-slate-100'}`}
                    title={String(value)}
                >
                    {value}
                </p>
                {trend && trend !== 'neutral' && (
                    <div className="mt-1 flex items-center gap-1 text-xs">
                        {trend === 'up' ? (
                            <TrendingUp className="size-3 text-emerald-500" />
                        ) : (
                            <TrendingDown className="size-3 text-red-500" />
                        )}
                        <span className={trend === 'up' ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400'}>
                            {trend === 'up' ? 'Growing' : 'Declining'}
                        </span>
                    </div>
                )}
            </CardContent>
        </Card>
    );
}

Dashboard.layout = {
    breadcrumbs: [{ title: 'Dashboard', href: dashboard() }],
};
