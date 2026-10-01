import { Head, useForm } from '@inertiajs/react';
import { Download, Printer } from 'lucide-react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { buildUpiCollectUrl } from '@/lib/share-invoice';

const currency = new Intl.NumberFormat('en-IN', { style: 'currency', currency: 'INR', maximumFractionDigits: 2 });

type PublicInvoice = {
    invoice_number: string;
    invoice_date: string;
    due_date: string | null;
    status: string;
    subtotal: string;
    charges_summary: { label: string; amount: number }[] | null;
    discount: string;
    tax: string;
    tax_breakdown: { label: string; amount: number }[] | null;
    round_off: string;
    grand_total: string;
    paid_amount: string;
    balance_amount: string;
    notes: string | null;
    terms: string | null;
    customer: { full_name: string; mobile_number: string; email: string | null; address: string | null };
    items: {
        id: number;
        item_name: string;
        huid_number: string | null;
        hsn_code: string | null;
        metal_type: string | null;
        purity: string | null;
        net_weight: string;
        quantity: number;
        total: string;
    }[];
};

type Business = {
    business_name: string;
    logo_path: string | null;
    address: string | null;
    phone: string | null;
    email: string | null;
    website: string | null;
    tax_number: string | null;
    footer_text: string | null;
    upi_id: string | null;
};

type TemplateConfig = {
    accent_color: string;
    show_huid: boolean;
    show_hsn: boolean;
    footer_note: string | null;
};

export default function PublicInvoicePage({
    status,
    token,
    invoice,
    business,
    template,
}: {
    status: 'ok' | 'password_required' | 'unavailable';
    token?: string;
    invoice?: PublicInvoice;
    business?: Business;
    template?: TemplateConfig;
}) {
    if (status === 'unavailable') {
        return (
            <div className="flex min-h-screen items-center justify-center bg-muted/30 p-4">
                <Head title="Invoice unavailable" />
                <Card className="max-w-sm text-center">
                    <CardContent className="py-10">
                        <p className="font-medium">This link is no longer available.</p>
                        <p className="mt-1 text-sm text-muted-foreground">
                            It may have expired or been disabled. Please contact the business
                            that sent it to you for a new link.
                        </p>
                    </CardContent>
                </Card>
            </div>
        );
    }

    if (status === 'password_required' && token) {
        return <PasswordGate token={token} />;
    }

    if (!invoice || !business) return null;

    return (
        <div className="min-h-screen bg-muted/30 py-6 print:bg-white print:py-0">
            <Head title={invoice.invoice_number} />

            <div className="mx-auto max-w-2xl space-y-4 px-4">
                <div className="flex justify-end gap-2 print:hidden">
                    <Button variant="outline" onClick={() => window.print()}>
                        <Printer className="size-4" />
                        Print
                    </Button>
                    <Button asChild>
                        <a href={`/invoice/view/${token}/pdf`}>
                            <Download className="size-4" />
                            Download PDF
                        </a>
                    </Button>
                    {business.upi_id && Number(invoice.balance_amount) > 0 && (
                        <Button
                            asChild
                            className="bg-[#25D366] text-white hover:bg-[#1fb955]"
                        >
                            <a
                                href={
                                    buildUpiCollectUrl({
                                        upiId: business.upi_id,
                                        payeeName: business.business_name,
                                        amount: invoice.balance_amount,
                                        note: `Invoice ${invoice.invoice_number}`,
                                    }) ?? '#'
                                }
                            >
                                Pay via UPI
                            </a>
                        </Button>
                    )}
                </div>

                <Card className="print:border-none print:shadow-none">
                    <CardContent className="space-y-6 p-6">
                        <div className="flex items-start justify-between">
                            <div>
                                <h1 className="text-lg font-semibold" style={{ color: template?.accent_color }}>
                                    {business.business_name}
                                </h1>
                                <p className="text-sm whitespace-pre-line text-muted-foreground">
                                    {business.address}
                                </p>
                                {business.phone && (
                                    <p className="text-sm text-muted-foreground">{business.phone}</p>
                                )}
                            </div>
                            <div className="text-right">
                                <p className="font-semibold">{invoice.invoice_number}</p>
                                <p className="text-sm text-muted-foreground">
                                    {new Date(invoice.invoice_date).toLocaleDateString()}
                                </p>
                                <Badge variant="secondary" className="mt-1 capitalize">
                                    {invoice.status.replace('_', ' ')}
                                </Badge>
                            </div>
                        </div>

                        <div className="border-t pt-4">
                            <p className="text-xs text-muted-foreground">Billed to</p>
                            <p className="font-medium">{invoice.customer.full_name}</p>
                            <p className="text-sm text-muted-foreground">{invoice.customer.mobile_number}</p>
                        </div>

                        <div className="overflow-x-auto border-t pt-4">
                            <table className="w-full min-w-[420px] text-sm">
                                <thead className="text-left text-muted-foreground">
                                    <tr>
                                        <th className="pb-2 font-medium">Item</th>
                                        <th className="pb-2 text-right font-medium">Wt</th>
                                        <th className="pb-2 text-right font-medium">Qty</th>
                                        <th className="pb-2 text-right font-medium">Total</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y">
                                    {invoice.items.map((item) => (
                                        <tr key={item.id}>
                                            <td className="py-2">
                                                {item.item_name}
                                                <span className="block text-xs text-muted-foreground">
                                                    {item.metal_type} {item.purity}
                                                    {template?.show_huid && item.huid_number && ` · HUID ${item.huid_number}`}
                                                    {template?.show_hsn && item.hsn_code && ` · HSN ${item.hsn_code}`}
                                                </span>
                                            </td>
                                            <td className="py-2 text-right">{item.net_weight}g</td>
                                            <td className="py-2 text-right">{item.quantity}</td>
                                            <td className="py-2 text-right">{currency.format(Number(item.total))}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>

                        <div className="space-y-1 border-t pt-4 text-sm">
                            <Row label="Subtotal" value={invoice.subtotal} />
                            {(invoice.charges_summary ?? []).map((row) => (
                                <Row key={row.label} label={row.label} value={String(row.amount)} />
                            ))}
                            <Row label="Discount" value={`-${invoice.discount}`} />
                            {invoice.tax_breakdown && invoice.tax_breakdown.length > 0 ? (
                                invoice.tax_breakdown.map((row, i) => (
                                    <Row key={i} label={row.label} value={String(row.amount)} />
                                ))
                            ) : (
                                <Row label="Tax" value={invoice.tax} />
                            )}
                            <Row label="Grand total" value={invoice.grand_total} emphasize />
                            <Row label="Paid" value={invoice.paid_amount} />
                            <Row label="Balance due" value={invoice.balance_amount} emphasize />
                        </div>

                        {invoice.terms && (
                            <div className="border-t pt-4 text-xs text-muted-foreground">
                                {invoice.terms}
                            </div>
                        )}

                        {(business.footer_text || template?.footer_note) && (
                            <div className="border-t pt-4 text-center text-xs text-muted-foreground">
                                {business.footer_text}
                                {template?.footer_note && <div>{template.footer_note}</div>}
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </div>
    );
}

function Row({ label, value, emphasize }: { label: string; value: string; emphasize?: boolean }) {
    return (
        <div
            className={
                emphasize
                    ? 'flex justify-between border-t pt-2 font-semibold'
                    : 'flex justify-between text-muted-foreground'
            }
        >
            <span>{label}</span>
            <span>{currency.format(Number(value))}</span>
        </div>
    );
}

function PasswordGate({ token }: { token: string }) {
    const { data, setData, post, processing, errors } = useForm({ password: '' });

    function submit(e: FormEvent) {
        e.preventDefault();
        post(`/invoice/view/${token}/verify`);
    }

    return (
        <div className="flex min-h-screen items-center justify-center bg-muted/30 p-4">
            <Head title="Password required" />
            <Card className="w-full max-w-sm">
                <CardContent className="space-y-4 py-8">
                    <div className="text-center">
                        <p className="font-medium">This invoice is password-protected</p>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Enter the password shared with you to view it.
                        </p>
                    </div>
                    <form onSubmit={submit} className="space-y-3">
                        <div className="grid gap-2">
                            <Label htmlFor="password">Password</Label>
                            <Input
                                id="password"
                                type="password"
                                value={data.password}
                                onChange={(e) => setData('password', e.target.value)}
                                autoFocus
                            />
                            <InputError message={errors.password} />
                        </div>
                        <Button type="submit" className="w-full" disabled={processing}>
                            {processing ? 'Checking…' : 'View invoice'}
                        </Button>
                    </form>
                </CardContent>
            </Card>
        </div>
    );
}
