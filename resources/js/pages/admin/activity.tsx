import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

type Entry = {
    id: number;
    action: string;
    action_label: string;
    description: string | null;
    actor: string;
    tenant: string | null;
    ip_address: string | null;
    created_at: string | null;
};

/**
 * The record of who changed what at the platform level. Suspending a tenant
 * or changing a plan is exactly the sort of action an operator needs to be
 * able to review later.
 */
export default function AdminActivity({
    entries,
    filters,
    actions,
}: {
    entries: {
        data: Entry[];
        links: { url: string | null; label: string; active: boolean }[];
        last_page: number;
    };
    filters: { action?: string };
    actions: Record<string, string>;
}) {
    const [search, setSearch] = useState('');

    const visible = entries.data.filter((entry) => {
        const needle = search.trim().toLowerCase();

        return (
            needle === '' ||
            (entry.description ?? '').toLowerCase().includes(needle) ||
            entry.actor.toLowerCase().includes(needle) ||
            (entry.tenant ?? '').toLowerCase().includes(needle)
        );
    });

    return (
        <>
            <Head title="Activity log" />

            <div className="space-y-6 p-4 md:p-6">
                <Heading
                    variant="small"
                    title="Activity log"
                    description="Every platform-level action: tenant status, subscriptions, staff access and impersonation."
                />

                <div className="flex flex-wrap items-center gap-2">
                    <Input
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        placeholder="Filter this page"
                        className="max-w-xs"
                    />
                    <Select
                        value={filters.action ?? ''}
                        onValueChange={(value) =>
                            router.get(
                                '/admin/activity',
                                { action: value },
                                { preserveState: true, replace: true },
                            )
                        }
                    >
                        <SelectTrigger className="w-64">
                            <SelectValue placeholder="All actions" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="">All actions</SelectItem>
                            {Object.entries(actions).map(([value, label]) => (
                                <SelectItem key={value} value={value}>
                                    {label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>

                <Card>
                    <CardContent className="space-y-2 p-4">
                        {visible.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                Nothing recorded yet.
                            </p>
                        ) : (
                            visible.map((entry) => (
                                <div
                                    key={entry.id}
                                    className="flex flex-wrap items-start justify-between gap-3 rounded-md border p-3"
                                >
                                    <div className="min-w-0">
                                        <div className="flex flex-wrap items-center gap-2">
                                            <Badge variant="outline">
                                                {entry.action_label}
                                            </Badge>
                                            {entry.tenant && (
                                                <span className="text-xs text-muted-foreground">
                                                    {entry.tenant}
                                                </span>
                                            )}
                                        </div>
                                        <p className="mt-1 text-sm">
                                            {entry.description ?? entry.action}
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            by {entry.actor}
                                            {entry.ip_address
                                                ? ` · ${entry.ip_address}`
                                                : ''}
                                            {entry.created_at
                                                ? ` · ${entry.created_at}`
                                                : ''}
                                        </p>
                                    </div>
                                </div>
                            ))
                        )}
                    </CardContent>
                </Card>

                {entries.last_page > 1 && (
                    <nav className="flex flex-wrap justify-center gap-1">
                        {entries.links.map((link, i) => (
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

AdminActivity.layout = {
    breadcrumbs: [{ title: 'Platform admin', href: '/admin' }],
};