import { Form, Head } from '@inertiajs/react';
import { Check, Pencil } from 'lucide-react';
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

type Plan = {
    id: number;
    name: string;
    slug: string;
    price: string;
    currency: string;
    interval: string | null;
    max_staff: number;
    max_invoices_per_month: number | null;
    features: string[];
    is_active: boolean;
    is_free: boolean;
    subscribers: number;
};

function EditPlanDialog({ plan }: { plan: Plan }) {
    const [open, setOpen] = useState(false);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button size="sm" variant="outline">
                    <Pencil className="size-4" />
                    Edit
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Edit {plan.name}</DialogTitle>
                </DialogHeader>
                <Form
                    {...{ action: `/admin/plans/${plan.id}`, method: 'put' }}
                    onSuccess={() => setOpen(false)}
                    className="space-y-4"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor={`name-${plan.id}`}>
                                    Name
                                </Label>
                                <Input
                                    id={`name-${plan.id}`}
                                    name="name"
                                    defaultValue={plan.name}
                                    required
                                />
                                <InputError message={errors.name} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor={`price-${plan.id}`}>
                                    Price
                                </Label>
                                <Input
                                    id={`price-${plan.id}`}
                                    name="price"
                                    type="number"
                                    step="0.01"
                                    min={0}
                                    defaultValue={plan.price}
                                    required
                                />
                                <InputError message={errors.price} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor={`staff-${plan.id}`}>
                                    Staff limit
                                </Label>
                                <Input
                                    id={`staff-${plan.id}`}
                                    name="max_staff"
                                    type="number"
                                    min={1}
                                    defaultValue={plan.max_staff}
                                    required
                                />
                                <InputError message={errors.max_staff} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor={`invoices-${plan.id}`}>
                                    Invoices per month
                                </Label>
                                <Input
                                    id={`invoices-${plan.id}`}
                                    name="max_invoices_per_month"
                                    type="number"
                                    min={1}
                                    placeholder="Blank for unlimited"
                                    defaultValue={
                                        plan.max_invoices_per_month ?? ''
                                    }
                                />
                                <InputError
                                    message={errors.max_invoices_per_month}
                                />
                            </div>

                            <div className="flex items-center gap-2">
                                <input
                                    type="checkbox"
                                    id={`active-${plan.id}`}
                                    name="is_active"
                                    defaultChecked={plan.is_active}
                                    className="size-4"
                                />
                                <Label htmlFor={`active-${plan.id}`}>
                                    Available to new tenants
                                </Label>
                            </div>

                            <DialogFooter>
                                <DialogClose asChild>
                                    <Button
                                        variant="secondary"
                                        type="button"
                                    >
                                        Cancel
                                    </Button>
                                </DialogClose>
                                <Button disabled={processing}>
                                    {processing ? 'Saving…' : 'Save plan'}
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

export default function AdminPlans({
    plans,
    canManage,
}: {
    plans: Plan[];
    canManage: boolean;
}) {
    return (
        <>
            <Head title="Plans" />

            <div className="space-y-6 p-4 md:p-6">
                <Heading
                    variant="small"
                    title="Plans"
                    description="What each tenant can use. The free plan grants unlimited staff and invoices, so nothing is gated while the product is free."
                />

                <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    {plans.map((plan) => (
                        <Card key={plan.id}>
                            <CardContent className="space-y-3 p-4">
                                <div className="flex items-start justify-between gap-2">
                                    <div>
                                        <p className="font-medium">
                                            {plan.name}
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            {plan.is_free
                                                ? 'Free'
                                                : `${plan.currency ?? 'INR'} ${plan.price}${
                                                      plan.interval
                                                          ? `/${plan.interval}`
                                                          : ''
                                                  }`}
                                        </p>
                                    </div>
                                    <div className="flex flex-col items-end gap-1">
                                        {canManage && (
                                            <EditPlanDialog plan={plan} />
                                        )}
                                        {plan.is_free && (
                                            <Badge variant="default">
                                                free
                                            </Badge>
                                        )}
                                        {!plan.is_active && (
                                            <Badge variant="secondary">
                                                hidden
                                            </Badge>
                                        )}
                                    </div>
                                </div>

                                <dl className="space-y-1 text-xs">
                                    <div className="flex justify-between">
                                        <dt className="text-muted-foreground">
                                            Staff
                                        </dt>
                                        <dd>
                                            {plan.max_staff >= 1000000
                                                ? 'Unlimited'
                                                : plan.max_staff}
                                        </dd>
                                    </div>
                                    <div className="flex justify-between">
                                        <dt className="text-muted-foreground">
                                            Invoices / month
                                        </dt>
                                        <dd>
                                            {plan.max_invoices_per_month ===
                                            null
                                                ? 'Unlimited'
                                                : plan.max_invoices_per_month}
                                        </dd>
                                    </div>
                                    <div className="flex justify-between">
                                        <dt className="text-muted-foreground">
                                            Tenants
                                        </dt>
                                        <dd>{plan.subscribers}</dd>
                                    </div>
                                </dl>

                                <ul className="space-y-1 text-xs">
                                    {(plan.features ?? []).map((feature) => (
                                        <li
                                            key={feature}
                                            className="flex items-start gap-1.5"
                                        >
                                            <Check className="mt-0.5 size-3 shrink-0 text-muted-foreground" />
                                            {feature}
                                        </li>
                                    ))}
                                </ul>
                            </CardContent>
                        </Card>
                    ))}
                </div>
            </div>
        </>
    );
}

AdminPlans.layout = {
    breadcrumbs: [{ title: 'Platform admin', href: '/admin' }],
};