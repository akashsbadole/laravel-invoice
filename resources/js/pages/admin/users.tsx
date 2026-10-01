import { Form, Head, router } from '@inertiajs/react';
import { Search } from 'lucide-react';
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

type PlatformUser = {
    id: number;
    name: string;
    email: string;
    role: string;
    role_label: string;
    is_active: boolean;
    tenant: string | null;
    last_login_at: string | null;
};

export default function AdminUsers({
    users,
    filters,
    roles,
}: {
    users: {
        data: PlatformUser[];
        links: { url: string | null; label: string; active: boolean }[];
        last_page: number;
    };
    filters: { search?: string; role?: string; status?: string };
    roles: { value: string; label: string }[];
}) {
    const apply = (patch: Record<string, string>) => {
        router.get(
            '/admin/users',
            { ...filters, ...patch },
            { preserveState: true, replace: true },
        );
    };

    return (
        <>
            <Head title="Users" />

            <div className="space-y-6 p-4 md:p-6">
                <Heading
                    variant="small"
                    title="Users"
                    description="Every account across all tenants, plus the platform super admins."
                />

                <Form
                    action="/admin/users"
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
                            role: String(data.get('role') ?? ''),
                            status: String(data.get('status') ?? ''),
                        });
                    }}
                >
                    <Input
                        name="search"
                        defaultValue={filters.search}
                        placeholder="Search name or email"
                        className="max-w-xs"
                    />
                    <Select
                        name="role"
                        defaultValue={filters.role ?? ''}
                        onValueChange={(value) => apply({ role: value })}
                    >
                        <SelectTrigger className="w-44">
                            <SelectValue placeholder="Role" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="">All roles</SelectItem>
                            {roles.map((role) => (
                                <SelectItem
                                    key={role.value}
                                    value={role.value}
                                >
                                    {role.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <Select
                        name="status"
                        defaultValue={filters.status ?? ''}
                        onValueChange={(value) => apply({ status: value })}
                    >
                        <SelectTrigger className="w-36">
                            <SelectValue placeholder="Status" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="">All</SelectItem>
                            <SelectItem value="active">Active</SelectItem>
                            <SelectItem value="inactive">Disabled</SelectItem>
                        </SelectContent>
                    </Select>
                    <Button type="submit" variant="secondary">
                        <Search className="size-4" />
                        Search
                    </Button>
                </Form>

                <Card>
                    <CardContent className="space-y-2 p-4">
                        {users.data.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                No users match those filters.
                            </p>
                        ) : (
                            users.data.map((user) => {
                                const isPlatform =
                                    user.role === 'super_admin';

                                return (
                                    <div
                                        key={user.id}
                                        className="flex flex-wrap items-center justify-between gap-3 rounded-md border p-3"
                                    >
                                        <div className="min-w-0">
                                            <div className="flex flex-wrap items-center gap-2">
                                                <span className="font-medium">
                                                    {user.name}
                                                </span>
                                                <Badge
                                                    variant={
                                                        isPlatform
                                                            ? 'default'
                                                            : 'outline'
                                                    }
                                                >
                                                    {user.role_label}
                                                </Badge>
                                                {!user.is_active && (
                                                    <Badge variant="destructive">
                                                        disabled
                                                    </Badge>
                                                )}
                                            </div>
                                            <p className="text-xs text-muted-foreground">
                                                {user.email}
                                                {user.tenant
                                                    ? ` · ${user.tenant}`
                                                    : ' · platform'}{' '}
                                                {user.last_login_at
                                                    ? ` · seen ${user.last_login_at}`
                                                    : ''}
                                            </p>
                                        </div>

                                        {!isPlatform && (
                                            <Form
                                                action={`/admin/users/${user.id}/toggle-active`}
                                                method="post"
                                            >
                                                {({ processing }) => (
                                                    <Button
                                                        size="sm"
                                                        variant="outline"
                                                        disabled={processing}
                                                    >
                                                        {user.is_active
                                                            ? 'Disable'
                                                            : 'Enable'}
                                                    </Button>
                                                )}
                                            </Form>
                                        )}
                                    </div>
                                );
                            })
                        )}
                    </CardContent>
                </Card>

                {users.last_page > 1 && (
                    <nav className="flex flex-wrap justify-center gap-1">
                        {users.links.map((link, i) => (
                            <button
                                key={i}
                                type="button"
                                disabled={!link.url}
                                onClick={() =>
                                    link.url && router.get(link.url)
                                }
                                className={`rounded-md px-3 py-1.5 text-sm ${
                                    link.active
                                        ? 'bg-primary text-primary-foreground'
                                        : 'text-muted-foreground hover:bg-muted'
                                } ${!link.url ? 'pointer-events-none opacity-40' : ''}`}
                                dangerouslySetInnerHTML={{
                                    __html: link.label,
                                }}
                            />
                        ))}
                    </nav>
                )}
            </div>
        </>
    );
}

AdminUsers.layout = {
    breadcrumbs: [{ title: 'Platform admin', href: '/admin' }],
};