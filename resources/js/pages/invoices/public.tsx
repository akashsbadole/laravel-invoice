import { Head, useForm, usePage } from '@inertiajs/react';
import { Download, Printer } from 'lucide-react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
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
    tds_rate: string;
    tds_amount: string;
    tcs_rate: string;
    tcs_amount: string;
    round_off: string;
    grand_total: string;
    paid_amount: string;
    balance_amount: string;
    notes: string | null;
    terms: string | null;
    irn: string | null;
    irn_ack_no: string | null;
    eway_bill_no: string | null;
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

type QuotationState = {
    status: string;
    is_open: boolean;
    can_decide: boolean;
    response: string | null;
    updates: { label: string; detail: string | null; at: string }[];
};

export default function PublicInvoicePage({
    status,
    token,
    invoice,
    business,
    template,
    quotation,
}: {
    status: 'ok' | 'password_required' | 'unavailable';
    token?: string;
    invoice?: PublicInvoice;
    business?: Business;
    template?: TemplateConfig;
    quotation?: QuotationState | null;
}) {
    if (status === 'unavailable') {
        return (
            <div className="flex min-h-screen items-center justify-center bg-slate-50 dark:bg-slate-950 p-4">
                <Head title="Invoice unavailable" />
                <Card className="max-w-md text-center shadow-lg border-slate-200 dark:border-slate-800">
                    <CardContent className="py-12 px-6">
                        <div className="mx-auto size-12 rounded-full bg-amber-100 dark:bg-amber-950/50 flex items-center justify-center text-amber-600 mb-4">
                            <span className="text-xl font-bold">!</span>
                        </div>
                        <p className="font-semibold text-slate-900 dark:text-slate-100 text-lg">Quotation link expired or unavailable</p>
                        <p className="mt-2 text-sm text-slate-500 dark:text-slate-400">
                            This quotation link may have expired or been deactivated by the seller. Please reach out to the shop owner to request an updated quotation.
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

    const isQuotation = quotation !== null && quotation !== undefined;

    return (
        <div className="min-h-screen bg-slate-50/80 dark:bg-slate-950 py-8 px-4 print:bg-white print:py-0">
            <Head title={`${business.business_name} — ${invoice.invoice_number}`} />

            <div className="mx-auto max-w-3xl space-y-6">
                {/* Header Action Bar */}
                <div className="flex flex-wrap items-center justify-between gap-3 rounded-xl bg-white dark:bg-slate-900 p-4 shadow-sm border border-slate-200/80 dark:border-slate-800 print:hidden">
                    <div className="flex items-center gap-2">
                        <Badge variant="outline" className="font-mono text-xs px-2.5 py-1">
                            {invoice.invoice_number}
                        </Badge>
                        {isQuotation && (
                            <Badge className={
                                quotation.status === 'accepted'
                                    ? 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-400 hover:bg-emerald-500/20 border-emerald-200 dark:border-emerald-800'
                                    : quotation.status === 'rejected'
                                        ? 'bg-rose-500/15 text-rose-700 dark:text-rose-400 border-rose-200'
                                        : 'bg-indigo-500/15 text-indigo-700 dark:text-indigo-400 border-indigo-200'
                            }>
                                Quotation · {quotation.status}
                            </Badge>
                        )}
                    </div>

                    <div className="flex flex-wrap items-center gap-2">
                        <Button
                            asChild
                            size="sm"
                            variant="outline"
                            className="bg-[#25D366] text-white hover:bg-[#1fb955] border-none shadow-sm"
                        >
                            <a
                                href={`https://wa.me/?text=${encodeURIComponent(
                                    `Hello ${business.business_name}, I am viewing quotation ${invoice.invoice_number} (${currency.format(Number(invoice.grand_total))}): ${window.location.href}`,
                                )}`}
                                target="_blank"
                                rel="noopener noreferrer"
                            >
                                WhatsApp
                            </a>
                        </Button>
                        <Button size="sm" variant="outline" onClick={() => window.print()}>
                            <Printer className="size-4" />
                            Print
                        </Button>
                        <Button size="sm" variant="outline" asChild>
                            <a href={`/invoice/view/${token}/pdf`}>
                                <Download className="size-4" />
                                PDF
                            </a>
                        </Button>
                        {business.upi_id && Number(invoice.balance_amount) > 0 && (
                            <Button
                                size="sm"
                                asChild
                                className="bg-emerald-600 hover:bg-emerald-700 text-white shadow-sm"
                            >
                                <a
                                    href={
                                        buildUpiCollectUrl({
                                            upiId: business.upi_id,
                                            payeeName: business.business_name,
                                            amount: invoice.balance_amount,
                                            note: `Deposit ${invoice.invoice_number}`,
                                        }) ?? '#'
                                    }
                                >
                                    Pay Deposit via UPI
                                </a>
                            </Button>
                        )}
                    </div>
                </div>

                {/* Hero Price & Acceptance Section for Quotations */}
                {isQuotation && (
                    <QuotationPanel token={token!} quotation={quotation} invoice={invoice} business={business} />
                )}

                {/* Main Invoice Card */}
                <Card className="shadow-md border-slate-200/80 dark:border-slate-800 print:border-none print:shadow-none bg-white dark:bg-slate-900">
                    <CardContent className="space-y-6 p-6 sm:p-8">
                        {/* Business Branding & Quote Info */}
                        <div className="flex flex-col sm:flex-row sm:items-start justify-between gap-4 border-b border-slate-100 dark:border-slate-800 pb-6">
                            <div>
                                {business.logo_path && (
                                    <img
                                        src={`/storage/${business.logo_path}`}
                                        alt={business.business_name}
                                        className="h-12 w-auto object-contain mb-2"
                                    />
                                )}
                                <h1
                                    className="text-xl font-bold tracking-tight text-slate-900 dark:text-slate-100"
                                    style={{ color: template?.accent_color || undefined }}
                                >
                                    {business.business_name}
                                </h1>
                                {business.address && (
                                    <p className="text-sm whitespace-pre-line text-slate-500 dark:text-slate-400 mt-1">
                                        {business.address}
                                    </p>
                                )}
                                <div className="flex flex-wrap items-center gap-3 text-xs text-slate-500 dark:text-slate-400 mt-2">
                                    {business.phone && <span>Ph: {business.phone}</span>}
                                    {business.email && <span>Email: {business.email}</span>}
                                    {business.tax_number && <span>GSTIN: {business.tax_number}</span>}
                                </div>
                            </div>
                            <div className="text-left sm:text-right bg-slate-50 dark:bg-slate-800/50 p-4 rounded-xl border border-slate-100 dark:border-slate-800/80">
                                <p className="text-xs uppercase font-semibold text-slate-400 tracking-wider">
                                    {isQuotation ? 'Quotation' : 'Invoice'}
                                </p>
                                <p className="font-mono text-lg font-bold text-slate-900 dark:text-slate-100 mt-0.5">
                                    {invoice.invoice_number}
                                </p>
                                <p className="text-xs text-slate-500 dark:text-slate-400 mt-1">
                                    Date: {new Date(invoice.invoice_date).toLocaleDateString('en-IN', { day: 'numeric', month: 'short', year: 'numeric' })}
                                </p>
                                {invoice.due_date && (
                                    <p className="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                                        Valid / Due: {new Date(invoice.due_date).toLocaleDateString('en-IN', { day: 'numeric', month: 'short', year: 'numeric' })}
                                    </p>
                                )}
                            </div>
                        </div>

                        {/* Customer Info */}
                        <div className="rounded-lg bg-slate-50/70 dark:bg-slate-800/40 p-4 border border-slate-100 dark:border-slate-800">
                            <p className="text-xs font-semibold uppercase text-slate-400 tracking-wider">Quotation For</p>
                            <p className="font-semibold text-slate-900 dark:text-slate-100 text-base mt-0.5">{invoice.customer.full_name}</p>
                            <div className="flex flex-wrap items-center gap-3 text-xs text-slate-500 dark:text-slate-400 mt-1">
                                <span>Phone: {invoice.customer.mobile_number}</span>
                                {invoice.customer.email && <span>Email: {invoice.customer.email}</span>}
                                {invoice.customer.address && <span>Address: {invoice.customer.address}</span>}
                            </div>
                        </div>

                        {/* Line Items Table */}
                        <div className="overflow-x-auto">
                            <table className="w-full min-w-[480px] text-sm">
                                <thead>
                                    <tr className="border-b border-slate-200 dark:border-slate-800 text-xs uppercase font-semibold text-slate-500 dark:text-slate-400">
                                        <th className="pb-3 text-left">Line Item</th>
                                        <th className="pb-3 text-right">Spec / Wt</th>
                                        <th className="pb-3 text-right">Qty</th>
                                        <th className="pb-3 text-right">Amount</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100 dark:divide-slate-800/60">
                                    {invoice.items.map((item) => (
                                        <tr key={item.id} className="hover:bg-slate-50/50 dark:hover:bg-slate-800/30">
                                            <td className="py-3 pr-2">
                                                <p className="font-medium text-slate-900 dark:text-slate-100">{item.item_name}</p>
                                                <div className="flex flex-wrap items-center gap-1.5 text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                                                    {item.metal_type && (
                                                        <span className="inline-flex items-center rounded bg-amber-50 dark:bg-amber-950/40 px-1.5 py-0.5 text-[11px] font-medium text-amber-700 dark:text-amber-300 border border-amber-200/60 dark:border-amber-900/40">
                                                            {item.metal_type} {item.purity}
                                                        </span>
                                                    )}
                                                    {template?.show_huid && item.huid_number && (
                                                        <span className="font-mono text-[11px] bg-slate-100 dark:bg-slate-800 px-1.5 py-0.5 rounded">
                                                            HUID: {item.huid_number}
                                                        </span>
                                                    )}
                                                    {template?.show_hsn && item.hsn_code && (
                                                        <span className="font-mono text-[11px] text-slate-400">
                                                            HSN {item.hsn_code}
                                                        </span>
                                                    )}
                                                </div>
                                            </td>
                                            <td className="py-3 text-right text-slate-600 dark:text-slate-300 font-mono text-xs">
                                                {Number(item.net_weight) > 0 ? `${item.net_weight}g` : '—'}
                                            </td>
                                            <td className="py-3 text-right text-slate-600 dark:text-slate-300 font-mono">
                                                {item.quantity}
                                            </td>
                                            <td className="py-3 text-right font-semibold text-slate-900 dark:text-slate-100 font-mono">
                                                {currency.format(Number(item.total))}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>

                        {/* Summary & Totals */}
                        <div className="border-t border-slate-200 dark:border-slate-800 pt-4 space-y-2 text-sm max-w-sm ml-auto">
                            <Row label="Subtotal" value={invoice.subtotal} />
                            {(invoice.charges_summary ?? []).map((row) => (
                                <Row key={row.label} label={row.label} value={String(row.amount)} />
                            ))}
                            {Number(invoice.discount) > 0 && (
                                <Row label="Discount" value={`-${invoice.discount}`} className="text-emerald-600 dark:text-emerald-400" />
                            )}
                            {invoice.tax_breakdown && invoice.tax_breakdown.length > 0 ? (
                                invoice.tax_breakdown.map((row, i) => (
                                    <Row key={i} label={row.label} value={String(row.amount)} />
                                ))
                            ) : (
                                Number(invoice.tax) > 0 && <Row label="Tax" value={invoice.tax} />
                            )}
                            {Number(invoice.tcs_amount) > 0 && (
                                <Row label={`TCS @ ${invoice.tcs_rate}%`} value={invoice.tcs_amount} />
                            )}
                            {Number(invoice.round_off) !== 0 && <Row label="Round off" value={invoice.round_off} />}

                            <div className="border-t border-slate-200 dark:border-slate-800 pt-3 mt-3 flex justify-between items-center">
                                <span className="font-bold text-base text-slate-900 dark:text-slate-100">Grand Total</span>
                                <span className="font-bold text-xl text-indigo-600 dark:text-indigo-400 font-mono">
                                    {currency.format(Number(invoice.grand_total))}
                                </span>
                            </div>
                        </div>

                        {/* Terms & Footer */}
                        {invoice.terms && (
                            <div className="border-t border-slate-100 dark:border-slate-800 pt-4">
                                <p className="text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1">Terms & Conditions</p>
                                <p className="text-xs text-slate-500 dark:text-slate-400 leading-relaxed whitespace-pre-line">{invoice.terms}</p>
                            </div>
                        )}

                        {(business.footer_text || template?.footer_note) && (
                            <div className="border-t border-slate-100 dark:border-slate-800 pt-4 text-center text-xs text-slate-400">
                                {business.footer_text}
                                {template?.footer_note && <div className="mt-1 font-medium">{template.footer_note}</div>}
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </div>
    );
}

/**
 * High-converting customer decision widget with instant acceptance, note submission, and UPI deposit option.
 */
function QuotationPanel({
    token,
    quotation,
    invoice,
    business,
}: {
    token: string;
    quotation: QuotationState;
    invoice: PublicInvoice;
    business: Business;
}) {
    const { data, setData, post, processing, errors, wasSuccessful } = useForm({
        decision: 'accepted',
        response: '',
        name: '',
    });

    const { flash } = usePage<{ flash: { message?: string } }>().props;
    const accepted = quotation.status === 'accepted';
    const rejected = quotation.status === 'rejected';

    return (
        <Card className="print:hidden border-indigo-200 dark:border-indigo-900/60 bg-gradient-to-br from-indigo-50/60 via-white to-purple-50/40 dark:from-indigo-950/30 dark:via-slate-900 dark:to-purple-950/20 shadow-md">
            <CardContent className="space-y-4 p-6">
                <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-indigo-100 dark:border-indigo-900/40 pb-4">
                    <div>
                        <h2 className="text-base font-bold text-slate-900 dark:text-slate-100">
                            Quotation Decision &amp; Acceptance
                        </h2>
                        <p className="text-xs text-slate-500 dark:text-slate-400">
                            Review this quotation and accept directly to confirm your order with {business.business_name}.
                        </p>
                    </div>

                    <div className="text-right">
                        <span className="text-xs text-slate-400 font-medium">Quote Value</span>
                        <p className="text-xl font-bold font-mono text-indigo-600 dark:text-indigo-400">
                            {currency.format(Number(invoice.grand_total))}
                        </p>
                    </div>
                </div>

                {quotation.can_decide && !wasSuccessful && (
                    <div className="space-y-4">
                        {flash.message && (
                            <div className="rounded-lg bg-emerald-50 dark:bg-emerald-950/50 p-3 border border-emerald-200 dark:border-emerald-800 text-sm text-emerald-800 dark:text-emerald-300 font-medium flex items-center gap-2">
                                <span className="size-2 rounded-full bg-emerald-500 animate-pulse"></span>
                                {flash.message}
                            </div>
                        )}

                        <div className="grid gap-3 sm:grid-cols-2">
                            <div className="grid gap-1.5">
                                <Label htmlFor="decision" className="text-xs font-semibold text-slate-700 dark:text-slate-300">
                                    Your Decision
                                </Label>
                                <Select
                                    value={data.decision}
                                    onValueChange={(value) => setData('decision', value)}
                                >
                                    <SelectTrigger id="decision" className="bg-white dark:bg-slate-900">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="accepted">
                                            ✓ Accept Quotation
                                        </SelectItem>
                                        <SelectItem value="rejected">
                                            Request Changes / Discuss
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                            <div className="grid gap-1.5">
                                <Label htmlFor="name" className="text-xs font-semibold text-slate-700 dark:text-slate-300">
                                    Your Name (optional)
                                </Label>
                                <Input
                                    id="name"
                                    placeholder="e.g. Rahul Sharma"
                                    value={data.name}
                                    onChange={(e) => setData('name', e.target.value)}
                                    className="bg-white dark:bg-slate-900"
                                />
                            </div>
                        </div>

                        <div className="grid gap-1.5">
                            <Label htmlFor="response" className="text-xs font-semibold text-slate-700 dark:text-slate-300">
                                Message or Order Note (optional)
                            </Label>
                            <Textarea
                                id="response"
                                rows={2}
                                value={data.response}
                                onChange={(e) => setData('response', e.target.value)}
                                placeholder="Any preferred delivery date or specifications?"
                                className="bg-white dark:bg-slate-900"
                            />
                            <InputError message={errors.response} />
                            <InputError message={errors.decision} />
                        </div>

                        <div className="flex flex-wrap items-center gap-3 pt-2">
                            <Button
                                disabled={processing}
                                size="lg"
                                className={
                                    data.decision === 'accepted'
                                        ? 'bg-emerald-600 hover:bg-emerald-700 text-white font-semibold shadow-md px-6'
                                        : 'bg-indigo-600 hover:bg-indigo-700 text-white font-semibold shadow-md px-6'
                                }
                                onClick={() => post(`/invoice/view/${token}/decide`)}
                            >
                                {processing ? 'Submitting…' : data.decision === 'accepted' ? 'Confirm & Accept Quotation' : 'Submit Feedback'}
                            </Button>

                            {business.upi_id && data.decision === 'accepted' && (
                                <Button
                                    asChild
                                    variant="outline"
                                    size="lg"
                                    className="border-emerald-300 text-emerald-700 hover:bg-emerald-50 dark:border-emerald-800 dark:text-emerald-400 font-semibold"
                                >
                                    <a
                                        href={
                                            buildUpiCollectUrl({
                                                upiId: business.upi_id,
                                                payeeName: business.business_name,
                                                amount: invoice.balance_amount,
                                                note: `Deposit ${invoice.invoice_number}`,
                                            }) ?? '#'
                                        }
                                    >
                                        Pay Deposit via UPI
                                    </a>
                                </Button>
                            )}
                        </div>
                    </div>
                )}

                {(accepted || rejected) && (
                    <div className={
                        accepted
                            ? 'rounded-xl bg-emerald-500/10 border border-emerald-300 dark:border-emerald-900 p-4 text-emerald-900 dark:text-emerald-200'
                            : 'rounded-xl bg-slate-100 dark:bg-slate-800 p-4 text-slate-800 dark:text-slate-200'
                    }>
                        <div className="flex items-center gap-2 font-bold text-sm">
                            <span>{accepted ? '✓ Quotation Accepted' : 'Note Recorded'}</span>
                        </div>
                        <p className="text-xs mt-1 text-slate-600 dark:text-slate-300">
                            {accepted
                                ? 'Thank you! Your acceptance has been submitted to the seller. They will confirm your order and generate the final invoice.'
                                : 'Your feedback was sent to the shop owner. They will review your notes and respond shortly.'}
                        </p>
                    </div>
                )}

                {quotation.updates.length > 0 && (
                    <div className="border-t border-indigo-100 dark:border-indigo-900/40 pt-3">
                        <p className="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-2">Quotation Activity History</p>
                        <ol className="space-y-1.5 text-xs text-slate-600 dark:text-slate-400">
                            {quotation.updates.map((update, i) => (
                                <li key={i} className="flex items-start gap-2">
                                    <span className="size-1.5 rounded-full bg-indigo-500 mt-1.5 shrink-0"></span>
                                    <div>
                                        <span className="font-semibold text-slate-800 dark:text-slate-200">{update.label}</span>
                                        <span className="text-slate-400 ml-1.5">
                                            · {new Date(update.at).toLocaleString('en-IN', { dateStyle: 'short', timeStyle: 'short' })}
                                        </span>
                                        {update.detail && (
                                            <p className="text-slate-500 dark:text-slate-400 italic mt-0.5">{update.detail}</p>
                                        )}
                                    </div>
                                </li>
                            ))}
                        </ol>
                    </div>
                )}
            </CardContent>
        </Card>
    );
}

function Row({ label, value, emphasize, className }: { label: string; value: string; emphasize?: boolean; className?: string }) {
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
