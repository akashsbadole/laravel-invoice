import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Download, Printer } from 'lucide-react';
import { AppLogo } from '@/components/app-logo';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { buildUpiCollectUrl } from '@/lib/share-invoice';
import type { Invoice } from '@/types/invoice';

const currency = new Intl.NumberFormat('en-IN', { style: 'currency', currency: 'INR', maximumFractionDigits: 2 });

export default function PortalInvoiceShow({
    invoice,
    business,
}: {
    invoice: Invoice;
    business: {
        business_name: string;
        address: string | null;
        phone: string | null;
        email: string | null;
        tax_number: string | null;
        upi_id: string | null;
    };
}) {
    const upiUrl =
        business.upi_id && Number(invoice.balance_amount) > 0
            ? buildUpiCollectUrl({
                upiId: business.upi_id,
                payeeName: business.business_name,
                amount: invoice.balance_amount,
                note: `Invoice ${invoice.invoice_number}`,
            })
            : null;
    return (
        <>
            <Head title={invoice.invoice_number} />

            <div className="min-h-screen bg-muted/30 py-6 print:bg-white print:py-0">
                <div className="mx-auto max-w-2xl space-y-4 px-4">
                    <div className="flex flex-wrap items-center justify-between gap-2 print:hidden">
                        <Button variant="ghost" size="sm" asChild>
                            <Link href="/portal">
                                <ArrowLeft className="size-4" />
                                All invoices
                            </Link>
                        </Button>
                        <div className="flex gap-2">
                            <Button variant="outline" size="sm" onClick={() => window.print()}>
                                <Printer className="size-4" />
                                Print
                            </Button>
                            <Button size="sm" asChild>
                                <a href={`/portal/invoices/${invoice.id}/pdf`}>
                                    <Download className="size-4" />
                                    Download PDF
                                </a>
                            </Button>
                            {upiUrl && (
                                <Button size="sm" asChild className="bg-[#25D366] text-white hover:bg-[#1fb955]">
                                    <a href={upiUrl}>Pay via UPI</a>
                                </Button>
                            )}
                        </div>
                    </div>

                    <Card className="print:border-none print:shadow-none">
                        <CardContent className="space-y-6 p-6">
                            <div className="flex items-start justify-between gap-4">
                                <div>
                                    <AppLogo />
                                    <p className="mt-2 text-xs text-muted-foreground">
                                        {business.address}
                                        {business.phone && <><br />{business.phone}</>}
                                        {business.tax_number && <><br />GSTIN: {business.tax_number}</>}
                                    </p>
                                </div>
                                <div className="text-right">
                                    <p className="font-display text-xl">{invoice.invoice_number}</p>
                                    <p className="text-xs text-muted-foreground">
                                        {new Date(invoice.invoice_date).toLocaleDateString()}
                                    </p>
                                    <Badge variant="secondary" className="mt-1 capitalize">
                                        {invoice.status.replace('_', ' ')}
                                    </Badge>
                                </div>
                            </div>

                            <div className="space-y-1 text-sm">
                                {invoice.items.map((item) => (
                                    <div key={item.id} className="flex justify-between gap-3 border-b py-2 last:border-0">
                                        <div className="min-w-0">
                                            <p className="font-medium">{item.item_name}</p>
                                            <p className="text-xs text-muted-foreground">
                                                {item.metal_type}
                                                {item.purity && ` / ${item.purity}`}
                                                {Number(item.net_weight) > 0 && ` Â· ${item.net_weight}g`}
                                                {` Â· Qty ${item.quantity}`}
                                            </p>
                                        </div>
                                        <p className="shrink-0 tabular-nums">{currency.format(Number(item.total))}</p>
                                    </div>
                                ))}
                            </div>

                            <div className="space-y-1 border-t pt-3 text-sm">
                                <div className="flex justify-between text-muted-foreground">
                                    <span>Subtotal</span>
                                    <span className="tabular-nums">{currency.format(Number(invoice.subtotal))}</span>
                                </div>
                                {(invoice.charges_summary ?? []).map((row, i) => (
                                    <div key={i} className="flex justify-between text-muted-foreground">
                                        <span>{row.label}</span>
                                        <span className="tabular-nums">{currency.format(Number(row.amount))}</span>
                                    </div>
                                ))}
                                <div className="flex justify-between font-semibold">
                                    <span>Total</span>
                                    <span className="tabular-nums">{currency.format(Number(invoice.grand_total))}</span>
                                </div>
                                <div className="flex justify-between text-muted-foreground">
                                    <span>Paid</span>
                                    <span className="tabular-nums">{currency.format(Number(invoice.paid_amount))}</span>
                                </div>
                                <div className="flex justify-between font-semibold text-brand-dark dark:text-brand-light">
                                    <span>Balance due</span>
                                    <span className="tabular-nums">{currency.format(Number(invoice.balance_amount))}</span>
                                </div>
                            </div>

                            {invoice.payments.length > 0 && (
                                <div className="text-sm">
                                    <p className="mb-1 font-medium">Payments</p>
                                    {invoice.payments.map((payment) => (
                                        <div key={payment.id} className="flex justify-between py-1 text-muted-foreground">
                                            <span>{new Date(payment.payment_date).toLocaleDateString()} Â· {String(payment.payment_method).replace('_', ' ')}</span>
                                            <span className="tabular-nums">{currency.format(Number(payment.amount))}</span>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </div>
            </div>
        </>
    );
}
