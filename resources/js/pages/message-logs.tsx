import { Form, Head, Link } from '@inertiajs/react';
import { MessageSquare } from 'lucide-react';
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
import { dashboard } from '@/routes';
import { index as messageLogsIndex } from '@/routes/message-logs';
import type { Paginated } from '@/types/customer';
import type { MessageLogRow } from '@/types/reminder';

const statusVariant: Record<string, 'default' | 'secondary' | 'destructive'> = {
    sent: 'default',
    logged: 'secondary',
    failed: 'destructive',
};

export default function MessageLogsPage({
    logs,
    filters,
}: {
    logs: Paginated<MessageLogRow>;
    filters: { search?: string; status?: string; channel?: string };
}) {
    const activeStatus = filters.status || 'all';

    return (
        <>
            <Head title="Message history" />

            <div className="space-y-6 p-4 md:p-6">
                <Heading
                    title="Message history"
                    description="Every SMS and WhatsApp message the app attempted."
                />

                <div className="flex flex-wrap gap-1">
                    {['', 'sent', 'failed', 'logged'].map((status) => (
                        <Link
                            key={status || 'all'}
                            href={`${messageLogsIndex().url}${status ? `?status=${status}` : ''}`}
                            className={
                                activeStatus === status
                                    ? 'rounded-md bg-accent px-3 py-1.5 text-sm font-medium text-accent-foreground'
                                    : 'rounded-md px-3 py-1.5 text-sm text-muted-foreground hover:bg-accent/60'
                            }
                        >
                            {status === ''
                                ? 'All'
                                : status.charAt(0).toUpperCase() + status.slice(1)}
                        </Link>
                    ))}
                </div>

                <Form
                    action={`${messageLogsIndex().url}?status=${activeStatus}`}
                    method="get"
                    options={{ preserveState: true }}
                    className="flex gap-2"
                >
                    {() => (
                        <>
                            <Input
                                name="search"
                                defaultValue={filters.search}
                                placeholder="Search number, message or customer"
                            />
                            <Select name="status" defaultValue={activeStatus}>
                                <SelectTrigger className="w-40">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">Any status</SelectItem>
                                    <SelectItem value="sent">Sent</SelectItem>
                                    <SelectItem value="failed">Failed</SelectItem>
                                    <SelectItem value="logged">Logged</SelectItem>
                                </SelectContent>
                            </Select>
                            <Button type="submit" variant="secondary">
                                Search
                            </Button>
                        </>
                    )}
                </Form>

                <Card>
                    <CardContent className="space-y-2 p-4">
                        {logs.data.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                No messages yet.
                            </p>
                        ) : (
                            logs.data.map((log) => (
                                <div
                                    key={log.id}
                                    className="rounded-md border p-3 text-sm"
                                >
                                    <div className="flex flex-wrap items-center justify-between gap-2">
                                        <div className="flex items-center gap-2">
                                            <MessageSquare className="size-4 text-muted-foreground" />
                                            <span className="font-medium">{log.to}</span>
                                            {log.customer && (
                                                <Link
                                                    href={`/customers/${log.customer.id}`}
                                                    className="text-muted-foreground hover:underline"
                                                >
                                                    {log.customer.full_name}
                                                </Link>
                                            )}
                                            {log.invoice && (
                                                <Link
                                                    href={`/invoices/${log.invoice.id}`}
                                                    className="text-muted-foreground hover:underline"
                                                >
                                                    {log.invoice.invoice_number}
                                                </Link>
                                            )}
                                        </div>
                                        <div className="flex items-center gap-2">
                                            <span className="text-xs uppercase text-muted-foreground">
                                                {log.channel} · {log.driver}
                                            </span>
                                            <Badge
                                                variant={
                                                    statusVariant[log.status] ?? 'secondary'
                                                }
                                            >
                                                {log.status}
                                            </Badge>
                                        </div>
                                    </div>
                                    <p className="mt-2 whitespace-pre-line text-muted-foreground">
                                        {log.body}
                                    </p>
                                    {log.error && (
                                        <p className="mt-1 text-xs text-destructive">
                                            {log.error}
                                        </p>
                                    )}
                                    <p className="mt-1 text-xs text-muted-foreground">
                                        {new Date(log.created_at).toLocaleString()}
                                    </p>
                                </div>
                            ))
                        )}
                    </CardContent>
                </Card>

                {logs.links && logs.links.length > 3 && (
                    <div className="flex flex-wrap gap-1">
                        {logs.links.map((link, i) =>
                            link.url ? (
                                <Link
                                    key={i}
                                    href={link.url}
                                    className={
                                        link.active
                                            ? 'rounded-md bg-accent px-3 py-1 text-sm font-medium text-accent-foreground'
                                            : 'rounded-md px-3 py-1 text-sm text-muted-foreground hover:bg-accent/60'
                                    }
                                    dangerouslySetInnerHTML={{ __html: link.label }}
                                />
                            ) : null,
                        )}
                    </div>
                )}
            </div>
        </>
    );
}

MessageLogsPage.layout = {
    breadcrumbs: [{ title: 'Dashboard', href: dashboard() }],
};