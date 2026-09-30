import { Form, Head, Link } from '@inertiajs/react';
import { Receipt } from 'lucide-react';
import Heading from '@/components/heading';
import PaymentController from '@/actions/App/Http/Controllers/PaymentController';
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
import { dashboard } from '@/routes';
import { index as paymentsIndex, receipt } from '@/routes/payments';
import type { Paginated } from '@/types/customer';
import type { PaymentRow } from '@/types/payment';

const currency = new Intl.NumberFormat('en-IN', { style: 'currency', currency: 'INR', maximumFractionDigits: 2 });

export default function PaymentsIndex({
    payments,
    total,
    filters,
}: {
    payments: Paginated<PaymentRow>;
    total: number;
    filters: { search?: string; method?: string; from?: string; to?: string };
}) {
    return (
        <>
            <Head title="Payments" />

            <div className="space-y-6 p-4 md:p-6">
                <Heading title="Payments" description={`${currency.format(total)} collected across these filters`} />

                <Card>
                    <CardContent>
                        <Form
                            {...PaymentController.index.form()}
                            options={{ preserveState: true }}
                            className="grid gap-3 sm:grid-cols-2 lg:grid-cols-5"
                        >
                            {() => (
                                <>
                                    <div className="grid gap-1.5 lg:col-span-2">
                                        <label className="text-sm font-medium">Search</label>
                                        <Input name="search" defaultValue={filters.search} placeholder="Invoice, customer, reference" />
                                    </div>
                                    <div className="grid gap-1.5">
                                        <label className="text-sm font-medium">Method</label>
                                        <Select name="method" defaultValue={filters.method || 'all'}>
                                            <SelectTrigger className="w-full">
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="all">All methods</SelectItem>
                                                <SelectItem value="cash">Cash</SelectItem>
                                                <SelectItem value="bank_transfer">Bank transfer</SelectItem>
                                                <SelectItem value="card">Card</SelectItem>
                                                <SelectItem value="upi">UPI</SelectItem>
                                                <SelectItem value="cheque">Cheque</SelectItem>
                                                <SelectItem value="other">Other</SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>
                                    <div className="grid gap-1.5">
                                        <label className="text-sm font-medium">From</label>
                                        <Input type="date" name="from" defaultValue={filters.from} />
                                    </div>
                                    <div className="grid gap-1.5">
                                        <label className="text-sm font-medium">To</label>
                                        <Input type="date" name="to" defaultValue={filters.to} />
                                    </div>
                                    <div className="flex items-end lg:col-span-5">
                                        <Button type="submit">Filter</Button>
                                    </div>
                                </>
                            )}
                        </Form>
                    </CardContent>
                </Card>

                <Card className="overflow-hidden">
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead className="border-b bg-muted/40 text-left text-muted-foreground">
                                <tr>
                                    <th className="px-3 py-2 font-medium">Date</th>
                                    <th className="px-3 py-2 font-medium">Invoice</th>
                                    <th className="px-3 py-2 font-medium">Customer</th>
                                    <th className="px-3 py-2 font-medium">Method</th>
                                    <th className="px-3 py-2 font-medium">Received by</th>
                                    <th className="px-3 py-2 text-right font-medium">Amount</th>
                                    <th className="px-3 py-2 text-right font-medium">Receipt</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {payments.data.length === 0 ? (
                                    <tr><td colSpan={7} className="px-3 py-8 text-center text-muted-foreground">No payments found.</td></tr>
                                ) : (
                                    payments.data.map((p) => (
                                        <tr key={p.id} className="hover:bg-muted/30">
                                            <td className="px-3 py-2">{new Date(p.payment_date).toLocaleDateString()}</td>
                                            <td className="px-3 py-2">
                                                {p.invoice ? (
                                                    <Link href={`/invoices/${p.invoice.id}`} className="hover:underline">
                                                        {p.invoice.invoice_number}
                                                    </Link>
                                                ) : '-'}
                                            </td>
                                            <td className="px-3 py-2">{p.invoice?.customer.full_name ?? '-'}</td>
                                            <td className="px-3 py-2 capitalize">{p.payment_method.replace('_', ' ')}</td>
                                            <td className="px-3 py-2">{p.receiver?.name ?? '-'}</td>
                                            <td className="px-3 py-2 text-right font-medium">{currency.format(Number(p.amount))}</td>
                                            <td className="px-3 py-2 text-right">
                                                <a href={receipt(p.id).url} target="_blank" rel="noreferrer" className="inline-flex text-muted-foreground hover:text-foreground">
                                                    <Receipt className="size-4" />
                                                </a>
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>
                </Card>

                {payments.last_page > 1 && (
                    <nav className="flex flex-wrap justify-center gap-1">
                        {payments.links.map((link, i) => (
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

PaymentsIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Payments', href: paymentsIndex() },
    ],
};
