import { Form, Head, Link, router } from '@inertiajs/react';
import { Cake, FileText, Gift, MessageSquare, Plus, Receipt as ReceiptIcon, Users } from 'lucide-react';
import { useState } from 'react';
import type { ReactNode } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
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
import ReminderController from '@/actions/App/Http/Controllers/ReminderController';
import { dashboard } from '@/routes';
import { index as remindersIndex } from '@/routes/reminders';
import { update as updateFollowup } from '@/routes/customers/followups';
import type { Staff } from '@/types/customer';
import type {
    CustomReminder,
    DueFollowup,
    DueInvoiceReminder,
    ExpiringQuote,
    OccasionReminder,
} from '@/types/reminder';

const currency = new Intl.NumberFormat('en-IN', { style: 'currency', currency: 'INR', maximumFractionDigits: 2 });

export default function RemindersPage({
    payments,
    followups,
    birthdays,
    anniversaries,
    custom,
    expiringQuotes,
    staff,
    customers,
    smsDriver,
}: {
    payments: DueInvoiceReminder[];
    followups: DueFollowup[];
    birthdays: OccasionReminder[];
    anniversaries: OccasionReminder[];
    custom: CustomReminder[];
    expiringQuotes: ExpiringQuote[];
    staff: Staff[];
    customers: { id: number; full_name: string }[];
    smsDriver: string;
}) {
    return (
        <>
            <Head title="Reminders" />

            <div className="space-y-6 p-4 md:p-6">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <Heading title="Reminders" description="Everything due for attention" />
                    <AddReminderDialog customers={customers} staff={staff} />
                </div>

                {smsDriver === 'log' && (
                    <p className="text-xs text-muted-foreground">
                        SMS is currently in log-only mode (no gateway configured) — messages record in
                        the activity log but aren't actually delivered. Set <code>SMS_DRIVER</code> in
                        <code> .env</code> to send for real.
                    </p>
                )}

                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <ReceiptIcon className="size-4" /> Payments due or overdue
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-2">
                        {payments.length === 0 ? (
                            <Empty text="Nothing due in the next few days." />
                        ) : (
                            payments.map((invoice) => (
                                <div key={invoice.id} className="flex flex-wrap items-center justify-between gap-2 rounded-md border p-3">
                                    <div>
                                        <Link href={`/invoices/${invoice.id}`} className="font-medium hover:underline">
                                            {invoice.invoice_number}
                                        </Link>
                                        <p className="text-sm text-muted-foreground">
                                            {invoice.customer.full_name} · due{' '}
                                            {invoice.due_date ? new Date(invoice.due_date).toLocaleDateString() : '—'}
                                        </p>
                                    </div>
                                    <div className="flex items-center gap-3">
                                        <span className="font-medium">{currency.format(Number(invoice.balance_amount))}</span>
                                        <Form {...ReminderController.sendPaymentSms.form(invoice.id)}>
                                            {({ processing }) => (
                                                <Button size="sm" variant="outline" disabled={processing}>
                                                    <MessageSquare className="size-4" />
                                                    SMS
                                                </Button>
                                            )}
                                        </Form>
                                    </div>
                                </div>
                            ))
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <FileText className="size-4" /> Quotations expiring
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-2">
                        {expiringQuotes.length === 0 ? (
                            <Empty text="No quotations closing in the next 3 days." />
                        ) : (
                            expiringQuotes.map((quote) => (
                                <div
                                    key={quote.id}
                                    className="flex flex-wrap items-center justify-between gap-2 rounded-md border p-3"
                                >
                                    <div>
                                        <Link
                                            href={`/invoices/${quote.id}`}
                                            className="font-medium hover:underline"
                                        >
                                            {quote.invoice_number}
                                        </Link>
                                        <p className="text-sm text-muted-foreground">
                                            {quote.customer.full_name} · valid
                                            until{' '}
                                            {new Date(
                                                quote.quotation_valid_until,
                                            ).toLocaleDateString()}
                                        </p>
                                    </div>
                                    <span className="font-medium">
                                        {currency.format(
                                            Number(quote.grand_total),
                                        )}
                                    </span>
                                </div>
                            ))
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Users className="size-4" /> Customer follow-ups
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-2">
                        {followups.length === 0 ? (
                            <Empty text="No follow-ups due." />
                        ) : (
                            followups.map((f) => (
                                <div
                                    key={f.id}
                                    className="flex flex-wrap items-center justify-between gap-2 rounded-md border p-3"
                                >
                                    <Link
                                        href={`/customers/${f.customer.id}`}
                                        className="min-w-0 flex-1 hover:underline"
                                    >
                                        <span className="font-medium">{f.customer.full_name}</span>
                                        {f.notes && <p className="text-sm text-muted-foreground">{f.notes}</p>}
                                    </Link>
                                    <div className="text-right text-sm text-muted-foreground">
                                        <div>{new Date(f.followup_date).toLocaleDateString()}</div>
                                        {f.assignee && <div>{f.assignee.name}</div>}
                                    </div>
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="outline"
                                        onClick={() =>
                                            router.put(
                                                updateFollowup({
                                                    customer: f.customer.id,
                                                    followup: f.id,
                                                }).url,
                                                { status: 'completed' },
                                                { preserveScroll: true },
                                            )
                                        }
                                    >
                                        Mark done
                                    </Button>
                                </div>
                            ))
                        )}
                    </CardContent>
                </Card>

                <div className="grid gap-4 sm:grid-cols-2">
                    <OccasionCard title="Birthdays" icon={<Cake className="size-4" />} rows={birthdays} occasion="birthday" />
                    <OccasionCard title="Anniversaries" icon={<Gift className="size-4" />} rows={anniversaries} occasion="anniversary" />
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Custom reminders</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-2">
                        {custom.length === 0 ? (
                            <Empty text="No custom reminders." />
                        ) : (
                            custom.map((r) => (
                                <div key={r.id} className="flex flex-wrap items-center justify-between gap-2 rounded-md border p-3">
                                    <div>
                                        <span className="font-medium">{r.title}</span>
                                        {r.customer && <span className="ml-2 text-sm text-muted-foreground">{r.customer.full_name}</span>}
                                        {r.notes && <p className="text-sm text-muted-foreground">{r.notes}</p>}
                                    </div>
                                    <div className="flex items-center gap-2">
                                        <span className="text-sm text-muted-foreground">
                                            {new Date(r.remind_on).toLocaleDateString()}
                                        </span>
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            onClick={() => router.post(`/reminders/${r.id}/done`, {}, { preserveScroll: true })}
                                        >
                                            Done
                                        </Button>
                                    </div>
                                </div>
                            ))
                        )}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

function Empty({ text }: { text: string }) {
    return <p className="text-sm text-muted-foreground">{text}</p>;
}

function OccasionCard({
    title,
    icon,
    rows,
    occasion,
}: {
    title: string;
    icon: ReactNode;
    rows: OccasionReminder[];
    occasion: 'birthday' | 'anniversary';
}) {
    return (
        <Card>
            <CardHeader>
                <CardTitle className="flex items-center gap-2">{icon} {title}</CardTitle>
            </CardHeader>
            <CardContent className="space-y-2">
                {rows.length === 0 ? (
                    <Empty text={`No upcoming ${title.toLowerCase()}.`} />
                ) : (
                    rows.map((row) => (
                        <div key={row.id} className="flex items-center justify-between gap-2 rounded-md border p-3">
                            <div>
                                <p className="font-medium">{row.full_name}</p>
                                <p className="text-sm text-muted-foreground">
                                    {row.days_until === 0 ? 'Today' : `In ${row.days_until} day(s)`}
                                </p>
                            </div>
                            <Button
                                size="sm"
                                variant="outline"
                                onClick={() =>
                                    router.post(`/reminders/customers/${row.id}/occasion-sms`, { occasion }, { preserveScroll: true })
                                }
                            >
                                <MessageSquare className="size-4" />
                                Wish
                            </Button>
                        </div>
                    ))
                )}
            </CardContent>
        </Card>
    );
}

function AddReminderDialog({ customers, staff }: { customers: { id: number; full_name: string }[]; staff: Staff[] }) {
    const [open, setOpen] = useState(false);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button size="sm">
                    <Plus className="size-4" />
                    Custom reminder
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>New reminder</DialogTitle>
                </DialogHeader>
                <Form
                    {...ReminderController.store.form()}
                    resetOnSuccess
                    onSuccess={() => setOpen(false)}
                    className="space-y-4"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="title">Title</Label>
                                <Input id="title" name="title" required />
                                <InputError message={errors.title} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="remind_on">Date</Label>
                                <Input id="remind_on" name="remind_on" type="date" required />
                                <InputError message={errors.remind_on} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="customer_id">Customer (optional)</Label>
                                <Select name="customer_id">
                                    <SelectTrigger id="customer_id" className="w-full">
                                        <SelectValue placeholder="None" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {customers.map((c) => (
                                            <SelectItem key={c.id} value={String(c.id)}>
                                                {c.full_name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="assigned_to">Assign to (optional)</Label>
                                <Select name="assigned_to">
                                    <SelectTrigger id="assigned_to" className="w-full">
                                        <SelectValue placeholder="Unassigned" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {staff.map((s) => (
                                            <SelectItem key={s.id} value={String(s.id)}>
                                                {s.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="notes">Notes (optional)</Label>
                                <Textarea id="notes" name="notes" rows={2} />
                            </div>
                            <DialogFooter>
                                <DialogClose asChild>
                                    <Button variant="secondary" type="button">Cancel</Button>
                                </DialogClose>
                                <Button disabled={processing}>Add</Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

RemindersPage.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Reminders', href: remindersIndex() },
    ],
};
