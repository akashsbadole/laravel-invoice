import { Form, Head, Link } from '@inertiajs/react';
import Heading from '@/components/heading';
import ActivityLogController from '@/actions/App/Http/Controllers/Settings/ActivityLogController';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { Paginated } from '@/types/customer';

type LogRow = {
    id: number;
    action: string;
    description: string | null;
    created_at: string;
    user: { id: number; name: string } | null;
};

export default function ActivityLogPage({
    logs,
    filters,
    users,
}: {
    logs: Paginated<LogRow>;
    filters: { search?: string; user_id?: string };
    users: { id: number; name: string }[];
}) {
    return (
        <>
            <Head title="Activity log" />

            <div className="space-y-6">
                <Heading variant="small" title="Activity log" description="Who did what, across the whole app" />

                <Form
                    {...ActivityLogController.index.form()}
                    options={{ preserveState: true }}
                    className="flex flex-col gap-3 sm:flex-row"
                >
                    {() => (
                        <>
                            <Input name="search" defaultValue={filters.search} placeholder="Search action or description" className="flex-1" />
                            <Select name="user_id" defaultValue={filters.user_id || 'all'}>
                                <SelectTrigger className="w-full sm:w-48">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">Everyone</SelectItem>
                                    {users.map((u) => (
                                        <SelectItem key={u.id} value={String(u.id)}>{u.name}</SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <Button type="submit">Filter</Button>
                        </>
                    )}
                </Form>

                <Card>
                    <CardContent className="divide-y p-0">
                        {logs.data.length === 0 ? (
                            <p className="p-4 text-sm text-muted-foreground">No activity recorded yet.</p>
                        ) : (
                            logs.data.map((log) => (
                                <div key={log.id} className="flex items-center justify-between gap-2 p-3 text-sm">
                                    <div>
                                        <p>{log.description ?? log.action}</p>
                                        <p className="text-xs text-muted-foreground">
                                            {log.user?.name ?? 'System'} · {log.action}
                                        </p>
                                    </div>
                                    <span className="shrink-0 text-xs text-muted-foreground">
                                        {new Date(log.created_at).toLocaleString()}
                                    </span>
                                </div>
                            ))
                        )}
                    </CardContent>
                </Card>

                {logs.last_page > 1 && (
                    <nav className="flex flex-wrap justify-center gap-1">
                        {logs.links.map((link, i) => (
                            <Link
                                key={i}
                                href={link.url ?? '#'}
                                preserveScroll
                                className={`rounded-md px-3 py-1.5 text-sm ${
                                    link.active ? 'bg-primary text-primary-foreground' : 'text-muted-foreground hover:bg-muted'
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
