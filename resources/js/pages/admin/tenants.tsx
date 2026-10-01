import { Form, Head, Link, router } from '@inertiajs/react';
import { Search, UserCog } from 'lucide-react';
import { useState } from 'react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

type TenantRow = {
    id: number;
    name: string;
    slug: string;
    industry: string;
    industry_label: string;
    status: string;
    users_count: number;
    plan: string | null;
    plan_is_free: boolean;
    subscription_status: string | null;
    created_at: string | null;
};

export default function AdminTenants({
    tenants,
    filters,
    statuses,
    industries,
}: {
    tenants: {
        data: TenantRow[];
        links: { url: string | null; label: string; active: boolean }[];
        last_page: number;
    };
    filters: { search?: string; status?: string; industry?: string };
    statuses: string[];
    industries: { key: string; label: string }[];
}) {
    const [impersonating, setImpersonating] = useState<number | null>(null);

    const apply = (patch: Record<string, string>) => {
        router.get(
            '/admin/tenants',
            { ...filters, ...patch },
            { preserveState: true, replace: true },
        );
    };

    return (
        <>
            <Head title="Tenants" />

            <div className="space-y-6 p-4 md:p-6">
                <Heading
                    variant="small"
                    title="Tenants"
                    description="Every business registered on the application."
                />

                <Form
                    action="/admin/tenants"
                    method="get"
                    options={{ preserveState: true }}
                    className="flex flex-wrap items-center gap-2"
                    onSubmit={(e) => {
                        e.preventDefault();
                        const data = new FormData(
                            e.currentTarget as HTMLFormElement,
                        );
                        apply({
                            search: String(data.get('search') ?? ''),
                            status: String(data.get('status') ?? ''),
                            industry: String(data.get('industry') ?? ''),
                        });
                    }}
                >
                    <Input
                        name="search"
                        defaultValue={filters.search}
                        placeholder="Search name or slug"
                        className="max-w-xs"
                    />
                    <Select
                        name="status"
                        defaultValue={filters.status ?? ''}
                        onValueChange={(value) => apply({ status: value })}
                    >
                        <SelectTrigger className="w-36">
                            <SelectValue placeholder="Status" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="">All statuses</SelectItem>
                            {statuses.map((status) => (
                                <SelectItem key={status} value={status}>
                                    {status}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <Select
                        name="industry"
                        defaultValue={filters.industry ?? ''}
                        onValueChange={(value) => apply({ industry: value })}
                    >
                        <SelectTrigger className="w-52">
                            <SelectValue placeholder="Industry" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="">All industries</SelectItem>
                            {industries.map((industry) => (
                                <SelectItem
                                    key={industry.key}
                                    value={industry.key}
                                >
                                    {industry.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <Button type="submit" variant="secondary">
                        <Search className="size-4" />
                        Search
                    </Button>
                </Form>

                <Card>
                    <CardContent className="space-y-2 p-4">
                        {tenants.data.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                No tenants match those filters.
                            </p>
                        ) : (
                            tenants.data.map((tenant) => (
                                <div
                                    key={tenant.id}
                                    className="flex flex-wrap items-center justify-between gap-3 rounded-md border p-3"
                                >
                                    <div className="min-w-0">
                                        <div className="flex flex-wrap items-center gap-2">
                                            <Link
                                                href={`/admin/tenants/${tenant.id}`}
                                                className="font-medium hover:underline"
                                            >
                                                {tenant.name}
                                            </Link>
                                            <Badge
                                                variant={
                                                    tenant.status === 'active'
                                                        ? 'secondary'
                                                        : 'destructive'
                                                }
                                            >
                                                {tenant.status}
                                            </Badge>
                                            {tenant.plan_is_free && (
                                                <Badge variant="outline">
                                                    free
                                                </Badge>
                                            )}
                                        </div>
                                        <p className="text-xs text-muted-foreground">
                                            {tenant.industry_label} ·{' '}
                                            {tenant.users_count} staff ·{' '}
                                            {tenant.plan ?? 'no plan'}
                                            {tenant.created_at
                                                ? ` · joined ${tenant.created_at}`
                                                : ''}
                                        </p>
                                    </div>

                                    <div className="flex items-center gap-2">
                                        <Button
                                            size="sm"
                                            variant="ghost"
                                            disabled={
                                                impersonating === tenant.id
                                            }
                                            onClick={() => {
                                                setImpersonating(tenant.id);
                                                router.post(
                                                    `/admin/tenants/${tenant.id}/impersonate`,
                                                    {},
                                                    {
                                                        onFinish: () =>
                                                            setImpersonating(
                                                                null,
                                                            ),
                                                    },
                                                );
                                            }}
                                        >
                                            <UserCog className="size-4" />
                                            Impersonate
                                        </Button>
                                        <Form
                                            action={`/admin/tenants/${tenant.id}/toggle-status`}
                                            method="post"
                                        >
                                            {({ processing }) => (
                                                <Button
                                                    size="sm"
                                                    variant="outline"
                                                    disabled={processing}
                                                >
                                                    {tenant.status === 'active'
                                                        ? 'Suspend'
                                                        : 'Reactivate'}
                                                </Button>
                                            )}
                                        </Form>
                                    </div>
                                </div>
                            ))
                        )}
                    </CardContent>
                </Card>

                {tenants.last_page > 1 && (
                    <nav className="flex flex-wrap justify-center gap-1">
                        {tenants.links.map((link, i) => (
                            <Link
                                key={i}
                                href={link.url ?? '#'}
                                preserveScroll
                                className={`rounded-md px-3 py-1.5 text-sm ${
                                    link.active
                                        ? 'bg-primary text-primary-foreground'
                                        : 'text-muted-foreground hover:bg-muted'
                                } ${!link.url ? 'pointer-events-none opacity-40' : ''}`}
                                dangerouslySetInnerHTML={{ __html: link.label }}
                            />
                        ))}
                    </nav>
                )}
            </div>
        </>
    );
}

AdminTenants.layout = {
    breadcrumbs: [{ title: 'Platform admin', href: '/admin' }],
};