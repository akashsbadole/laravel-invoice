import { Form, Head, Link, router, usePage } from '@inertiajs/react';
import { Copy, Download, Mail, MessageCircle, MessageSquare, Pencil, Printer, Receipt, Trash2 } from 'lucide-react';
import { useState } from 'react';
import InvoiceController from '@/actions/App/Http/Controllers/InvoiceController';
import InvoiceShareLinkController from '@/actions/App/Http/Controllers/InvoiceShareLinkController';
import PaymentController from '@/actions/App/Http/Controllers/PaymentController';
import InputError from '@/components/input-error';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
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
import { pdf as pdfRoute, receipt as receiptRoute } from '@/routes/invoices';
import { buildMailtoUrl, buildPublicInvoiceUrl, buildShareMessage, buildWhatsAppShareUrl } from '@/lib/share-invoice';
import type { Auth } from '@/types/auth';
import type { Invoice } from '@/types/invoice';

const currency = new Intl.NumberFormat('en-IN', { style: 'currency', currency: 'INR', maximumFractionDigits: 2 });

type RecurringProfile = {
    id: number;
    frequency: string;
    next_run_at: string;
    last_run_at: string | null;
    is_active: boolean;
} | null;

export default function ShowInvoice({ invoice, recurringProfile }: { invoice: Invoice; recurringProfile: RecurringProfile }) {
    const { auth } = usePage<{ auth: Auth }>().props;
    const isAdmin = auth.user.role === 'admin';
    const activeLink = invoice.share_links.find((link) => link.is_active);
    const canWrite = auth.user.role === 'admin' || auth.user.role === 'invoice_creator';

    function markSent(via: 'whatsapp' | 'email' | 'copy') {
        if (!activeLink) return;
        router.post(
            `/invoices/${invoice.id}/share-links/${activeLink.id}/mark-sent`,
            { via },
            { preserveScroll: true, preserveState: true },
        );
    }

    function shareWhatsApp() {
        if (!activeLink) return;
        const url = buildWhatsAppShareUrl(
            invoice.customer.mobile_number,
            buildShareMessage({
                businessName: 'us',
                invoiceNumber: invoice.invoice_number,
                grandTotal: invoice.grand_total,
                publicUrl: buildPublicInvoiceUrl(activeLink.token),
            }),
        );
        window.open(url, '_blank');
        markSent('whatsapp');
    }

    function shareEmail() {
        if (!activeLink || !invoice.customer.email) return;
        const url = buildMailtoUrl(
            invoice.customer.email,
            `Invoice ${invoice.invoice_number}`,
            buildShareMessage({
                businessName: 'us',
                invoiceNumber: invoice.invoice_number,
                grandTotal: invoice.grand_total,
                publicUrl: buildPublicInvoiceUrl(activeLink.token),
            }),
        );
        window.location.href = url;
        markSent('email');
    }

    function copyLink() {
        if (!activeLink) return;
        navigator.clipboard.writeText(buildPublicInvoiceUrl(activeLink.token));
        markSent('copy');
    }

    return (
        <>
            <Head title={invoice.invoice_number} />

            <div className="mx-auto w-full max-w-4xl space-y-6 p-4 md:p-6">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <Heading title={invoice.invoice_number} />
                        <div className="flex flex-wrap items-center gap-2 text-sm text-muted-foreground">
                            <Link href={`/customers/${invoice.customer.id}`} className="hover:underline">
                                {invoice.customer.full_name}
                            </Link>
                            <span>· {new Date(invoice.invoice_date).toLocaleDateString()}</span>
                            <Badge variant="secondary" className="capitalize">
                                {invoice.status.replace('_', ' ')}
                            </Badge>
                            {invoice.document_type !== 'jewelry_invoice' && (
                                <Badge variant="outline">
                                    {invoice.document_type === 'quotation' ? 'Quotation' : 'General invoice'}
                                </Badge>
                            )}
                        </div>
                    </div>

                    <div className="flex flex-wrap gap-2">
                        <Button variant="outline" asChild>
                            <a href={pdfRoute(invoice.id).url} target="_blank" rel="noreferrer">
                                <Printer className="size-4" />
                            </a>
                        </Button>
                        <Button variant="outline" asChild>
                            <a href={receiptRoute(invoice.id).url} target="_blank" rel="noreferrer" title="Thermal receipt (58/80mm)">
                                <Receipt className="size-4" />
                            </a>
                        </Button>
                        {canWrite && invoice.status !== 'cancelled' && (
                            <Button variant="outline" asChild>
                                <Link href={`/invoices/${invoice.id}/edit`}>
                                    <Pencil className="size-4" />
                                    Edit
                                </Link>
                            </Button>
                        )}
                        {canWrite && invoice.status !== 'cancelled' && (
                            <Form {...InvoiceController.cancel.form(invoice.id)}>
                                {({ processing }) => (
                                    <Button variant="outline" type="submit" disabled={processing}>
                                        Cancel invoice
                                    </Button>
                                )}
                            </Form>
                        )}
                        {canWrite && invoice.document_type === 'quotation' && !invoice.converted_to_id && (
                            <Dialog>
                                <DialogTrigger asChild>
                                    <Button>Convert to invoice</Button>
                                </DialogTrigger>
                                <DialogContent>
                                    <DialogHeader>
                                        <DialogTitle>Convert quotation to invoice</DialogTitle>
                                    </DialogHeader>
                                    <p className="text-sm text-muted-foreground">
                                        A new invoice is created with the same items. The
                                        quotation is marked as converted.
                                    </p>
                                    <Form {...InvoiceController.convert.form(invoice.id)}>
                                        {({ processing }) => (
                                            <>
                                                <div className="grid gap-2">
                                                    <Label htmlFor="convert-type">Invoice type</Label>
                                                    <Select name="document_type" defaultValue="jewelry_invoice">
                                                        <SelectTrigger id="convert-type" className="w-full">
                                                            <SelectValue />
                                                        </SelectTrigger>
                                                        <SelectContent>
                                                            <SelectItem value="jewelry_invoice">
                                                                Jewelry invoice
                                                            </SelectItem>
                                                            <SelectItem value="general_invoice">
                                                                General invoice
                                                            </SelectItem>
                                                        </SelectContent>
                                                    </Select>
                                                </div>
                                                <DialogFooter className="mt-4 gap-2">
                                                    <DialogClose asChild>
                                                        <Button variant="secondary" type="button">Cancel</Button>
                                                    </DialogClose>
                                                    <Button disabled={processing}>Convert</Button>
                                                </DialogFooter>
                                            </>
                                        )}
                                    </Form>
                                </DialogContent>
                            </Dialog>
                        )}
                        {isAdmin && (
                            <Dialog>
                                <DialogTrigger asChild>
                                    <Button variant="destructive" size="icon">
                                        <Trash2 className="size-4" />
                                    </Button>
                                </DialogTrigger>
                                <DialogContent>
                                    <DialogHeader>
                                        <DialogTitle>Delete {invoice.invoice_number}?</DialogTitle>
                                    </DialogHeader>
                                    <p className="text-sm text-muted-foreground">
                                        This can't be undone.
                                    </p>
                                    <Form {...InvoiceController.destroy.form(invoice.id)}>
                                        {({ processing }) => (
                                            <DialogFooter className="mt-4 gap-2">
                                                <DialogClose asChild>
                                                    <Button variant="secondary">Cancel</Button>
                                                </DialogClose>
                                                <Button variant="destructive" disabled={processing}>
                                                    Delete
                                                </Button>
                                            </DialogFooter>
                                        )}
                                    </Form>
                                </DialogContent>
                            </Dialog>
                        )}
                    </div>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Items</CardTitle>
                    </CardHeader>
                    <CardContent className="overflow-x-auto">
                        <table className="w-full min-w-[640px] text-sm">
                            <thead className="border-b text-left text-muted-foreground">
                                <tr>
                                    <th className="py-2 pr-2 font-medium">Item</th>
                                    <th className="py-2 pr-2 font-medium">Metal / Purity</th>
                                    <th className="py-2 pr-2 text-right font-medium">Net wt</th>
                                    <th className="py-2 pr-2 text-right font-medium">Qty</th>
                                    <th className="py-2 pr-2 text-right font-medium">Charges</th>
                                    <th className="py-2 text-right font-medium">Total</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {invoice.items.map((item) => (
                                    <tr key={item.id}>
                                        <td className="py-2 pr-2">
                                            <div className="font-medium">{item.item_name}</div>
                                            {item.huid_number && (
                                                <div className="text-xs text-muted-foreground">
                                                    HUID {item.huid_number}
                                                </div>
                                            )}
                                        </td>
                                        <td className="py-2 pr-2">
                                            {item.metal_type} {item.purity && `/ ${item.purity}`}
                                        </td>
                                        <td className="py-2 pr-2 text-right">{item.net_weight}g</td>
                                        <td className="py-2 pr-2 text-right">{item.quantity}</td>
                                        <td className="py-2 pr-2 text-right">
                                            {currency.format(item.charges.reduce((sum, c) => sum + Number(c.amount), 0) * item.quantity)}
                                        </td>
                                        <td className="py-2 text-right font-medium">
                                            {currency.format(Number(item.total))}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Totals</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-1 text-sm">
                        <TotalRow label="Subtotal" value={invoice.subtotal} />
                        {(invoice.charges_summary ?? []).map((row) => (
                            <TotalRow key={row.code} label={row.label} value={String(row.amount)} />
                        ))}
                        <TotalRow label="Discount" value={`-${invoice.discount}`} />
                        {invoice.tax_breakdown && invoice.tax_breakdown.length > 0 ? (
                            invoice.tax_breakdown.map((row, i) => (
                                <TotalRow key={i} label={row.label} value={String(row.amount)} />
                            ))
                        ) : (
                            <TotalRow label="Tax" value={invoice.tax} />
                        )}
                        <TotalRow label="Round off" value={invoice.round_off} />
                        <TotalRow label="Grand total" value={invoice.grand_total} emphasize />
                        <TotalRow label="Paid" value={invoice.paid_amount} />
                        <TotalRow label="Balance due" value={invoice.balance_amount} emphasize />
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader className="flex-row items-center justify-between">
                        <CardTitle>Payments</CardTitle>
                        {canWrite && invoice.document_type !== 'quotation' && Number(invoice.balance_amount) > 0 && (
                            <RecordPaymentDialog invoiceId={invoice.id} balance={invoice.balance_amount} />
                        )}
                    </CardHeader>
                    <CardContent className="space-y-2">
                        {invoice.payments.length === 0 ? (
                            <p className="text-sm text-muted-foreground">No payments recorded.</p>
                        ) : (
                            invoice.payments.map((payment) => (
                                <div
                                    key={payment.id}
                                    className="flex items-center justify-between rounded-md border p-2 text-sm"
                                >
                                    <div>
                                        <span className="font-medium">{currency.format(Number(payment.amount))}</span>
                                        <span className="ml-2 text-muted-foreground capitalize">
                                            {payment.payment_method.replace('_', ' ')}
                                        </span>
                                    </div>
                                    <span className="text-muted-foreground">
                                        {new Date(payment.payment_date).toLocaleDateString()}
                                    </span>
                                </div>
                            ))
                        )}
                    </CardContent>
                </Card>

                {canWrite && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Share with customer</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            {!activeLink ? (
                                <Form
                                    {...InvoiceShareLinkController.store.form(invoice.id)}
                                    options={{ preserveScroll: true }}
                                >
                                    {({ processing }) => (
                                        <Button disabled={processing}>
                                            {processing ? 'Generating…' : 'Generate share link'}
                                        </Button>
                                    )}
                                </Form>
                            ) : (
                                <>
                                    <div className="flex flex-col gap-2 sm:flex-row">
                                        <Input
                                            readOnly
                                            value={buildPublicInvoiceUrl(activeLink.token)}
                                            onFocus={(e) => e.target.select()}
                                        />
                                        <Button variant="outline" onClick={copyLink} className="shrink-0">
                                            <Copy className="size-4" />
                                            Copy
                                        </Button>
                                    </div>
                                    <div className="flex flex-wrap gap-2">
                                        <Button onClick={shareWhatsApp} className="bg-[#25D366] text-white hover:bg-[#1fb955]">
                                            <MessageCircle className="size-4" />
                                            Share on WhatsApp
                                        </Button>
                                        {invoice.customer.email && (
                                            <Button variant="outline" onClick={shareEmail}>
                                                <Mail className="size-4" />
                                                Email
                                            </Button>
                                        )}
                                        <SendEmailDialog
                                            invoiceId={invoice.id}
                                            defaultEmail={invoice.customer.email ?? ''}
                                        />
                                        <Form {...InvoiceShareLinkController.sendSms.form({
                                            invoice: invoice.id,
                                            shareLink: activeLink.id,
                                        })}>
                                            {({ processing }) => (
                                                <Button type="submit" variant="outline" disabled={processing}>
                                                    <MessageSquare className="size-4" />
                                                    SMS
                                                </Button>
                                            )}
                                        </Form>
                                        <Button variant="outline" asChild>
                                            <a href={`${pdfRoute(invoice.id).url}`} download>
                                                <Download className="size-4" />
                                                Download PDF
                                            </a>
                                        </Button>
                                        <Form
                                            {...InvoiceShareLinkController.deactivate.form({
                                                invoice: invoice.id,
                                                shareLink: activeLink.id,
                                            })}
                                        >
                                            {({ processing }) => (
                                                <Button variant="ghost" disabled={processing}>
                                                    Disable link
                                                </Button>
                                            )}
                                        </Form>
                                    </div>
                                    <div className="text-xs text-muted-foreground">
                                        {activeLink.sent_at && (
                                            <>Sent via {activeLink.sent_via} on {new Date(activeLink.sent_at).toLocaleString()}. </>
                                        )}
                                        {activeLink.viewed_at && (
                                            <>Viewed {new Date(activeLink.viewed_at).toLocaleString()}. </>
                                        )}
                                        {activeLink.downloaded_at && (
                                            <>Downloaded {new Date(activeLink.downloaded_at).toLocaleString()}.</>
                                        )}
                                    </div>
                                </>
                            )}
                        </CardContent>
                    </Card>
                )}

                {canWrite && invoice.document_type !== 'quotation' && invoice.status !== 'cancelled' && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Recurring</CardTitle>
                        </CardHeader>
                        <CardContent>
                            {recurringProfile ? (
                                <div className="flex flex-wrap items-center justify-between gap-2 text-sm">
                                    <p className="text-muted-foreground">
                                        Repeats <span className="font-medium text-foreground capitalize">{recurringProfile.frequency}</span>
                                        {' · '}next run{' '}
                                        {new Date(recurringProfile.next_run_at).toLocaleDateString()}
                                    </p>
                                    <Form {...InvoiceController.recurringDestroy.form(invoice.id, recurringProfile.id)}>
                                        {({ processing }) => (
                                            <Button variant="ghost" size="sm" disabled={processing}>
                                                Stop recurring
                                            </Button>
                                        )}
                                    </Form>
                                </div>
                            ) : (
                                <Form
                                    {...InvoiceController.recurringStore.form(invoice.id)}
                                    options={{ preserveScroll: true }}
                                    className="flex flex-col gap-2 sm:flex-row sm:items-end"
                                >
                                    {({ processing, errors }) => (
                                        <>
                                            <div className="grid gap-1.5">
                                                <Label htmlFor="frequency">Repeat</Label>
                                                <Select name="frequency" defaultValue="monthly">
                                                    <SelectTrigger id="frequency" className="w-full sm:w-40">
                                                        <SelectValue />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        <SelectItem value="weekly">Weekly</SelectItem>
                                                        <SelectItem value="monthly">Monthly</SelectItem>
                                                        <SelectItem value="quarterly">Quarterly</SelectItem>
                                                    </SelectContent>
                                                </Select>
                                            </div>
                                            <div className="grid gap-1.5">
                                                <Label htmlFor="next_run_at">First run</Label>
                                                <Input
                                                    id="next_run_at"
                                                    name="next_run_at"
                                                    type="date"
                                                    defaultValue={new Date().toISOString().slice(0, 10)}
                                                />
                                                <InputError message={errors.next_run_at} />
                                            </div>
                                            <Button disabled={processing}>Make recurring</Button>
                                        </>
                                    )}
                                </Form>
                            )}
                        </CardContent>
                    </Card>
                )}
            </div>
        </>
    );
}

function TotalRow({ label, value, emphasize }: { label: string; value: string; emphasize?: boolean }) {
    return (
        <div
            className={
                emphasize
                    ? 'flex justify-between border-t pt-2 text-base font-semibold'
                    : 'flex justify-between text-muted-foreground'
            }
        >
            <span>{label}</span>
            <span>{currency.format(Number(value))}</span>
        </div>
    );
}

function RecordPaymentDialog({ invoiceId, balance }: { invoiceId: number; balance: string }) {
    const [open, setOpen] = useState(false);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button size="sm">Record payment</Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Record a payment</DialogTitle>
                </DialogHeader>
                <Form
                    {...PaymentController.store.form(invoiceId)}
                    resetOnSuccess
                    onSuccess={() => setOpen(false)}
                    className="space-y-4"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="amount">Amount (balance: {currency.format(Number(balance))})</Label>
                                <Input
                                    id="amount"
                                    name="amount"
                                    type="number"
                                    step="0.01"
                                    min={0.01}
                                    defaultValue={balance}
                                    required
                                />
                                <InputError message={errors.amount} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="payment_date">Date</Label>
                                <Input
                                    id="payment_date"
                                    name="payment_date"
                                    type="date"
                                    defaultValue={new Date().toISOString().slice(0, 10)}
                                    required
                                />
                                <InputError message={errors.payment_date} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="payment_method">Method</Label>
                                <Select name="payment_method" defaultValue="cash">
                                    <SelectTrigger id="payment_method" className="w-full">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="cash">Cash</SelectItem>
                                        <SelectItem value="bank_transfer">Bank transfer</SelectItem>
                                        <SelectItem value="card">Card</SelectItem>
                                        <SelectItem value="upi">UPI</SelectItem>
                                        <SelectItem value="cheque">Cheque</SelectItem>
                                        <SelectItem value="other">Other</SelectItem>
                                    </SelectContent>
                                </Select>
                                <InputError message={errors.payment_method} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="reference_number">Reference no. (optional)</Label>
                                <Input id="reference_number" name="reference_number" />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="payment_notes">Notes (optional)</Label>
                                <Textarea id="payment_notes" name="notes" rows={2} />
                            </div>
                            <DialogFooter>
                                <DialogClose asChild>
                                    <Button variant="secondary" type="button">Cancel</Button>
                                </DialogClose>
                                <Button disabled={processing}>Record payment</Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

function SendEmailDialog({ invoiceId, defaultEmail }: { invoiceId: number; defaultEmail: string }) {
    const [open, setOpen] = useState(false);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button variant="outline">
                    <Mail className="size-4" />
                    Send via email
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Email invoice PDF</DialogTitle>
                </DialogHeader>
                <Form
                    {...InvoiceController.sendEmail.form(invoiceId)}
                    options={{ preserveScroll: true }}
                    onSuccess={() => setOpen(false)}
                    className="space-y-4"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="email-to">To</Label>
                                <Input
                                    id="email-to"
                                    name="email"
                                    type="email"
                                    defaultValue={defaultEmail}
                                    required
                                />
                                <InputError message={errors.email} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="email-message">Message (optional)</Label>
                                <Textarea id="email-message" name="message" rows={3} />
                                <InputError message={errors.message} />
                            </div>
                            <DialogFooter>
                                <DialogClose asChild>
                                    <Button variant="secondary" type="button">Cancel</Button>
                                </DialogClose>
                                <Button disabled={processing}>Send email</Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
