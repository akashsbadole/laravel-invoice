import { Form, Head, Link, router, usePage } from '@inertiajs/react';
import { FileText, Pencil, Plus, RotateCcw, Trash2 } from 'lucide-react';
import { useState } from 'react';
import CustomerController from '@/actions/App/Http/Controllers/CustomerController';
import CustomerAdvanceController from '@/actions/App/Http/Controllers/CustomerAdvanceController';
import CustomerFollowupController from '@/actions/App/Http/Controllers/CustomerFollowupController';
import CustomerNoteController from '@/actions/App/Http/Controllers/CustomerNoteController';
import Heading from '@/components/heading';
import { AttributesList } from '@/components/attributes-editor';
import InputError from '@/components/input-error';
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
import { dashboard } from '@/routes';
import { edit as editCustomer, index } from '@/routes/customers';
import { update as updateFollowup } from '@/routes/customers/followups';
import type { Auth } from '@/types/auth';
import type { Customer, CustomerAdvance, CustomerFollowup, Staff } from '@/types/customer';
import { cn } from '@/lib/utils';

const currency = new Intl.NumberFormat('en-IN', {
    style: 'currency',
    currency: 'INR',
    maximumFractionDigits: 0,
});

const statusOptions: CustomerFollowup['status'][] = [
    'pending',
    'contacted',
    'waiting_for_response',
    'completed',
    'cancelled',
];

function statusLabel(status: string) {
    return status
        .split('_')
        .map((word) => word[0].toUpperCase() + word.slice(1))
        .join(' ');
}

type Stats = {
    total_invoiced: number;
    total_paid: number;
    total_outstanding: number;
    last_invoice_date: string | null;
    /** Null when this business does not extend credit to the customer. */
    credit_limit: number | null;
    credit_outstanding: number;
    credit_overrun: number | null;
    /** Advance money this customer has paid that no invoice has consumed. */
    available_advance: number;
};

type TimelineEvent = {
    at: string;
    kind: 'invoice' | 'payment' | 'note' | 'followup' | 'message' | 'activity' | 'advance';
    label: string;
    detail: string | null;
    href: string | null;
};

export default function ShowCustomer({
    customer,
    stats,
    staff,
    timeline,
}: {
    customer: Customer;
    stats: Stats;
    staff: Staff[];
    timeline: TimelineEvent[];
}) {
const { auth } = usePage<{ auth: Auth }>().props;
    const canDeleteCustomer = ['admin', 'super_admin', 'manager'].includes(auth.user.role);
    // Tracked by id so only the row being worked on shows a spinner.
    const [reQuotingId, setReQuotingId] = useState<number | null>(null);

    /** The repeat order: same items, priced at today's metal rate. */
    function reQuote(invoiceId: number) {
        setReQuotingId(invoiceId);
        router.post(`/invoices/${invoiceId}/re-quote`, {}, {
            onFinish: () => setReQuotingId(null),
        });
    }

    return (
        <>
            <Head title={customer.full_name} />

            <div className="mx-auto w-full max-w-4xl space-y-6 p-4 md:p-6">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div className="min-w-0">
                        <Heading title={customer.full_name} />
                        <div className="flex flex-wrap items-center gap-2 text-sm text-muted-foreground">
                            <span>{customer.mobile_number}</span>
                            {customer.email && <span>· {customer.email}</span>}
                            <Badge variant="outline" className="capitalize">
                                {customer.customer_type}
                            </Badge>
                            {customer.group && (
                                <Badge variant="secondary">
                                    {customer.group.name}
                                    {Number(customer.group.discount_percent) > 0 &&
                                        ` · ${Number(customer.group.discount_percent)}%`}
                                </Badge>
                            )}
                        </div>
                    </div>

                    <div className="flex gap-2">
                        <Button variant="outline" asChild>
                            <Link href={editCustomer(customer.id)}>
                                <Pencil className="size-4" />
                                Edit
                            </Link>
                        </Button>

                        {canDeleteCustomer && (
                            <Dialog>
                                <DialogTrigger asChild>
                                    <Button variant="destructive">
                                        <Trash2 className="size-4" />
                                    </Button>
                                </DialogTrigger>
                                <DialogContent>
                                    <DialogHeader>
                                        <DialogTitle>
                                            Delete {customer.full_name}?
                                        </DialogTitle>
                                    </DialogHeader>
                                    <p className="text-sm text-muted-foreground">
                                        This removes the customer record. Their
                                        invoice history is kept for your
                                        records.
                                    </p>
                                    <Form
                                        {...CustomerController.destroy.form(
                                            customer.id,
                                        )}
                                    >
                                        {({ processing }) => (
                                            <DialogFooter className="mt-4 gap-2">
                                                <DialogClose asChild>
                                                    <Button variant="secondary">
                                                        Cancel
                                                    </Button>
                                                </DialogClose>
                                                <Button
                                                    variant="destructive"
                                                    disabled={processing}
                                                >
                                                    Delete customer
                                                </Button>
                                            </DialogFooter>
                                        )}
                                    </Form>
                                </DialogContent>
                            </Dialog>
                        )}
                    </div>
                </div>

                <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                    <StatCard label="Invoiced" value={currency.format(stats.total_invoiced)} />
                    <StatCard label="Paid" value={currency.format(stats.total_paid)} />
                    <StatCard
                        label="Outstanding"
                        value={currency.format(stats.total_outstanding)}
                        emphasize={stats.total_outstanding > 0}
                    />
<StatCard
                        label="Last invoice"
                        value={stats.last_invoice_date ?? '—'}
                    />
                </div>

                {/* Credit position. Only shown for customers the business
                    actually extends credit to. */}
                {stats.credit_limit !== null && (
                    <Card
                        className={
                            (stats.credit_overrun ?? 0) > 0
                                ? 'border-destructive/50'
                                : undefined
                        }
                    >
                        <CardHeader>
                            <CardTitle>Credit</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-1 text-sm">
                            <div className="flex justify-between">
                                <span className="text-muted-foreground">
                                    Limit
                                </span>
                                <span className="font-medium">
                                    {currency.format(stats.credit_limit)}
                                </span>
                            </div>
                            <div className="flex justify-between">
                                <span className="text-muted-foreground">
                                    Outstanding
                                </span>
                                <span className="font-medium">
                                    {currency.format(
                                        stats.credit_outstanding,
                                    )}
                                </span>
                            </div>
                            <div className="flex justify-between">
                                <span className="text-muted-foreground">
                                    Available
                                </span>
                                <span
                                    className={
                                        (stats.credit_overrun ?? 0) > 0
                                            ? 'font-medium text-destructive'
                                            : 'font-medium'
                                    }
                                >
                                    {(stats.credit_overrun ?? 0) > 0
                                        ? `Over by ${currency.format(
                                              stats.credit_overrun ?? 0,
                                          )}`
                                        : currency.format(
                                              Math.max(
                                                  0,
                                                  stats.credit_limit -
                                                      stats.credit_outstanding,
                                              ),
                                          )}
                                </span>
                            </div>
                            {customer.credit_days !== null && (
                                <p className="pt-1 text-xs text-muted-foreground">
                                    Net {customer.credit_days} days — the
                                    invoice due date is filled in
                                    automatically.
                                </p>
                            )}
                        </CardContent>
                    </Card>
                )}

                {(customer.tags?.length ?? 0) > 0 && (
                    <div className="flex flex-wrap gap-1">
                        {customer.tags?.map((tag) => (
                            <Badge key={tag} variant="outline">
                                {tag}
                            </Badge>
                        ))}
                    </div>
                )}

                {customer.attributes &&
                    Object.keys(customer.attributes).length > 0 && (
                        <Card>
                            <CardHeader>
                                <CardTitle>Extra attributes</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <AttributesList
                                    attributes={customer.attributes}
                                />
                            </CardContent>
                        </Card>
                    )}

                <Card>
                    <CardHeader className="flex-row items-center justify-between">
                        <CardTitle>Invoices</CardTitle>
                        <div className="flex gap-2">
                            <Button size="sm" variant="outline" asChild>
                                <Link href={`/invoices/create?customer_id=${customer.id}&document_type=quotation`}>
                                    <FileText className="size-4" />
                                    Quotation
                                </Link>
                            </Button>
                            <Button size="sm" variant="outline" asChild>
                                <Link href={`/invoices/create?customer_id=${customer.id}`}>
                                    <Plus className="size-4" />
                                    New invoice
                                </Link>
                            </Button>
                        </div>
                    </CardHeader>
                    <CardContent>
                        {!customer.invoices || customer.invoices.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                No invoices yet.
                            </p>
                        ) : (
<div className="divide-y">
                                {customer.invoices.map((invoice) => {
                                    // An adjustment corrects another bill, so
                                    // there is nothing to quote again.
                                    const canReQuote =
                                        invoice.document_type !== 'credit_note' &&
                                        invoice.document_type !== 'debit_note';

                                    return (
                                        <div
                                            key={invoice.id}
                                            className="flex items-center justify-between gap-2 py-1.5"
                                        >
                                            <Link
                                                href={`/invoices/${invoice.id}`}
                                                className="flex flex-1 items-center justify-between gap-2 text-sm hover:underline"
                                            >
                                                <span>{invoice.invoice_number}</span>
                                                <Badge variant="outline" className="capitalize">
                                                    {invoice.status}
                                                </Badge>
                                                <span>{currency.format(Number(invoice.grand_total))}</span>
                                            </Link>
                                            {canReQuote && (
                                                <Button
                                                    size="sm"
                                                    variant="ghost"
                                                    title="Same items, today's metal rate"
                                                    disabled={reQuotingId === invoice.id}
                                                    onClick={() => reQuote(invoice.id)}
                                                >
                                                    <RotateCcw
                                                        className={
                                                            reQuotingId === invoice.id
                                                                ? 'size-3.5 animate-spin'
                                                                : 'size-3.5'
                                                        }
                                                    />
                                                    Re-quote
                                                </Button>
                                            )}
                                        </div>
                                    );
                                })}
                            </div>
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader className="flex-row items-center justify-between">
                        <CardTitle>Advances</CardTitle>
                        <RecordAdvanceDialog customerId={customer.id} />
                    </CardHeader>
                    <CardContent className="space-y-3">
                        <p className="text-sm text-muted-foreground">
                            Available to apply:{' '}
                            <span className="font-medium text-foreground">
                                {currency.format(stats.available_advance)}
                            </span>
                            {' — '}apply it from any unpaid invoice of this
                            customer.
                        </p>
                        {!customer.advances || customer.advances.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                No advances recorded.
                            </p>
                        ) : (
                            <div className="divide-y">
                                {customer.advances.map((advance) => (
                                    <AdvanceRow
                                        key={advance.id}
                                        customerId={customer.id}
                                        advance={advance}
                                    />
                                ))}
                            </div>
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader className="flex-row items-center justify-between">
                        <CardTitle>Follow-ups</CardTitle>
                        <AddFollowupDialog customerId={customer.id} staff={staff} />
                    </CardHeader>
                    <CardContent className="space-y-3">
                        {!customer.followups || customer.followups.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                No follow-ups scheduled.
                            </p>
                        ) : (
                            customer.followups.map((followup) => (
                                <div
                                    key={followup.id}
                                    className="flex flex-col gap-2 rounded-lg border p-3 sm:flex-row sm:items-center sm:justify-between"
                                >
                                    <div className="min-w-0">
                                        <p className="text-sm font-medium">
                                            {new Date(
                                                followup.followup_date,
                                            ).toLocaleDateString()}
                                            {followup.assignee && (
                                                <span className="font-normal text-muted-foreground">
                                                    {' '}
                                                    · {followup.assignee.name}
                                                </span>
                                            )}
                                        </p>
                                        {followup.reminder_at && (
                                            <p className="text-xs text-brand-dark dark:text-brand-light">
                                                Reminder{' '}
                                                {new Date(
                                                    followup.reminder_at,
                                                ).toLocaleString()}
                                            </p>
                                        )}
                                        {followup.notes && (
                                            <p className="truncate text-sm text-muted-foreground">
                                                {followup.notes}
                                            </p>
                                        )}
                                    </div>
                                    <div className="flex items-center gap-2">
                                        {followup.status !== 'completed' && (
                                            <Form
                                                {...CustomerFollowupController.destroy.form(
                                                    customer.id,
                                                    followup.id,
                                                )}
                                            >
                                                {({ processing }) => (
                                                    <Button
                                                        type="submit"
                                                        size="icon"
                                                        variant="ghost"
                                                        disabled={processing}
                                                        title="Delete follow-up"
                                                        className="text-muted-foreground hover:text-destructive"
                                                    >
                                                        <Trash2 className="size-4" />
                                                    </Button>
                                                )}
                                            </Form>
                                        )}
                                    <Select
                                            defaultValue={followup.status}
                                            onValueChange={(value) =>
                                                router.put(
                                                    updateFollowup({
                                                        customer: customer.id,
                                                        followup: followup.id,
                                                    }).url,
                                                    { status: value },
                                                    { preserveScroll: true },
                                                )
                                            }
                                        >
                                            <SelectTrigger className="w-full sm:w-48">
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {statusOptions.map((status) => (
                                                    <SelectItem key={status} value={status}>
                                                        {statusLabel(status)}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    </div>
                                </div>
                            ))
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader className="flex-row items-center justify-between">
                        <CardTitle>Notes</CardTitle>
                        <AddNoteDialog customerId={customer.id} />
                    </CardHeader>
                    <CardContent className="space-y-3">
                        {!customer.notes_log || customer.notes_log.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                No notes yet.
                            </p>
                        ) : (
                            customer.notes_log.map((note) => (
                                <div key={note.id} className="rounded-lg border p-3">
                                    <div className="mb-1 flex items-center justify-between text-xs text-muted-foreground">
                                        <span className="capitalize">{note.type}</span>
                                        <span>
                                            {note.creator?.name ?? 'Unknown'} ·{' '}
                                            {new Date(note.created_at).toLocaleDateString()}
                                        </span>
                                    </div>
                                    <p className="text-sm whitespace-pre-wrap">{note.note}</p>
                                </div>
                            ))
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Activity</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {timeline.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                Nothing recorded yet.
                            </p>
                        ) : (
                            <ol className="space-y-3">
                                {timeline.map((event, index) => (
                                    <li key={index} className="flex gap-3 text-sm">
                                        <span
                                            className={cn(
                                                'mt-1 size-2 shrink-0 rounded-full',
                                                event.kind === 'payment'
                                                    ? 'bg-emerald-500'
                                                    : event.kind === 'invoice'
                                                      ? 'bg-brand'
                                                      : event.kind === 'message'
                                                        ? 'bg-sky-500'
                                                        : 'bg-muted-foreground/40',
                                            )}
                                        />
                                        <div className="min-w-0">
                                            {event.href ? (
                                                <Link
                                                    href={event.href}
                                                    className="block hover:underline"
                                                >
                                                    {event.label}
                                                </Link>
                                            ) : (
                                                <span className="block">
                                                    {event.label}
                                                </span>
                                            )}
                                            <span className="block text-xs text-muted-foreground">
                                                {event.detail && (
                                                    <>{event.detail} · </>
                                                )}
                                                {new Date(
                                                    event.at,
                                                ).toLocaleString()}
                                            </span>
                                        </div>
                                    </li>
                                ))}
                            </ol>
                        )}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

function StatCard({
    label,
    value,
    emphasize,
}: {
    label: string;
    value: string;
    emphasize?: boolean;
}) {
    return (
        <Card>
            <CardContent className="px-4">
                <p className="text-xs text-muted-foreground">{label}</p>
                <p
                    className={`mt-1 text-lg font-semibold ${emphasize ? 'text-amber-600 dark:text-amber-400' : ''}`}
                >
                    {value}
                </p>
            </CardContent>
        </Card>
    );
}

function AddNoteDialog({ customerId }: { customerId: number }) {
    const [open, setOpen] = useState(false);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button size="sm" variant="outline">
                    <Plus className="size-4" />
                    Add note
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Add note</DialogTitle>
                </DialogHeader>
                <Form
                    {...CustomerNoteController.store.form(customerId)}
                    resetOnSuccess
                    onSuccess={() => setOpen(false)}
                    className="space-y-4"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="type">Type</Label>
                                <Select name="type" defaultValue="private">
                                    <SelectTrigger id="type" className="w-full">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="private">Private note</SelectItem>
                                        <SelectItem value="communication">
                                            Communication log
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                <InputError message={errors.type} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="note">Note</Label>
                                <Textarea id="note" name="note" rows={4} required />
                                <InputError message={errors.note} />
                            </div>

                            <DialogFooter>
                                <DialogClose asChild>
                                    <Button variant="secondary" type="button">
                                        Cancel
                                    </Button>
                                </DialogClose>
                                <Button disabled={processing}>Save note</Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

function AddFollowupDialog({
    customerId,
    staff,
}: {
    customerId: number;
    staff: Staff[];
}) {
    const [open, setOpen] = useState(false);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button size="sm" variant="outline">
                    <Plus className="size-4" />
                    Add follow-up
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Schedule a follow-up</DialogTitle>
                </DialogHeader>
                <Form
                    {...CustomerFollowupController.store.form(customerId)}
                    resetOnSuccess
                    onSuccess={() => setOpen(false)}
                    className="space-y-4"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="followup_date">Date</Label>
                                <Input
                                    id="followup_date"
                                    name="followup_date"
                                    type="date"
                                    required
                                />
                                <InputError message={errors.followup_date} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="reminder_at">
                                    Remind me on (optional)
                                </Label>
                                <Input
                                    id="reminder_at"
                                    name="reminder_at"
                                    type="datetime-local"
                                />
                                <p className="text-xs text-muted-foreground">
                                    Shows on the reminders page when this time
                                    passes, even before the follow-up date.
                                </p>
                                <InputError message={errors.reminder_at} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="assigned_to">Assign to (optional)</Label>
                                <Select name="assigned_to">
                                    <SelectTrigger id="assigned_to" className="w-full">
                                        <SelectValue placeholder="Unassigned" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {staff.map((member) => (
                                            <SelectItem
                                                key={member.id}
                                                value={String(member.id)}
                                            >
                                                {member.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError message={errors.assigned_to} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="fu_notes">Notes (optional)</Label>
                                <Textarea id="fu_notes" name="notes" rows={3} />
                                <InputError message={errors.notes} />
                            </div>

                            <DialogFooter>
                                <DialogClose asChild>
                                    <Button variant="secondary" type="button">
                                        Cancel
                                    </Button>
                                </DialogClose>
                                <Button disabled={processing}>Schedule</Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

function AdvanceRow({ customerId, advance }: { customerId: number; advance: CustomerAdvance }) {
    const available = Math.max(
        0,
        Number(advance.amount) - Number(advance.applied_amount),
    );
    const canRefund =
        advance.status === 'available' && Number(advance.applied_amount) === 0;

    return (
        <div className="flex flex-wrap items-center justify-between gap-2 py-2 text-sm">
            <div className="flex flex-wrap items-center gap-2">
                <span className="font-medium">
                    {currency.format(Number(advance.amount))}
                </span>
                <span className="text-muted-foreground">
                    {new Date(advance.advance_date).toLocaleDateString()}
                </span>
                <span className="text-muted-foreground capitalize">
                    · {advance.payment_method.replace('_', ' ')}
                </span>
                <Badge
                    variant="outline"
                    className={cn(
                        'capitalize',
                        advance.status === 'refunded' && 'text-muted-foreground line-through',
                        advance.status === 'available' && available > 0 && 'text-emerald-700 dark:text-emerald-400',
                    )}
                >
                    {advance.status === 'available' && available > 0
                        ? `${currency.format(available)} available`
                        : statusLabel(advance.status)}
                </Badge>
            </div>
            {canRefund && (
                <Form {...CustomerAdvanceController.refund.form(customerId, advance.id)}>
                    {({ processing }) => (
                        <Button variant="ghost" size="sm" disabled={processing}>
                            Refund
                        </Button>
                    )}
                </Form>
            )}
        </div>
    );
}

function RecordAdvanceDialog({ customerId }: { customerId: number }) {
    const [open, setOpen] = useState(false);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button size="sm" variant="outline">
                    <Plus className="size-4" />
                    Record advance
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Record an advance</DialogTitle>
                </DialogHeader>
                <p className="text-sm text-muted-foreground">
                    Booking fees or part-payments taken before an invoice —
                    apply them to any invoice later.
                </p>
                <Form
                    {...CustomerAdvanceController.store.form(customerId)}
                    resetOnSuccess
                    onSuccess={() => setOpen(false)}
                    className="space-y-4"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="adv_amount">Amount</Label>
                                <Input
                                    id="adv_amount"
                                    name="amount"
                                    type="number"
                                    step="0.01"
                                    min="0.01"
                                    required
                                />
                                <InputError message={errors.amount} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="adv_date">Received on</Label>
                                <Input
                                    id="adv_date"
                                    name="advance_date"
                                    type="date"
                                    defaultValue={new Date().toISOString().slice(0, 10)}
                                    required
                                />
                                <InputError message={errors.advance_date} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="adv_method">Method</Label>
                                <Select name="payment_method" defaultValue="cash">
                                    <SelectTrigger id="adv_method" className="w-full">
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
                                <Label htmlFor="adv_reference">Reference no. (optional)</Label>
                                <Input id="adv_reference" name="reference_number" />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="adv_notes">Notes (optional)</Label>
                                <Textarea id="adv_notes" name="notes" rows={2} />
                                <InputError message={errors.notes} />
                            </div>
                            <DialogFooter>
                                <DialogClose asChild>
                                    <Button variant="secondary" type="button">
                                        Cancel
                                    </Button>
                                </DialogClose>
                                <Button disabled={processing}>Record advance</Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

ShowCustomer.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Customers', href: index() },
    ],
};
