import { Head, router, usePage } from '@inertiajs/react';
import { BadgeIndianRupee, Check, Crown } from 'lucide-react';
import { useState } from 'react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import type { Auth } from '@/types/auth';

declare global {
    interface Window {
        Razorpay?: new (options: Record<string, unknown>) => { open: () => void };
    }
}

type Plan = {
    id: number;
    name: string;
    slug: string;
    price: string;
    currency: string;
    interval: string;
    max_staff: number;
    max_invoices_per_month: number | null;
    features: string[] | null;
};

type Subscription = {
    status: string;
    plan_id: number;
    trial_ends_at: string | null;
    current_period_ends_at: string | null;
} | null;

function loadRazorpay(): Promise<void> {
    if (window.Razorpay) return Promise.resolve();
    return new Promise((resolve, reject) => {
        const script = document.createElement('script');
        script.src = 'https://checkout.razorpay.com/v1/checkout.js';
        script.onload = () => resolve();
        script.onerror = () => reject(new Error('Could not load Razorpay.'));
        document.body.appendChild(script);
    });
}

export default function Billing({
    plans,
    subscription,
    trialDaysLeft,
    usage,
    gatewayConfigured,
    razorpayKeyId,
}: {
    plans: Plan[];
    subscription: Subscription;
    trialDaysLeft: number;
    usage: { staff: number; invoicesThisMonth: number };
    gatewayConfigured: boolean;
    razorpayKeyId: string | null;
}) {
    const { auth } = usePage<{ auth: Auth }>().props;
    const [paying, setPaying] = useState<string | null>(null);

    const currentPlanId = subscription?.plan_id ?? null;

    async function subscribe(plan: Plan) {
        if (!gatewayConfigured || !razorpayKeyId) return;
        setPaying(plan.slug);

        try {
            const csrf = (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '';
            const orderRes = await fetch('/billing/checkout', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    'X-Requested-With': 'XMLHttpRequest',
                    Accept: 'application/json',
                },
                body: JSON.stringify({ plan: plan.slug }),
            });

            if (!orderRes.ok) throw new Error('Could not start checkout.');

            const { order, customer } = (await orderRes.json()) as {
                order: { order_id: string; amount: number; currency: string; key_id: string };
                customer: { name: string; email: string };
            };

            await loadRazorpay();

            if (!window.Razorpay) throw new Error('Could not load Razorpay.');

            new window.Razorpay({
                key: order.key_id,
                amount: order.amount,
                currency: order.currency,
                name: 'Jewelry Invoice',
                description: `${plan.name} plan`,
                order_id: order.order_id,
                prefill: { name: customer.name, email: customer.email },
                handler: (response: { razorpay_order_id: string; razorpay_payment_id: string; razorpay_signature: string }) => {
                    router.post(
                        '/billing/verify',
                        {
                            plan: plan.slug,
                            razorpay_order_id: response.razorpay_order_id,
                            razorpay_payment_id: response.razorpay_payment_id,
                            razorpay_signature: response.razorpay_signature,
                        },
                        { preserveScroll: true },
                    );
                },
            }).open();
        } catch {
            // Errors surface through the toast / validation bag.
        } finally {
            setPaying(null);
        }
    }

    return (
        <>
            <Head title="Billing" />

            <div className="space-y-6 p-4 md:p-6">
                <Heading title="Billing & subscription" description="Plans, trial status and usage for your business." />

                <Card>
                    <CardContent className="flex flex-col gap-2 pt-6 sm:flex-row sm:items-center sm:justify-between">
                        <div className="flex items-center gap-3">
                            <Crown className="size-5 text-muted-foreground" />
                            <div>
                                <p className="font-medium">
                                    {auth.tenant?.subscription?.plan_name ?? 'No plan'}
                                    {subscription?.status === 'trialing' && (
                                        <span className="ml-2 text-sm font-normal text-muted-foreground">
                                            {trialDaysLeft} day{trialDaysLeft === 1 ? '' : 's'} of trial left
                                        </span>
                                    )}
                                </p>
                                <p className="text-sm text-muted-foreground">
                                    {usage.staff} staff · {usage.invoicesThisMonth} invoices this month
                                </p>
                            </div>
                        </div>
                        <Badge variant={subscription && ['trialing', 'active'].includes(subscription.status) ? 'default' : 'destructive'} className="w-fit capitalize">
                            {subscription?.status ?? 'none'}
                        </Badge>
                    </CardContent>
                </Card>

                {!gatewayConfigured && (
                    <p className="rounded-md border border-dashed p-3 text-sm text-muted-foreground">
                        Online payments are not configured yet — contact support to subscribe.
                    </p>
                )}

                <div className="grid gap-4 md:grid-cols-3">
                    {plans.map((plan) => {
                        const isCurrent = plan.id === currentPlanId;
                        const price = Number(plan.price);
                        return (
                            <Card key={plan.slug} className={isCurrent ? 'border-primary' : ''}>
                                <CardHeader>
                                    <CardTitle className="flex items-center justify-between">
                                        {plan.name}
                                        {isCurrent && <Badge>Current</Badge>}
                                    </CardTitle>
                                    <p className="flex items-center gap-1 text-2xl font-bold">
                                        <BadgeIndianRupee className="size-5" />
                                        {price.toLocaleString('en-IN')}
                                        <span className="text-sm font-normal text-muted-foreground">/month</span>
                                    </p>
                                </CardHeader>
                                <CardContent className="space-y-3">
                                    <ul className="space-y-1.5 text-sm text-muted-foreground">
                                        {(plan.features ?? []).map((feature) => (
                                            <li key={feature} className="flex items-start gap-2">
                                                <Check className="mt-0.5 size-4 shrink-0 text-green-600" />
                                                {feature}
                                            </li>
                                        ))}
                                    </ul>
                                    {!isCurrent && (
                                        <Button
                                            className="w-full"
                                            disabled={!gatewayConfigured || paying !== null}
                                            onClick={() => void subscribe(plan)}
                                        >
                                            {paying === plan.slug ? 'Opening checkout…' : `Choose ${plan.name}`}
                                        </Button>
                                    )}
                                </CardContent>
                            </Card>
                        );
                    })}
                </div>
            </div>
        </>
    );
}

Billing.layout = {
    breadcrumbs: [{ title: 'Billing', href: '/billing' }],
};
