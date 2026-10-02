import { Form, Head } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useState } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
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
import CustomerGroupController from '@/actions/App/Http/Controllers/Settings/CustomerGroupController';
import type { CustomerGroup } from '@/types/customer';

export default function CustomerGroupsPage({
    customerGroups,
}: {
    customerGroups: CustomerGroup[];
}) {
    return (
        <>
            <Head title="Customer groups" />

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title="Customer groups"
                    description="Pricing tiers that take a percentage off every invoice line you haven't priced by hand"
                />

                <div className="flex justify-end">
                    <AddCustomerGroupDialog />
                </div>

                <Card>
                    <CardContent className="space-y-1 p-4">
                        {customerGroups.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                No groups yet. A shop that deals in walk-in
                                retail never needs one.
                            </p>
                        ) : (
                            customerGroups.map((group) => (
                                <div
                                    key={group.id}
                                    className="flex flex-wrap items-center justify-between gap-2 rounded-md border p-3"
                                >
                                    <div>
                                        <div className="flex items-center gap-2">
                                            <span className="font-medium">{group.name}</span>
                                            {!group.is_active && (
                                                <Badge variant="secondary" className="text-xs">
                                                    inactive
                                                </Badge>
                                            )}
                                        </div>
                                        <p className="text-xs text-muted-foreground">
                                            {Number(group.discount_percent) > 0
                                                ? `${Number(group.discount_percent)}% off unpriced lines`
                                                : 'No discount'}
                                            {group.customers_count !== undefined &&
                                                ` · ${group.customers_count} ${group.customers_count === 1 ? 'customer' : 'customers'}`}
                                        </p>
                                    </div>
                                    <div className="flex items-center gap-2">
                                        <EditCustomerGroupDialog group={group} />
                                        <Form {...CustomerGroupController.destroy.form(group.id)}>
                                            {({ processing }) => (
                                                <Button
                                                    size="sm"
                                                    variant="ghost"
                                                    disabled={processing}
                                                >
                                                    Remove
                                                </Button>
                                            )}
                                        </Form>
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

function AddCustomerGroupDialog() {
    const [open, setOpen] = useState(false);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button size="sm">
                    <Plus className="size-4" />
                    Add group
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>New customer group</DialogTitle>
                </DialogHeader>
                <Form
                    {...CustomerGroupController.store.form()}
                    resetOnSuccess
                    onSuccess={() => setOpen(false)}
                    className="space-y-4"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="name">Name</Label>
                                <Input
                                    id="name"
                                    name="name"
                                    placeholder="e.g. Wholesale"
                                    required
                                />
                                <InputError message={errors.name} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="discount_percent">
                                    Discount off unpriced lines (%)
                                </Label>
                                <Input
                                    id="discount_percent"
                                    name="discount_percent"
                                    type="number"
                                    step="0.01"
                                    min={0}
                                    max={100}
                                    defaultValue={0}
                                    required
                                />
                                <p className="text-xs text-muted-foreground">
                                    A line where you typed your own discount is
                                    never touched — this only fills in the
                                    blank ones.
                                </p>
                                <InputError message={errors.discount_percent} />
                            </div>
                            <DialogFooter>
                                <DialogClose asChild>
                                    <Button variant="secondary" type="button">
                                        Cancel
                                    </Button>
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

function EditCustomerGroupDialog({ group }: { group: CustomerGroup }) {
    const [open, setOpen] = useState(false);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button size="sm" variant="outline">
                    Edit
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Edit {group.name}</DialogTitle>
                </DialogHeader>
                <Form
                    {...CustomerGroupController.update.form(group.id)}
                    onSuccess={() => setOpen(false)}
                    className="space-y-4"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor={`name-${group.id}`}>Name</Label>
                                <Input
                                    id={`name-${group.id}`}
                                    name="name"
                                    defaultValue={group.name}
                                    required
                                />
                                <InputError message={errors.name} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor={`percent-${group.id}`}>
                                    Discount off unpriced lines (%)
                                </Label>
                                <Input
                                    id={`percent-${group.id}`}
                                    name="discount_percent"
                                    type="number"
                                    step="0.01"
                                    min={0}
                                    max={100}
                                    defaultValue={group.discount_percent}
                                    required
                                />
                                <InputError message={errors.discount_percent} />
                            </div>
                            <div className="flex items-center gap-2">
                                <input
                                    type="checkbox"
                                    id={`active-${group.id}`}
                                    name="is_active"
                                    defaultChecked={group.is_active}
                                    className="size-4"
                                />
                                <Label htmlFor={`active-${group.id}`}>
                                    Active
                                </Label>
                            </div>
                            <p className="text-xs text-muted-foreground">
                                Invoices already issued keep the discount they
                                were saved with.
                            </p>
                            <DialogFooter>
                                <DialogClose asChild>
                                    <Button variant="secondary" type="button">
                                        Close
                                    </Button>
                                </DialogClose>
                                <Button disabled={processing}>Save</Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
