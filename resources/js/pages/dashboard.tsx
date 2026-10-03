import { Head, Link } from '@inertiajs/react';
import { Bell, Plus } from 'lucide-react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { dashboard } from '@/routes';
import { create as createInvoice, show as showInvoice } from '@/routes/invoices';
import { index as remindersIndex } from '@/routes/reminders';

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

export default function Dashboard({
    stats,
    months,
    recentInvoices,
    reminderCount,
}: {
    stats: Stats;
    months: MonthPoint[];
    recentInvoices: RecentInvoice[];
    reminderCount: number;
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

                <div className="grid grid-cols-2 gap-3 md:grid-cols-4">
                    <StatCard label="Invoices this month" value={stats.invoices_this_month} />
                    <StatCard label="Total sales" value={currency.format(stats.total_sales)} />
                    <StatCard label="Amount collected" value={currency.format(stats.total_collected)} />
                    <StatCard label="Outstanding" value={currency.format(stats.total_outstanding)} />
                </div>

                <div className="grid gap-4 lg:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle>Sales vs collected (6 months)</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="flex h-40 items-end gap-3">
                                {months.map((m) => (
                                    <div key={m.label} className="flex h-32 w-full flex-col items-center justify-end gap-1">
                                        <div className="flex w-full items-end justify-center gap-1">
                                            <div
                                                className="w-1/2 rounded-t bg-blue-600"
                                                style={{ height: `${Math.max((m.sales / maxSales) * 100, 2)}%` }}
                                            />
                                            <div
                                                className="w-1/2 rounded-t bg-blue-400"
                                                style={{ height: `${Math.max((m.collected / maxSales) * 100, 2)}%` }}
                                            />
                                        </div>
                                        <span className="text-xs text-slate-500">{m.label}</span>
                                    </div>
                                ))}
                            </div>
                            <div className="mt-3 flex gap-4 text-xs text-slate-600">
                                <span className="flex items-center gap-1.5">
                                    <span className="inline-block size-2.5 rounded-full bg-blue-600" />
                                    Sales
                                </span>
                                <span className="flex items-center gap-1.5">
                                    <span className="inline-block size-2.5 rounded-full bg-indigo-400" />
                                    Collected
                                </span>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex-row items-center justify-between">
                            <CardTitle>Recent invoices</CardTitle>
                            <Link href="/invoices" className="text-sm text-blue-700 hover:underline font-medium">
                                View all
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
                                        className="flex items-center justify-between rounded-lg px-2.5 py-2 text-sm hover:bg-slate-50 transition-colors"
                                    >
                                        <span className="truncate text-slate-700">
                                            {inv.invoice_number} · <span className="text-slate-500">{inv.customer.full_name}</span>
                                        </span>
                                        <span className="font-semibold text-slate-900 tabular-nums">{currency.format(Number(inv.grand_total))}</span>
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

function StatCard({ label, value }: { label: string; value: string | number }) {
    return (
        <Card className="border-blue-200/80 dark:border-blue-800/60 bg-white dark:bg-slate-900">
            <CardContent className="p-4">
                <p className="truncate text-xs text-slate-500 dark:text-slate-400 font-medium">{label}</p>
                <p className="truncate text-lg font-semibold tabular-nums">{value}</p>
            </CardContent>
        </Card>
    );
}

Dashboard.layout = {
    breadcrumbs: [{ title: 'Dashboard', href: dashboard() }],
};
