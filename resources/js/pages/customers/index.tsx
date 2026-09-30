import { Form, Head, Link } from '@inertiajs/react';
import { Download, Plus, Search } from 'lucide-react';
import CustomerController from '@/actions/App/Http/Controllers/CustomerController';
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
import { create, download, index, show } from '@/routes/customers';
import { dashboard } from '@/routes';
import type { Customer, CustomerFilters, Paginated, Staff } from '@/types/customer';

const currency = new Intl.NumberFormat('en-IN', {
    style: 'currency',
    currency: 'INR',
    maximumFractionDigits: 0,
});

function money(value?: string | null) {
    return currency.format(Number(value ?? 0));
}

export default function CustomersIndex({
    customers,
    filters,
    staff,
}: {
    customers: Paginated<Customer>;
    filters: CustomerFilters;
    staff: Staff[];
}) {
    return (
        <>
            <Head title="Customers" />

            <div className="space-y-6 p-4 md:p-6">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <Heading
                        title="Customers"
                        description="Everyone you've invoiced or plan to."
                    />
                    <div className="flex gap-2">
                        <Button variant="outline" asChild className="flex-1 sm:flex-none">
                            <a href={download({ query: filters }).url}>
                                <Download className="size-4" />
                                Export
                            </a>
                        </Button>
                        <Button asChild className="flex-1 sm:flex-none">
                            <Link href={create()}>
                                <Plus className="size-4" />
                                New customer
                            </Link>
                        </Button>
                    </div>
                </div>

                <Card>
                    <CardContent>
                        <Form
                            {...CustomerController.index.form()}
                            options={{ preserveState: true, preserveScroll: true }}
                            className="flex flex-col gap-3 sm:flex-row sm:items-end"
                        >
                            {() => (
                                <>
                                    <div className="grid flex-1 gap-2">
                                        <label
                                            htmlFor="search"
                                            className="text-sm font-medium"
                                        >
                                            Search
                                        </label>
                                        <div className="relative">
                                            <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                                            <Input
                                                id="search"
                                                name="search"
                                                defaultValue={filters.search}
                                                placeholder="Name, mobile, or email"
                                                className="pl-9"
                                            />
                                        </div>
                                    </div>

                                    <div className="grid gap-2 sm:w-44">
                                        <label
                                            htmlFor="customer_type"
                                            className="text-sm font-medium"
                                        >
                                            Type
                                        </label>
                                        <Select
                                            name="customer_type"
                                            defaultValue={filters.customer_type || 'all'}
                                        >
                                            <SelectTrigger id="customer_type" className="w-full">
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="all">All types</SelectItem>
                                                <SelectItem value="individual">Individual</SelectItem>
                                                <SelectItem value="business">Business</SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>

                                    <div className="grid gap-2 sm:w-48">
                                        <label
                                            htmlFor="assigned_staff_id"
                                            className="text-sm font-medium"
                                        >
                                            Staff
                                        </label>
                                        <Select
                                            name="assigned_staff_id"
                                            defaultValue={
                                                filters.assigned_staff_id
                                                    ? String(filters.assigned_staff_id)
                                                    : 'all'
                                            }
                                        >
                                            <SelectTrigger id="assigned_staff_id" className="w-full">
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="all">Everyone</SelectItem>
                                                {staff.map((member) => (
                                                    <SelectItem
                                                        key={member.id}
                                                        value={String(member.id)}
                                                    >
                                                        {member.name}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    </div>

                                    <Button type="submit">Filter</Button>
                                </>
                            )}
                        </Form>
                    </CardContent>
                </Card>

                {customers.data.length === 0 ? (
                    <Card>
                        <CardContent className="py-12 text-center text-muted-foreground">
                            No customers match your filters yet.
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
                                            <th className="px-4 py-3 font-medium">Name</th>
                                            <th className="px-4 py-3 font-medium">Mobile</th>
                                            <th className="px-4 py-3 font-medium">Type</th>
                                            <th className="px-4 py-3 font-medium">Staff</th>
                                            <th className="px-4 py-3 text-right font-medium">
                                                Invoiced
                                            </th>
                                            <th className="px-4 py-3 text-right font-medium">
                                                Outstanding
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y">
                                        {customers.data.map((customer) => (
                                            <tr key={customer.id} className="hover:bg-muted/30">
                                                <td className="px-4 py-3">
                                                    <Link
                                                        href={show(customer.id)}
                                                        className="font-medium hover:underline"
                                                    >
                                                        {customer.full_name}
                                                    </Link>
                                                </td>
                                                <td className="px-4 py-3">
                                                    {customer.mobile_number}
                                                </td>
                                                <td className="px-4 py-3">
                                                    <Badge variant="outline" className="capitalize">
                                                        {customer.customer_type}
                                                    </Badge>
                                                </td>
                                                <td className="px-4 py-3">
                                                    {customer.assigned_staff?.name ?? (
                                                        <span className="text-muted-foreground">
                                                            Unassigned
                                                        </span>
                                                    )}
                                                </td>
                                                <td className="px-4 py-3 text-right">
                                                    {money(customer.total_invoiced)}
                                                </td>
                                                <td className="px-4 py-3 text-right">
                                                    {money(customer.total_outstanding)}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </Card>

                        {/* Mobile: cards */}
                        <div className="grid gap-3 md:hidden">
                            {customers.data.map((customer) => (
                                <Link key={customer.id} href={show(customer.id)}>
                                    <Card className="transition-colors active:bg-muted/40">
                                        <CardContent className="flex items-center justify-between gap-3">
                                            <div className="min-w-0">
                                                <p className="truncate font-medium">
                                                    {customer.full_name}
                                                </p>
                                                <p className="text-sm text-muted-foreground">
                                                    {customer.mobile_number}
                                                </p>
                                                <Badge
                                                    variant="outline"
                                                    className="mt-1 capitalize"
                                                >
                                                    {customer.customer_type}
                                                </Badge>
                                            </div>
                                            <div className="shrink-0 text-right">
                                                <p className="text-sm text-muted-foreground">
                                                    Outstanding
                                                </p>
                                                <p className="font-medium">
                                                    {money(customer.total_outstanding)}
                                                </p>
                                            </div>
                                        </CardContent>
                                    </Card>
                                </Link>
                            ))}
                        </div>
                    </>
                )}

                {customers.last_page > 1 && (
                    <nav className="flex flex-wrap items-center justify-center gap-1">
                        {customers.links.map((link, i) => (
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

CustomersIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Customers', href: index() },
    ],
};
