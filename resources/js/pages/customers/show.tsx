import { Form, Head, Link, router, usePage } from '@inertiajs/react';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import CustomerController from '@/actions/App/Http/Controllers/CustomerController';
import CustomerFollowupController from '@/actions/App/Http/Controllers/CustomerFollowupController';
import CustomerNoteController from '@/actions/App/Http/Controllers/CustomerNoteController';
import Heading from '@/components/heading';
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
import type { Customer, CustomerFollowup, Staff } from '@/types/customer';

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
};

export default function ShowCustomer({
    customer,
    stats,
    staff,
}: {
    customer: Customer;
    stats: Stats;
    staff: Staff[];
}) {
    const { auth } = usePage<{ auth: Auth }>().props;
    const isAdmin = auth.user.role === 'admin';

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
                        </div>
                    </div>

                    <div className="flex gap-2">
                        <Button variant="outline" asChild>
                            <Link href={editCustomer(customer.id)}>
                                <Pencil className="size-4" />
                                Edit
                            </Link>
                        </Button>

                        {isAdmin && (
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

                <Card>
                    <CardHeader className="flex-row items-center justify-between">
                        <CardTitle>Invoices</CardTitle>
                        <Button size="sm" variant="outline" asChild>
                            <Link href={`/invoices/create?customer_id=${customer.id}`}>
                                <Plus className="size-4" />
                                New invoice
                            </Link>
                        </Button>
                    </CardHeader>
                    <CardContent>
                        {!customer.invoices || customer.invoices.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                No invoices yet.
                            </p>
                        ) : (
                            <div className="divide-y">
                                {customer.invoices.map((invoice) => (
                                    <Link
                                        key={invoice.id}
                                        href={`/invoices/${invoice.id}`}
                                        className="flex items-center justify-between py-2 text-sm hover:underline"
                                    >
                                        <span>{invoice.invoice_number}</span>
                                        <Badge variant="outline" className="capitalize">
                                            {invoice.status}
                                        </Badge>
                                        <span>{currency.format(Number(invoice.grand_total))}</span>
                                    </Link>
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
                                        {followup.notes && (
                                            <p className="truncate text-sm text-muted-foreground">
                                                {followup.notes}
                                            </p>
                                        )}
                                    </div>
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
    return (
        <Dialog>
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
    return (
        <Dialog>
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

ShowCustomer.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Customers', href: index() },
    ],
};
