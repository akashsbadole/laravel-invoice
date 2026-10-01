import { Form, Head, Link } from '@inertiajs/react';
import { ArrowLeft, UserCog } from 'lucide-react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

type Tenant = {
    id: number;
    name: string;
    slug: string;
    industry: string;
    industry_label: string;
    status: string;
    created_at: string | null;
    users_count: number;
    invoices_count: number;
    business: {
        business_name: string | null;
        phone: string | null;
        email: string | null;
        tax_number: string | null;
        gstin: string | null;
    } | null;
    subscription: {
        id: number;
        status: string;
        plan_id: number;
        plan_name: string | null;
        plan_is_free: boolean;
        trial_ends_at: string | null;
        current_period_ends_at: string | null;
    } | null;
};

type TenantUser = {
    id: number;
    name: string;
    email: string;
    role: string;
    role_label: string;
    is_active: boolean;
    last_login_at: string | null;
};

export default function AdminTenantShow({
    tenant,
    users,
    plans,
    invoiceStats,
}: {
    tenant: Tenant;
    users: TenantUser[];
    plans: { id: number; name: string; is_free: boolean }[];
    invoiceStats: {
        count: number;
        billed: number;
        outstanding: number;
        this_month: number;
    };
}) {
    const currency = (value: number) =>
        new Intl.NumberFormat('en-IN', {
            style: 'currency',
            currency: 'INR',
            maximumFractionDigits: 0,
        }).format(value);

    return (
        <>
            <Head title={tenant.name} />

            <div className="space-y-6 p-4 md:p-6">
                <Button size="sm" variant="ghost" asChild>
                    <Link href="/admin/tenants">
                        <ArrowLeft className="size-4" />
                        All tenants
                    </Link>
                </Button>

                <Heading
                    variant="small"
                    title={tenant.name}
                    description={`${tenant.industry_label} · joined ${tenant.created_at ?? 'unknown'}`}
                />

                <div className="flex flex-wrap items-center gap-2">
                    <Badge
                        variant={
                            tenant.status === 'active'
                                ? 'secondary'
                                : 'destructive'
                        }
                    >
                        {tenant.status}
                    </Badge>
                    <Badge variant="outline">{tenant.slug}</Badge>
                    {tenant.subscription?.plan_is_free && (
                        <Badge variant="outline">free plan</Badge>
                    )}
                </div>

                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    {[
                        { label: 'Invoices', value: String(invoiceStats.count) },
                        {
                            label: 'This month',
                            value: String(invoiceStats.this_month),
                        },
                        {
                            label: 'Billed',
                            value: currency(invoiceStats.billed),
                        },
                        {
                            label: 'Outstanding',
                            value: currency(invoiceStats.outstanding),
                        },
                    ].map((stat) => (
                        <Card key={stat.label}>
                            <CardContent className="p-4">
                                <p className="text-xs text-muted-foreground">
                                    {stat.label}
                                </p>
                                <p className="text-xl font-semibold tabular-nums">
                                    {stat.value}
                                </p>
                            </CardContent>
                        </Card>
                    ))}
                </div>

                <div className="grid gap-4 lg:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle>Business details</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-1 text-sm">
                            <p>
                                <span className="text-muted-foreground">
                                    Legal name:
                                </span>{' '}
                                {tenant.business?.business_name ?? '—'}
                            </p>
                            <p>
                                <span className="text-muted-foreground">
                                    Email:
                                </span>{' '}
                                {tenant.business?.email ?? '—'}
                            </p>
                            <p>
                                <span className="text-muted-foreground">
                                    Phone:
                                </span>{' '}
                                {tenant.business?.phone ?? '—'}
                            </p>
                            <p>
                                <span className="text-muted-foreground">
                                    Tax number:
                                </span>{' '}
                                {tenant.business?.tax_number ?? '—'}
                            </p>
                            <p>
                                <span className="text-muted-foreground">
                                    GSTIN:
                                </span>{' '}
                                {tenant.business?.gstin ?? '—'}
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Subscription</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <Form
                                action={`/admin/tenants/${tenant.id}/subscription`}
                                method="put"
                                className="space-y-3"
                            >
                                {({ processing, errors }) => (
                                    <>
                                        <div className="grid gap-2">
                                            <Label htmlFor="plan_id">
                                                Plan
                                            </Label>
                                            <Select
                                                name="plan_id"
                                                defaultValue={String(
                                                    tenant.subscription
                                                        ?.plan_id ?? '',
                                                )}
                                            >
                                                <SelectTrigger id="plan_id">
                                                    <SelectValue />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {plans.map((plan) => (
                                                        <SelectItem
                                                            key={plan.id}
                                                            value={String(
                                                                plan.id,
                                                            )}
                                                        >
                                                            {plan.name}
                                                            {plan.is_free
                                                                ? ' (free)'
                                                                : ''}
                                                        </SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                            {errors.plan_id && (
                                                <p className="text-xs text-destructive">
                                                    {errors.plan_id}
                                                </p>
                                            )}
                                        </div>

                                        <div className="grid gap-2">
                                            <Label htmlFor="status">
                                                Status
                                            </Label>
                                            <Select
                                                name="status"
                                                defaultValue={
                                                    tenant.subscription
                                                        ?.status ?? 'active'
                                                }
                                            >
                                                <SelectTrigger id="status">
                                                    <SelectValue />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {[
                                                        'active',
                                                        'trialing',
                                                        'past_due',
                                                        'cancelled',
                                                    ].map((status) => (
                                                        <SelectItem
                                                            key={status}
                                                            value={status}
                                                        >
                                                            {status}
                                                        </SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                            {errors.status && (
                                                <p className="text-xs text-destructive">
                                                    {errors.status}
                                                </p>
                                            )}
                                        </div>

                                        <div className="grid gap-2">
                                            <Label htmlFor="trial_ends_at">
                                                Trial ends
                                            </Label>
                                            <Input
                                                id="trial_ends_at"
                                                name="trial_ends_at"
                                                type="date"
                                                defaultValue={
                                                    tenant.subscription
                                                        ?.trial_ends_at ?? ''
                                                }
                                            />
                                        </div>

                                        <div className="grid gap-2">
                                            <Label htmlFor="current_period_ends_at">
                                                Current period ends
                                            </Label>
                                            <Input
                                                id="current_period_ends_at"
                                                name="current_period_ends_at"
                                                type="date"
                                                defaultValue={
                                                    tenant.subscription
                                                        ?.current_period_ends_at ??
                                                    ''
                                                }
                                            />
                                        </div>

                                        <Button disabled={processing}>
                                            Save subscription
                                        </Button>
                                    </>
                                )}
                            </Form>
                        </CardContent>
                    </Card>
                </div>

                <Card>
                    <CardHeader className="flex-row items-center justify-between">
                        <CardTitle>
                            Staff ({users.length})
                        </CardTitle>
                        <Form
                            action={`/admin/tenants/${tenant.id}/impersonate`}
                            method="post"
                        >
                            {({ processing }) => (
                                <Button
                                    size="sm"
                                    variant="outline"
                                    disabled={
                                        processing ||
                                        tenant.status !== 'active'
                                    }
                                >
                                    <UserCog className="size-4" />
                                    Impersonate
                                </Button>
                            )}
                        </Form>
                    </CardHeader>
                    <CardContent className="space-y-2">
                        {users.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                This tenant has no staff yet.
                            </p>
                        ) : (
                            users.map((user) => (
                                <div
                                    key={user.id}
                                    className="flex flex-wrap items-center justify-between gap-2 rounded-md border p-3"
                                >
                                    <div>
                                        <p className="text-sm font-medium">
                                            {user.name}
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            {user.email} ·{' '}
                                            {user.role_label}
                                            {user.last_login_at
                                                ? ` · seen ${user.last_login_at}`
                                                : ''}
                                        </p>
                                    </div>
                                    <Badge
                                        variant={
                                            user.is_active
                                                ? 'secondary'
                                                : 'destructive'
                                        }
                                    >
                                        {user.is_active
                                            ? 'active'
                                            : 'disabled'}
                                    </Badge>
                                </div>
                            ))
                        )}
                    </CardContent>
                </Card>

                <Form
                    action={`/admin/tenants/${tenant.id}/toggle-status`}
                    method="post"
                    className="flex justify-end"
                >
                    {({ processing }) => (
                        <Button
                            variant={
                                tenant.status === 'active'
                                    ? 'destructive'
                                    : 'default'
                            }
                            disabled={processing}
                        >
                            {tenant.status === 'active'
                                ? 'Suspend this tenant'
                                : 'Reactivate this tenant'}
                        </Button>
                    )}
                </Form>
            </div>
        </>
    );
}

AdminTenantShow.layout = {
    breadcrumbs: [{ title: 'Platform admin', href: '/admin' }],
};