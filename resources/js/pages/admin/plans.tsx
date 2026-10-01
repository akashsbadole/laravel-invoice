import { Head } from '@inertiajs/react';
import { Check } from 'lucide-react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';

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

export default function AdminPlans({ plans }: { plans: Plan[] }) {
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