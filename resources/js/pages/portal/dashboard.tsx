import { Head, Link, router } from '@inertiajs/react';
import { Download, FileText, LogOut } from 'lucide-react';
import { AppLogo } from '@/components/app-logo';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import type { Paginated } from '@/types/customer';

type PortalInvoice = {
    id: number;
    invoice_number: string;
    invoice_date: string;
    due_date: string | null;
    status: string;
    document_type: string;
    grand_total: string;
    paid_amount: string;
    balance_amount: string;
};

const currency = new Intl.NumberFormat('en-IN', { style: 'currency', currency: 'INR', maximumFractionDigits: 0 });

export default function PortalDashboard({
    customer,
    invoices,
    totals,
}: {
    customer: { full_name: string; email: string | null; mobile_number: string };
    invoices: Paginated<PortalInvoice>;
    totals: { invoiced: number; paid: number; outstanding: number };
}) {
    return (
        <>
            <Head title="My invoices" />

            <div className="min-h-screen bg-muted/30">
                <header className="border-b bg-card">
                    <div className="mx-auto flex w-full max-w-3xl items-center justify-between px-4 py-3">
                        <AppLogo />
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={() => router.post('/portal/logout')}
                        >
                            <LogOut className="size-4" />
                            Sign out
                        </Button>
                    </div>
                </header>

                <main className="mx-auto w-full max-w-3xl space-y-4 px-4 py-6">
                    <div>
                        <h1 className="font-display text-2xl">Hi, {customer.full_name}</h1>
                        <p className="text-sm text-muted-foreground">
                            {customer.mobile_number}
                            {customer.email && ` · ${customer.email}`}
                        </p>
                    </div>

                    <div className="grid grid-cols-3 gap-3">
                        <Card>
                            <CardContent className="p-4">
                                <p className="text-xs text-muted-foreground">Invoiced</p>
                                <p className="truncate text-lg font-semibold tabular-nums">{currency.format(totals.invoiced)}</p>
                            </CardContent>
                        </Card>
                        <Card>
                            <CardContent className="p-4">
                                <p className="text-xs text-muted-foreground">Paid</p>
                                <p className="truncate text-lg font-semibold tabular-nums">{currency.format(totals.paid)}</p>
                            </CardContent>
                        </Card>
                        <Card>
                            <CardContent className="p-4">
                                <p className="text-xs text-muted-foreground">Due</p>
                                <p className="truncate text-lg font-semibold tabular-nums text-gold-dark dark:text-gold-light">
                                    {currency.format(totals.outstanding)}
                                </p>
                            </CardContent>
                        </Card>
                    </div>

                    <div className="space-y-2">
                        {invoices.data.length === 0 && (
                            <Card>
                                <CardContent className="py-10 text-center text-sm text-muted-foreground">
                                    No invoices yet.
                                </CardContent>
                            </Card>
                        )}
                        {invoices.data.map((invoice) => (
                            <Card key={invoice.id}>
                                <CardContent className="flex items-center justify-between gap-3 p-4">
                                    <div className="min-w-0">
                                        <Link
                                            href={`/portal/invoices/${invoice.id}`}
                                            className="font-medium hover:underline"
                                        >
                                            {invoice.invoice_number}
                                        </Link>
                                        <p className="text-xs text-muted-foreground">
                                            {new Date(invoice.invoice_date).toLocaleDateString()}
                                            {' · '}
                                            <Badge variant="secondary" className="capitalize">
                                                {invoice.status.replace('_', ' ')}
                                            </Badge>
                                        </p>
                                    </div>
                                    <div className="flex shrink-0 items-center gap-2">
                                        <div className="text-right">
                                            <p className="font-medium tabular-nums">{currency.format(Number(invoice.grand_total))}</p>
                                            {Number(invoice.balance_amount) > 0 && (
                                                <p className="text-xs text-muted-foreground tabular-nums">
                                                    Due {currency.format(Number(invoice.balance_amount))}
                                                </p>
                                            )}
                                        </div>
                                        <Button variant="outline" size="icon" asChild title="Download PDF">
                                            <a href={`/portal/invoices/${invoice.id}/pdf`}>
                                                <Download className="size-4" />
                                            </a>
                                        </Button>
                                    </div>
                                </CardContent>
                            </Card>
                        ))}
                    </div>

                    {invoices.last_page > 1 && (
                        <nav className="flex flex-wrap items-center justify-center gap-1">
                            {invoices.links.map((link, i) => (
                                <Link
                                    key={i}
                                    href={link.url ?? '#'}
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

                    <p className="flex items-center justify-center gap-1.5 pt-4 text-xs text-muted-foreground">
                        <FileText className="size-3" />
                        Powered by Invoice CRM
                    </p>
                </main>
            </div>
        </>
    );
}
