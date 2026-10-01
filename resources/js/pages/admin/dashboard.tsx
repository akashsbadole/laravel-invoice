import { Head, Link } from '@inertiajs/react';
import {
    Building2,
    CreditCard,
    LifeBuoy,
    Package,
    TrendingUp,
    UserCog,
    Users,
} from 'lucide-react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

type Stat = { label: string; value: string; hint?: string };
type RecentTenant = {
    id: number;
    name: string;
    slug: string;
    industry: string;
    status: string;
    users_count: number;
    created_at: string | null;
};
type PlanRow = {
    id: number;
    name: string;
    slug: string;
    price: string;
    is_free: boolean;
    is_active: boolean;
    subscribers: number;
};

export default function AdminDashboard({
    stats,
    recentTenants,
    plans,
}: {
    stats: {
        tenants: number;
        active_tenants: number;
        suspended_tenants: number;
        users: number;
        super_admins: number;
        invoices: number;
        customers: number;
        on_free_plan: number;
    };
    recentTenants: RecentTenant[];
    plans: PlanRow[];
}) {
    const tiles: Stat[] = [
        { label: 'Tenants', value: String(stats.tenants), hint: `${stats.active_tenants} active` },
        { label: 'Users', value: String(stats.users), hint: `${stats.super_admins} super admins` },
        { label: 'Invoices', value: String(stats.invoices) },
        { label: 'Customers', value: String(stats.customers) },
    ];

    return (
        <>
            <Head title="Platform admin" />

            <div className="space-y-6 p-4 md:p-6">
                <Heading
                    variant="small"
                    title="Platform admin"
                    description="Every business using the application, and the plans they are on."
                />

                <div className="flex flex-wrap gap-2">
                    <Button size="sm" variant="outline" asChild>
                        <Link href="/admin/tenants">
                            <Building2 className="size-4" />
                            Tenants
                        </Link>
                    </Button>
                    <Button size="sm" variant="outline" asChild>
                        <Link href="/admin/users">
                            <UserCog className="size-4" />
                            Users
                        </Link>
                    </Button>
                    <Button size="sm" variant="outline" asChild>
                        <Link href="/admin/plans">
                            <CreditCard className="size-4" />
                            Plans
                        </Link>
                    </Button>
                </div>

                {stats.suspended_tenants > 0 && (
                    <div className="flex items-center gap-2 rounded-md border border-destructive/40 bg-destructive/5 p-3 text-sm">
                        <LifeBuoy className="size-4 text-destructive" />
                        {stats.suspended_tenants} suspended tenant(s) cannot sign
                        in.
                    </div>
                )}

                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    {tiles.map((tile) => (
                        <Card key={tile.label}>
                            <CardContent className="p-4">
                                <p className="text-xs text-muted-foreground">
                                    {tile.label}
                                </p>
                                <p className="text-2xl font-semibold tabular-nums">
                                    {tile.value}
                                </p>
                                {tile.hint && (
                                    <p className="text-xs text-muted-foreground">
                                        {tile.hint}
                                    </p>
                                )}
                            </CardContent>
                        </Card>
                    ))}
                </div>

                <div className="grid gap-4 lg:grid-cols-2">
                    <Card>
                        <CardHeader className="flex-row items-center justify-between">
                            <CardTitle>Newest tenants</CardTitle>
                            <Button size="sm" variant="ghost" asChild>
                                <Link href="/admin/tenants">View all</Link>
                            </Button>
                        </CardHeader>
                        <CardContent className="space-y-2">
                            {recentTenants.length === 0 ? (
                                <p className="text-sm text-muted-foreground">
                                    No tenants yet.
                                </p>
                            ) : (
                                recentTenants.map((tenant) => (
                                    <Link
                                        key={tenant.id}
                                        href={`/admin/tenants/${tenant.id}`}
                                        className="flex items-center justify-between rounded-md border p-3 hover:bg-muted/50"
                                    >
                                        <div className="min-w-0">
                                            <p className="truncate text-sm font-medium">
                                                {tenant.name}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                {tenant.industry} ·{' '}
                                                {tenant.users_count} staff
                                            </p>
                                        </div>
                                        <Badge
                                            variant={
                                                tenant.status === 'active'
                                                    ? 'secondary'
                                                    : 'destructive'
                                            }
                                        >
                                            {tenant.status}
                                        </Badge>
                                    </Link>
                                ))
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex-row items-center justify-between">
                            <CardTitle>Plans</CardTitle>
                            <TrendingUp className="size-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent className="space-y-2">
                            {plans.map((plan) => (
                                <div
                                    key={plan.id}
                                    className="flex items-center justify-between rounded-md border p-3"
                                >
                                    <div className="flex items-center gap-2">
                                        <Package className="size-4 text-muted-foreground" />
                                        <div>
                                            <p className="text-sm font-medium">
                                                {plan.name}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                {plan.is_free
                                                    ? 'Free'
                                                    : `₹${plan.price}`}{' '}
                                                · {plan.subscribers} tenant(s)
                                            </p>
                                        </div>
                                    </div>
                                    {!plan.is_active && (
                                        <Badge variant="secondary">
                                            hidden
                                        </Badge>
                                    )}
                                </div>
                            ))}
                        </CardContent>
                    </Card>
                </div>

                {stats.on_free_plan > 0 && (
                    <p className="flex items-center gap-2 text-xs text-muted-foreground">
                        <Users className="size-4" />
                        {stats.on_free_plan} tenant(s) are on the free plan, which
                        grants unlimited staff and invoices.
                    </p>
                )}
            </div>
        </>
    );
}

AdminDashboard.layout = {
    breadcrumbs: [{ title: 'Dashboard', href: '/dashboard' }],
};