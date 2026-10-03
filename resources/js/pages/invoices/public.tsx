import { Head, useForm, usePage } from '@inertiajs/react';
import { Download, Printer } from 'lucide-react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { buildUpiCollectUrl, buildWhatsAppShareUrl } from '@/lib/share-invoice';
import { cn } from '@/lib/utils';

const currency = new Intl.NumberFormat('en-IN', { style: 'currency', currency: 'INR', maximumFractionDigits: 2 });

type PublicInvoice = {
    invoice_number: string;
    invoice_date: string;
    due_date: string | null;
    rate_locked_at: string | null;
    revision_number: number;
    revision_note: string | null;
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
            <div className="flex min-h-screen items-center justify-center bg-white dark:bg-slate-950 p-4">
                <Head title="Invoice unavailable" />
                <Card className="max-w-md text-center shadow-2xl shadow-blue-300/50 dark:shadow-blue-950/50 border-0 overflow-hidden">
                    <div className="bg-blue-600 p-6">
                        <div className="mx-auto size-16 rounded-full bg-white/20 backdrop-blur-sm flex items-center justify-center text-white text-2xl font-bold shadow-lg">
                            !
                        </div>
                    </div>
                    <CardContent className="py-8 px-6">
                        <h2 className="text-xl font-bold text-slate-900 dark:text-slate-100">Quotation link expired or unavailable</h2>
                        <p className="mt-3 text-sm text-slate-500 dark:text-slate-400 leading-relaxed">
                            This quotation link may have expired or been deactivated by the seller. Please reach out to the shop owner to request an updated quotation.
                        </p>
                        <Button asChild className="mt-6 bg-blue-600 hover:bg-blue-700 text-white shadow-lg">
                            <a href="/">Go to homepage</a>
                        </Button>
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
        <div className="min-h-screen bg-white dark:bg-slate-950 py-8 px-4 print:bg-white print:py-0">
            <Head title={`${business.business_name} — ${invoice.invoice_number}`} />

            <div className="mx-auto max-w-3xl space-y-6">
                {/* Header Action Bar */}
                <div className="flex flex-wrap items-center justify-between gap-3 rounded-2xl bg-white dark:bg-slate-900 p-5 shadow-lg shadow-blue-200/50 dark:shadow-blue-950/50 border border-blue-200 dark:border-blue-800 print:hidden">
                    <div className="flex items-center gap-3">
                        <Badge variant="outline" className="font-mono text-xs px-3 py-1.5 border-blue-300 dark:border-blue-700 text-blue-800 dark:text-blue-200">
                            {invoice.invoice_number}
                        </Badge>
                        {isQuotation && (
                            <Badge className={
                                quotation.status === 'accepted'
                                    ? 'bg-blue-500/15 text-blue-700 dark:text-blue-300 hover:bg-blue-500/20 border border-blue-200 dark:border-blue-800 shadow-sm'
                                    : quotation.status === 'rejected'
                                        ? 'bg-blue-950/10 text-slate-700 dark:text-slate-300 border border-blue-200 dark:border-blue-800 shadow-sm'
                                        : 'bg-blue-500/10 text-blue-700 dark:text-blue-300 border border-blue-200 dark:border-blue-800 shadow-sm'
                            }>
                                Quotation · {quotation.status}
                                {invoice.revision_number > 1 ? ` · Rev ${invoice.revision_number}` : ''}
                            </Badge>
                        )}
                    </div>

                    <div className="flex flex-wrap items-center gap-2">
                        <Button
                            asChild
                            size="sm"
                            variant="outline"
                            className="bg-[#25D366] text-white hover:bg-[#1fb955] border-none shadow-md hover:shadow-lg transition-all"
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
                        <Button size="sm" variant="outline" onClick={() => window.print()} className="hover:bg-blue-50 dark:hover:bg-blue-950/30 transition-colors border-blue-200 dark:border-blue-800">
                            <Printer className="size-4" />
                            Print
                        </Button>
                        <Button size="sm" variant="outline" asChild className="hover:bg-blue-50 dark:hover:bg-blue-950/30 transition-colors border-blue-200 dark:border-blue-800">
                            <a href={`/invoice/view/${token}/pdf`}>
                                <Download className="size-4" />
                                PDF
                            </a>
                        </Button>
                        {business.upi_id && Number(invoice.balance_amount) > 0 && (
                            <Button
                                size="sm"
                                asChild
                                className="bg-blue-600 hover:bg-blue-700 text-white shadow-md hover:shadow-lg transition-all"
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
                <Card className="shadow-xl shadow-blue-300/40 dark:shadow-blue-950/40 border-0 bg-white dark:bg-slate-900 print:border-none print:shadow-none">
                    <CardContent className="space-y-6 p-6 sm:p-8">
                        {/* Business Branding & Quote Info */}
                        <div className="flex flex-col sm:flex-row sm:items-start justify-between gap-4 border-b border-blue-100 dark:border-blue-900/60 pb-6">
                            <div>
                                {business.logo_path && (
                                    <img
                                        src={`/storage/${business.logo_path}`}
                                        alt={business.business_name}
                                        className="h-14 w-auto object-contain mb-3 drop-shadow-sm"
                                    />
                                )}
                                <h1
                                    className="text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100"
                                    style={{ color: '#4f46e5' }}
                                >
                                    {business.business_name}
                                </h1>
                                {business.address && (
                                    <p className="text-sm whitespace-pre-line text-slate-500 dark:text-slate-400 mt-1.5 leading-relaxed">
                                        {business.address}
                                    </p>
                                )}
                                <div className="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-slate-500 dark:text-slate-400 mt-2.5">
                                    {business.phone && <span className="flex items-center gap-1"><span className="font-medium">Ph:</span> {business.phone}</span>}
                                    {business.email && <span className="flex items-center gap-1"><span className="font-medium">Email:</span> {business.email}</span>}
                                    {business.tax_number && <span className="flex items-center gap-1"><span className="font-medium">GSTIN:</span> {business.tax_number}</span>}
                                </div>
                            </div>
                            <div className="text-left sm:text-right bg-blue-50 dark:bg-blue-950/40 p-5 rounded-2xl border border-blue-100 dark:border-blue-900/60 shadow-sm">
                                <p className="text-xs uppercase font-semibold text-blue-600 dark:text-blue-300 tracking-wider mb-1">
                                    {isQuotation ? 'Quotation' : 'Invoice'}
                                </p>
                                <p className="font-mono text-xl font-bold text-slate-900 dark:text-slate-100 mt-0.5">
                                    {invoice.invoice_number}
                                </p>
                                <div className="mt-2 space-y-1">
                                    <p className="text-xs text-slate-500 dark:text-slate-400">
                                        <span className="font-medium">Date:</span> {new Date(invoice.invoice_date).toLocaleDateString('en-IN', { day: 'numeric', month: 'short', year: 'numeric' })}
                                    </p>
                                    {invoice.due_date && (
                                        <p className="text-xs text-slate-500 dark:text-slate-400">
                                            <span className="font-medium">Valid / Due:</span> {new Date(invoice.due_date).toLocaleDateString('en-IN', { day: 'numeric', month: 'short', year: 'numeric' })}
                                        </p>
                                    )}
                                    {invoice.rate_locked_at && (
                                        <p className="text-xs text-slate-500 dark:text-slate-400">
                                            <span className="font-medium">Metal rate as on:</span> {new Date(invoice.rate_locked_at).toLocaleDateString('en-IN', { day: 'numeric', month: 'short', year: 'numeric' })}
                                        </p>
                                    )}
                                </div>
                            </div>
                        </div>

                        {/* Customer Info */}
                        <div className="rounded-xl bg-blue-50/70 dark:bg-blue-950/30 p-5 border border-blue-100 dark:border-blue-900/60">
                            <p className="text-xs font-semibold uppercase text-blue-700 dark:text-blue-300 tracking-wider mb-1.5">Quotation For</p>
                            <p className="font-bold text-slate-900 dark:text-slate-100 text-lg">{invoice.customer.full_name}</p>
                            <div className="flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-slate-600 dark:text-slate-300 mt-1.5">
                                <span className="flex items-center gap-1.5">
                                    <span className="text-xs font-medium text-slate-400">Phone:</span> {invoice.customer.mobile_number}
                                </span>
                                {invoice.customer.email && (
                                    <span className="flex items-center gap-1.5">
                                        <span className="text-xs font-medium text-slate-400">Email:</span> {invoice.customer.email}
                                    </span>
                                )}
                                {invoice.customer.address && (
                                    <span className="flex items-center gap-1.5">
                                        <span className="text-xs font-medium text-slate-400">Address:</span> {invoice.customer.address}
                                    </span>
                                )}
                            </div>
                        </div>

                        {/* Line Items Table */}
                        <div className="overflow-x-auto rounded-xl border border-slate-200 dark:border-slate-700">
                            <table className="w-full min-w-[480px] text-sm">
                                <thead>
                                    <tr className="border-b border-slate-200 dark:border-slate-700 bg-slate-50/80 dark:bg-slate-800/80 text-xs uppercase font-semibold text-slate-600 dark:text-slate-300">
                                        <th className="pb-3.5 pl-4 text-left font-semibold">Line Item</th>
                                        <th className="pb-3.5 text-right font-semibold">Spec / Wt</th>
                                        <th className="pb-3.5 text-right font-semibold">Qty</th>
                                        <th className="pb-3.5 pr-4 text-right font-semibold">Amount</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100 dark:divide-slate-800/60">
                                    {invoice.items.map((item) => (
                                        <tr key={item.id} className="hover:bg-slate-50/70 dark:hover:bg-slate-800/40 transition-colors">
                                            <td className="py-3.5 pr-2 pl-4">
                                                <p className="font-semibold text-slate-900 dark:text-slate-100">{item.item_name}</p>
                                                <div className="flex flex-wrap items-center gap-1.5 text-xs text-slate-500 dark:text-slate-400 mt-1">
                                                    {item.metal_type && (
                                                        <span className="inline-flex items-center rounded-md bg-amber-50 dark:bg-amber-950/40 px-2 py-0.5 text-[11px] font-semibold text-amber-700 dark:text-amber-300 border border-amber-200/60 dark:border-amber-900/40">
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
                                            <td className="py-3.5 text-right text-slate-600 dark:text-slate-300 font-mono text-xs">
                                                {Number(item.net_weight) > 0 ? `${item.net_weight}g` : '—'}
                                            </td>
                                            <td className="py-3.5 text-right text-slate-600 dark:text-slate-300 font-mono font-medium">
                                                {item.quantity}
                                            </td>
                                            <td className="py-3.5 pr-4 text-right font-bold text-slate-900 dark:text-slate-100 font-mono">
                                                {currency.format(Number(item.total))}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>

                        {/* Summary & Totals */}
                        <div className="border-t border-slate-200 dark:border-slate-700 pt-4 space-y-2 text-sm max-w-sm ml-auto">
                            <Row label="Subtotal" value={invoice.subtotal} />
                            {(invoice.charges_summary ?? []).map((row) => (
                                <Row key={row.label} label={row.label} value={String(row.amount)} />
                            ))}
                            {Number(invoice.discount) > 0 && (
                                <Row label="Discount" value={`-${invoice.discount}`} className="text-emerald-600 dark:text-emerald-400 font-medium" />
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

                            <div className="border-t-2 border-blue-200 dark:border-blue-800 pt-3 mt-3 flex justify-between items-center bg-blue-50/60 dark:bg-blue-950/30 -mx-2 px-2 py-2 rounded-lg">
                                <span className="font-bold text-base text-slate-900 dark:text-slate-100">Grand Total</span>
                                <span className="font-bold text-2xl text-blue-700 dark:text-blue-300 font-mono tracking-tight">
                                    {currency.format(Number(invoice.grand_total))}
                                </span>
                            </div>
                        </div>

                        {/* Terms & Footer */}
                        {invoice.terms && (
                            <div className="border-t border-slate-100 dark:border-slate-800 pt-4">
                                <p className="text-xs font-semibold text-slate-400 uppercase tracking-wider mb-2">Terms & Conditions</p>
                                <p className="text-xs text-slate-500 dark:text-slate-400 leading-relaxed whitespace-pre-line">{invoice.terms}</p>
                            </div>
                        )}

                        {(business.footer_text || template?.footer_note) && (
                            <div className="border-t border-slate-100 dark:border-slate-800 pt-4 text-center space-y-1">
                                {business.footer_text && (
                                    <p className="text-xs text-slate-400">{business.footer_text}</p>
                                )}
                                {template?.footer_note && (
                                    <p className="text-xs font-medium text-indigo-600 dark:text-indigo-400">{template.footer_note}</p>
                                )}
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
    const decide = useForm({ decision: 'accepted', response: '', name: '' });
    const changes = useForm({ response: '', name: '' });

    const { flash } = usePage<{ flash: { message?: string } }>().props;
    const accepted = quotation.status === 'accepted';
    const rejected = quotation.status === 'rejected';

    function submitDecision(value: 'accepted' | 'rejected') {
        decide.setData('decision', value);
        decide.post(`/invoice/view/${token}/decide`);
    }

    /** Negotiation continues by WhatsApp first, but we record it too. */
    function askForChanges() {
        const message = changes.data.response.trim();
        if (!message) {
            changes.setError('response', 'Please tell the shop what you would like to change.');
            return;
        }
        if (business.phone) {
            const waUrl = buildWhatsAppShareUrl(
                business.phone,
                `Hi ${business.business_name}, I'm reviewing quotation ${invoice.invoice_number} and would like a few changes:\n\n${message}`,
            );
            window.open(waUrl, '_blank');
        }
        changes.post(`/invoice/view/${token}/changes`);
    }

    return (
        <Card className="print:hidden border-0 bg-gradient-to-br from-blue-700 via-blue-600 to-indigo-700 dark:from-blue-800 dark:via-blue-700 dark:to-indigo-800 shadow-2xl shadow-blue-600/30 dark:shadow-blue-900/50 overflow-hidden">
            <CardContent className="space-y-5 p-6 sm:p-8 relative">
                {/* Decorative background elements */}
                <div className="absolute inset-0 opacity-10 pointer-events-none">
                    <div className="absolute -top-24 -right-24 w-96 h-96 rounded-full bg-white blur-3xl" />
                    <div className="absolute -bottom-24 -left-24 w-96 h-96 rounded-full bg-indigo-300 blur-3xl" />
                </div>

                <div className="relative flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-5 border-b border-white/20">
                    <div>
                        <h2 className="text-xl font-bold text-white">Quotation Decision & Acceptance</h2>
                        <p className="text-sm text-blue-100 mt-1">
                            Review this quotation and accept directly to confirm your order with {business.business_name}.
                        </p>
                    </div>

                    <div className="text-left sm:text-right bg-white/10 backdrop-blur-sm rounded-xl px-5 py-3 border border-white/20">
                        <span className="text-xs text-blue-100 font-medium uppercase tracking-wider">Quote Value</span>
                        <p className="text-3xl font-bold font-mono text-white mt-0.5 tracking-tight">
                            {currency.format(Number(invoice.grand_total))}
                        </p>
                    </div>
                </div>

                {quotation.can_decide && !decide.wasSuccessful && !changes.wasSuccessful && (
                    <div className="space-y-5 relative">
                        {flash.message && (
                            <div className="rounded-xl bg-white/15 backdrop-blur-md p-4 border border-white/25 text-sm text-white font-medium flex items-center gap-3 shadow-lg">
                                <span className="size-2.5 rounded-full bg-emerald-400 animate-pulse shadow-sm shadow-emerald-400"></span>
                                {flash.message}
                            </div>
                        )}

                        <div className="grid gap-4 sm:grid-cols-2">
                            <div className="grid gap-2">
                                <Label htmlFor="name" className="text-xs font-bold text-blue-100 uppercase tracking-wider">
                                    Your Name
                                </Label>
                                <Input
                                    id="name"
                                    placeholder="e.g. Rahul Sharma"
                                    value={decide.data.name}
                                    onChange={(e) => {
                                        decide.setData('name', e.target.value);
                                        changes.setData('name', e.target.value);
                                    }}
                                    className="bg-white/95 dark:bg-slate-900/95 border-blue-200 dark:border-blue-600 text-slate-900 dark:text-slate-100 placeholder:text-slate-400"
                                />
                            </div>
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="response" className="text-xs font-bold text-blue-100 uppercase tracking-wider">
                                Message or order note
                            </Label>
                            <Textarea
                                id="response"
                                rows={3}
                                value={changes.data.response}
                                onChange={(e) => {
                                    decide.setData('response', e.target.value);
                                    changes.setData('response', e.target.value);
                                }}
                                placeholder="Delivery timeline, or what you would like changed..."
                                className="bg-white/95 dark:bg-slate-900/95 border-blue-200 dark:border-blue-600 text-slate-900 dark:text-slate-100 placeholder:text-slate-400 resize-none"
                            />
                            <InputError message={decide.errors.response} />
                            <InputError message={changes.errors.response} />
                            <InputError message={decide.errors.decision} />
                        </div>

                        <div className="flex flex-wrap items-center gap-3 pt-2">
                            <Button
                                disabled={decide.processing}
                                size="lg"
                                className="bg-blue-600 hover:bg-blue-700 text-white font-bold shadow-xl hover:shadow-2xl transition-all"
                                onClick={() => submitDecision('accepted')}
                            >
                                {decide.processing ? 'Submitting…' : 'Confirm & Accept Quotation'}
                            </Button>
                            <Button variant="outline" size="lg" disabled={changes.processing} onClick={askForChanges} className="border-white/40 text-white hover:bg-white/10 font-semibold backdrop-blur-sm">
                                {changes.processing ? 'Submitting…' : 'Ask for changes'}
                            </Button>
                            <Button
                                variant="ghost"
                                size="lg"
                                className="text-white/80 hover:text-white hover:bg-white/10 font-semibold"
                                disabled={decide.processing}
                                onClick={() => submitDecision('rejected')}
                            >
                                Decline
                            </Button>

                            {business.upi_id && (
                                <Button
                                    asChild
                                    variant="outline"
                                    size="lg"
                                    className="border-2 border-white/40 text-white hover:bg-white/10 font-bold backdrop-blur-sm ml-auto"
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
                            ? 'rounded-xl bg-blue-500/15 border border-blue-300/60 dark:border-blue-700/60 p-5 text-blue-900 dark:text-blue-100 backdrop-blur-sm'
                            : 'rounded-xl bg-white border border-blue-100 dark:bg-slate-800/60 p-5 text-slate-800 dark:text-slate-200 backdrop-blur-sm'
                    }>
                        <div className="flex items-center gap-3 font-bold text-base">
                            <div className={`size-8 rounded-full flex items-center justify-center ${accepted ? 'bg-blue-600 text-white' : 'bg-slate-500 text-white'}`}>
                                {accepted ? '✓' : '✎'}
                            </div>
                            <span>{accepted ? 'Quotation Accepted' : 'Feedback Recorded'}</span>
                        </div>
                        <p className="text-sm mt-2 text-slate-700 dark:text-slate-300 ml-11">
                            {accepted
                                ? 'Thank you! Your acceptance has been submitted to the seller. They will confirm your order and generate the final invoice.'
                                : 'Your feedback was sent to the shop owner. They will review your notes and respond shortly.'}
                        </p>
                    </div>
                )}

                {quotation.updates.length > 0 && (
                    <div className="border-t border-blue-100 dark:border-blue-900/40 pt-4">
                        <p className="text-xs font-bold text-blue-700 dark:text-blue-300 uppercase tracking-wider mb-3">Quotation Activity History</p>
                        <ol className="space-y-2.5 text-sm text-slate-700 dark:text-slate-300">
                            {quotation.updates.map((update, i) => (
                                <li key={i} className="flex items-start gap-3">
                                    <span className="size-2 rounded-full bg-blue-500 mt-2 shrink-0 shadow-sm shadow-blue-500/50"></span>
                                    <div>
                                        <span className="font-semibold text-slate-900 dark:text-slate-100">{update.label}</span>
                                        <span className="text-slate-500 dark:text-slate-400 ml-2 text-xs">
                                            {new Date(update.at).toLocaleString('en-IN', { dateStyle: 'short', timeStyle: 'short' })}
                                        </span>
                                        {update.detail && (
                                            <p className="text-slate-600 dark:text-slate-400 mt-0.5 text-xs italic">{update.detail}</p>
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
            className={cn(
                emphasize
                    ? 'flex justify-between border-t pt-2 font-semibold'
                    : 'flex justify-between text-muted-foreground',
                className,
            )}
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
        <div className="flex min-h-screen items-center justify-center bg-white dark:bg-slate-950 p-4">
            <Head title="Password required" />
            <Card className="w-full max-w-sm shadow-2xl shadow-blue-200/50 dark:shadow-blue-950/50 border-0 overflow-hidden">
                <div className="bg-blue-600 p-6 text-center">
                    <div className="mx-auto size-14 rounded-full bg-white/20 backdrop-blur-sm flex items-center justify-center text-white text-2xl font-bold shadow-lg mb-2">
                        🔒
                    </div>
                    <h2 className="text-lg font-bold text-white">Password Protected</h2>
                    <p className="text-sm text-blue-100 mt-1">Enter the password shared with you to view this document.</p>
                </div>
                <CardContent className="space-y-4 py-6">
                    <form onSubmit={submit} className="space-y-3">
                        <div className="grid gap-2">
                            <Label htmlFor="password" className="text-sm font-semibold text-slate-700 dark:text-slate-200">Password</Label>
                            <Input
                                id="password"
                                type="password"
                                value={data.password}
                                onChange={(e) => setData('password', e.target.value)}
                                autoFocus
                                className="border-blue-200 dark:border-blue-800 focus:border-blue-500 dark:focus:border-blue-400"
                            />
                            <InputError message={errors.password} />
                        </div>
                        <Button type="submit" className="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold shadow-lg" disabled={processing}>
                            {processing ? 'Checking…' : 'View document'}
                        </Button>
                    </form>
                </CardContent>
            </Card>
        </div>
    );
}
