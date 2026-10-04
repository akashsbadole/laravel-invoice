import { Form, Head, Link } from '@inertiajs/react';
import { FileText, Package, Plus } from 'lucide-react';
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
import { create, index, show } from '@/routes/invoices';
import type { Paginated } from '@/types/customer';
import type { Invoice, InvoiceStatus } from '@/types/invoice';
import InvoiceController from '@/actions/App/Http/Controllers/InvoiceController';

const currency = new Intl.NumberFormat('en-IN', { style: 'currency', currency: 'INR', maximumFractionDigits: 0 });

const statusColors: Record<InvoiceStatus, string> = {
    unpaid: 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-200',
    partially_paid: 'bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-300',
    paid: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300',
    overdue: 'bg-red-100 text-red-700 dark:bg-red-950 dark:text-red-300',
    cancelled: 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400',
    refunded: 'bg-blue-100 text-blue-700 dark:bg-blue-950 dark:text-blue-300',
    draft: 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400',
    sent: 'bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-300',
    accepted: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300',
    converted: 'bg-blue-100 text-blue-700 dark:bg-blue-950 dark:text-blue-300',
    closed: 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-200',
};

const documentLabels: Record<string, string> = {
    jewelry_invoice: 'Jewelry',
    general_invoice: 'Sales invoice',
    quotation: 'Quotation',
    delivery_challan: 'Challan',
    credit_note: 'Credit note',
    debit_note: 'Debit note',
};

export default function InvoicesIndex({
    invoices,
    filters,
    usesJewelryDocuments,
}: {
    invoices: Paginated<Invoice>;
    filters: { search?: string; status?: string; document_type?: string };
    usesJewelryDocuments: boolean;
}) {
    const activeTab = filters.document_type || 'all';

    // A tenant that never sells jewelry has no jewelry documents to filter.
    const documentTabs = [
        { value: 'all', label: 'All' },
        ...(usesJewelryDocuments ? [{ value: 'jewelry_invoice', label: 'Jewelry' }] : []),
        { value: 'general_invoice', label: 'Sales invoices' },
        { value: 'quotation', label: 'Quotations' },
        { value: 'delivery_challan', label: 'Challans' },
        { value: 'credit_note', label: 'Credit notes' },
        { value: 'debit_note', label: 'Debit notes' },
    ];

    return (
        <>
            <Head title="Invoices" />

            <div className="space-y-6 p-4 md:p-6">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <Heading title="Invoices" description="Every invoice you've created." />
                    <div className="flex flex-wrap gap-2">
                        <Button asChild variant="outline" className="w-full sm:w-auto">
                            <Link href="/quotations/create">
                                <Package className="size-4" />
                                New quotation
                            </Link>
                        </Button>
                        <Button asChild variant="outline" className="w-full sm:w-auto">
                            <Link href={`${create()}?document_type=quotation`}>
                                <FileText className="size-4" />
                                Blank quotation
                            </Link>
                        </Button>
                        <Button asChild className="w-full sm:w-auto">
                            <Link href={create()}>
                                <Plus className="size-4" />
                                New invoice
                            </Link>
                        </Button>
                    </div>
                </div>

                <div className="flex flex-wrap gap-1">
                    {documentTabs.map((tab) => {
                        const href =
                            tab.value === 'all'
                                ? index()
                                : `${index()}?document_type=${tab.value}`;
                        const isActive = activeTab === tab.value;
                        return (
                            <Link
                                key={tab.value}
                                href={href}
                                preserveScroll
                                className={`rounded-full px-3 py-1 text-sm transition-colors ${
                                    isActive
                                        ? 'bg-primary font-medium text-primary-foreground'
                                        : 'text-muted-foreground hover:bg-muted'
                                }`}
                            >
                                {tab.label}
                            </Link>
                        );
                    })}
                </div>

                <Card>
                    <CardContent>
                        <Form
                            {...InvoiceController.index.form()}
                            options={{ preserveState: true, preserveScroll: true }}
                            className="flex flex-col gap-3 sm:flex-row sm:items-end"
                        >
                            {() => (
                                <>
                                    <div className="grid flex-1 gap-2">
                                        <label htmlFor="search" className="text-sm font-medium">
                                            Search
                                        </label>
                                        <Input
                                            id="search"
                                            name="search"
                                            defaultValue={filters.search}
                                            placeholder="Invoice number or customer"
                                        />
                                    </div>
                                    <div className="grid gap-2 sm:w-48">
                                        <label htmlFor="status" className="text-sm font-medium">
                                            Status
                                        </label>
                                        <Select name="status" defaultValue={filters.status || 'all'}>
                                            <SelectTrigger id="status" className="w-full">
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="all">All statuses</SelectItem>
                                                <SelectItem value="unpaid">Unpaid</SelectItem>
                                                <SelectItem value="partially_paid">Partially paid</SelectItem>
                                                <SelectItem value="paid">Paid</SelectItem>
                                                <SelectItem value="overdue">Overdue</SelectItem>
                                                <SelectItem value="cancelled">Cancelled</SelectItem>
                                                <SelectItem value="refunded">Refunded</SelectItem>
                                                <SelectItem value="closed">Closed</SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>
                                    <Button type="submit">Filter</Button>
                                </>
                            )}
                        </Form>
                    </CardContent>
                </Card>

                {invoices.data.length === 0 ? (
                    <Card>
                        <CardContent className="py-12 text-center text-muted-foreground">
                            No invoices match your filters yet.
                        </CardContent>
                    </Card>
                ) : (
                    <>
                        {/* Desktop / tablet: table */}
                        <Card className="hidden overflow-hidden md:block">
                            <div className="overflow-x-auto">
                                <table className="w-full text-sm">
                                    <thead className="border-b bg-muted/40 text-left text-muted-foreground">
                                        <tr>
                                            <th className="px-4 py-3 font-medium">Invoice</th>
                                            <th className="px-4 py-3 font-medium">Customer</th>
                                            <th className="px-4 py-3 font-medium">Date</th>
                                            <th className="px-4 py-3 font-medium">Status</th>
                                            <th className="px-4 py-3 text-right font-medium">Total</th>
                                            <th className="px-4 py-3 text-right font-medium">Balance</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y">
                                        {invoices.data.map((invoice) => (
                                            <tr key={invoice.id} className="hover:bg-muted/30">
                                                <td className="px-4 py-3 font-mono">
                                                    <Link
                                                        href={show(invoice.id)}
                                                        className="font-semibold text-slate-900 dark:text-slate-100 hover:underline"
                                                    >
                                                        {invoice.invoice_number}
                                                    </Link>
                                                    {invoice.document_type !== 'jewelry_invoice' && (
                                                        <Badge variant="outline" className="ml-2 text-[11px] font-sans">
                                                            {documentLabels[invoice.document_type] ?? invoice.document_type}
                                                        </Badge>
                                                    )}
                                                </td>
                                                <td className="px-4 py-3">{invoice.customer.full_name}</td>
                                                <td className="px-4 py-3">
                                                    {new Date(invoice.invoice_date).toLocaleDateString('en-IN', { day: 'numeric', month: 'short' })}
                                                </td>
                                                <td className="px-4 py-3">
                                                    <Badge className={`capitalize font-medium ${statusColors[invoice.status]}`} variant="secondary">
                                                        {invoice.status.replace('_', ' ')}
                                                    </Badge>
                                                </td>
                                                <td className="px-4 py-3 text-right font-mono font-semibold">
                                                    {currency.format(Number(invoice.grand_total))}
                                                </td>
                                                <td className="px-4 py-3 text-right font-mono">
                                                    {currency.format(Number(invoice.balance_amount))}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </Card>

                        {/* Mobile: cards */}
                        <div className="grid gap-3 md:hidden">
                            {invoices.data.map((invoice) => (
                                <Link key={invoice.id} href={show(invoice.id)}>
                                    <Card className="transition-colors active:bg-muted/40">
                                        <CardContent className="flex items-center justify-between gap-3">
                                            <div className="min-w-0">
                                                <p className="font-medium">{invoice.invoice_number}</p>
                                                <p className="truncate text-sm text-muted-foreground">{invoice.customer.full_name}</p>
                                                <p className="text-xs text-muted-foreground">
                                                    {new Date(invoice.invoice_date).toLocaleDateString('en-IN', { day: 'numeric', month: 'short' })}
                                                </p>
                                                <Badge className={`mt-1 capitalize font-medium ${statusColors[invoice.status]}`} variant="secondary">
                                                    {invoice.status.replace('_', ' ')}
                                                </Badge>
                                            </div>
                                            <div className="shrink-0 text-right">
                                                <p className="text-sm text-muted-foreground">Balance</p>
                                                <p className="font-medium">{currency.format(Number(invoice.balance_amount))}</p>
                                                <p className="text-sm font-semibold">{currency.format(Number(invoice.grand_total))}</p>
                                            </div>
                                        </CardContent>
                                    </Card>
                                </Link>
                            ))}
                        </div>
                    </>
                )}

                {invoices.last_page > 1 && (
                    <nav className="flex flex-wrap items-center justify-center gap-1">
                        {invoices.links.map((link, i) => (
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

InvoicesIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Invoices', href: index() },
    ],
};
