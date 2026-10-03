import { Head, Link, router } from '@inertiajs/react';
import { FileText, LogOut } from 'lucide-react';
import { AppLogo } from '@/components/app-logo';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import type { PortalQuotation } from '@/types/customer';

const currency = new Intl.NumberFormat('en-IN', { style: 'currency', currency: 'INR', maximumFractionDigits: 0 });

export default function PortalQuotations({
    customer,
    quotations,
}: {
    customer: { full_name: string; email: string | null; mobile_number: string };
    quotations: PortalQuotation[];
}) {
    return (
        <>
            <Head title="My quotations" />

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

                    <div className="space-y-2">
                        {quotations.length === 0 && (
                            <Card>
                                <CardContent className="py-10 text-center text-sm text-muted-foreground">
                                    No quotations yet.
                                </CardContent>
                            </Card>
                        )}
                        {quotations.map((quotation) => (
                            <Card key={quotation.id}>
                                <CardContent className="flex items-center justify-between gap-3 p-4">
                                    <div className="min-w-0">
                                        <Link
                                            href={`/invoice/view/${quotation.share_token}`}
                                            className="font-medium hover:underline"
                                        >
                                            {quotation.invoice_number}
                                        </Link>
                                        <p className="text-xs text-muted-foreground">
                                            {new Date(quotation.invoice_date).toLocaleDateString()}
                                            {' · '}
                                            <Badge variant="secondary" className="capitalize">
                                                {quotation.status_label}
                                            </Badge>
                                        </p>
                                    </div>
                                    <div className="flex shrink-0 items-center gap-2">
                                        <div className="text-right">
                                            <p className="font-medium tabular-nums">{currency.format(quotation.grand_total)}</p>
                                            {quotation.valid_until && (
                                                <p className="text-xs text-muted-foreground">
                                                    Valid until {new Date(quotation.valid_until).toLocaleDateString()}
                                                </p>
                                            )}
                                        </div>
                                        {quotation.can_decide && quotation.is_open && (
                                            <Button size="sm" asChild>
                                                <Link href={`/invoice/view/${quotation.share_token}`}>View</Link>
                                            </Button>
                                        )}
                                    </div>
                                </CardContent>
                            </Card>
                        ))}
                    </div>

                    <p className="flex items-center justify-center gap-1.5 pt-4 text-xs text-muted-foreground">
                        <FileText className="size-3" />
                        Powered by Invoice CRM
                    </p>
                </main>
            </div>
        </>
    );
}
