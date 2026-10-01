import { Head, Link, usePage } from '@inertiajs/react';
import {
    ArrowRight,
    BadgeIndianRupee,
    BarChart3,
    Bell,
    Boxes,
    Building2,
    FileText,
    Globe,
    LifeBuoy,
    Package,
    Receipt,
    ScanBarcode,
    ShieldCheck,
    Sparkles,
    Truck,
    UserCog,
} from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import type { Auth } from '@/types/auth';

type Item = {
    icon: typeof Package;
    title: string;
    text: string;
};

const cycle: Item[] = [
    {
        icon: Package,
        title: 'Product catalog',
        text: 'A real catalog, not a spreadsheet. Products, prices, HSN codes, images and stock levels, all searchable and importable from CSV.',
    },
    {
        icon: FileText,
        title: 'Quotations',
        text: 'Build a quote by picking products off the catalog. Share a secure link and let the customer accept or decline — you see every change they make.',
    },
    {
        icon: Truck,
        title: 'Delivery challans',
        text: 'Dispatch against an accepted quotation with a delivery challan, then convert it to an invoice once the work is done.',
    },
    {
        icon: Receipt,
        title: 'GST invoices',
        text: 'Correct CGST/SGST/IGST, HSN codes, discounts, charges, round-off, installments and advance tokens — with PDF and e-invoice IRN/QR.',
    },
    {
        icon: BadgeIndianRupee,
        title: 'Payments & receipts',
        text: 'Record cash, UPI, card or bank transfers against any invoice. Customers can pay from a shared UPI link and get a printable receipt.',
    },
    {
        icon: Bell,
        title: 'Automatic reminders',
        text: 'Overdue invoices, follow-ups, birthdays and anniversaries — chased by SMS or email on a schedule you control, or by hand when someone calls.',
    },
];

const operations: Item[] = [
    {
        icon: Boxes,
        title: 'Stock with a ledger',
        text: 'Opt-in per product. Every movement is recorded, so the balance on hand can always be explained and low stock is flagged.',
    },
    {
        icon: ScanBarcode,
        title: 'Barcode & CSV',
        text: 'Scan or search by barcode, and bulk-import your existing catalogue from a spreadsheet. Exports round-trip losslessly.',
    },
    {
        icon: BarChart3,
        title: 'Reports',
        text: 'Revenue, salesperson performance, GST summary, outstanding balances and payment history — filtered by date, customer or staff, exported to CSV, Excel or PDF.',
    },
    {
        icon: Globe,
        title: 'Customer portal',
        text: 'Customers sign in to their own portal to view and download their invoices, so they stop calling you for copies.',
    },
    {
        icon: UserCog,
        title: 'Roles & permissions',
        text: 'Admin, manager, invoice creator and viewer. Fine-grained permissions decide who can edit, delete, see costs or manage settings.',
    },
    {
        icon: ShieldCheck,
        title: 'Platform admin',
        text: 'An operator view for managing every business on the system: tenants, subscriptions, plans and a full audit trail.',
    },
];

const notFor = [
    'Restaurants and cafés (no POS billing)',
    'Grocery and supermarkets (no barcode POS checkout)',
    'Pharmacies (no batch or expiry compliance)',
    'Manufacturing (no production or BOM handling)',
    'Logistics and fleet operations',
];

/**
 * Supplied by the /features route straight from config/industries.php, so this
 * page lists exactly the trades the product supports.
 */
export type FeatureIndustry = {
    key: string;
    name: string;
    note: string;
};

export default function Features({
    industries,
}: {
    industries: FeatureIndustry[];
}) {
    const { auth } = usePage<{ auth: Auth }>().props;

    return (
        <>
            <Head title="Features — Invoice CRM" />

            <div className="brand-panel min-h-screen text-ivory">
                <header className="mx-auto flex w-full max-w-6xl items-center justify-between px-6 py-5">
                    <Link
                        href="/"
                        className="flex items-center gap-2.5"
                    >
                        <span className="flex size-9 items-center justify-center rounded-full border border-brand/60 bg-brand/10 text-brand">
                            <Package className="size-4" />
                        </span>
                        <span className="font-display text-xl font-semibold tracking-wide">
                            Invoice CRM
                        </span>
                    </Link>

                    <nav className="flex items-center gap-2">
                        {auth.user ? (
                            <Button asChild variant="secondary">
                                <Link href="/dashboard">
                                    Open dashboard
                                </Link>
                            </Button>
                        ) : (
                            <>
                                <Button
                                    asChild
                                    variant="ghost"
                                    className="text-ivory/80 hover:text-ivory"
                                >
                                    <Link href="/contact">Contact</Link>
                                </Button>
                                <Button
                                    asChild
                                    className="bg-brand text-navy-deep hover:bg-brand-light"
                                >
                                    <Link href="/register">
                                        Start free
                                        <ArrowRight className="size-4" />
                                    </Link>
                                </Button>
                            </>
                        )}
                    </nav>
                </header>

                <main className="mx-auto w-full max-w-6xl px-6 pb-24">
                    <section className="pt-10 text-center md:pt-14">
                        <p className="text-xs font-semibold uppercase tracking-[0.24em] text-brand-light">
                            Features
                        </p>
                        <h1 className="mx-auto mt-4 max-w-3xl font-display text-4xl font-medium leading-tight md:text-5xl">
                            Everything a quote-first business needs
                        </h1>
                        <p className="mx-auto mt-4 max-w-2xl text-ivory/70">
                            Not a bare invoicing form. A catalog that knows
                            your products, a quotation the customer can
                            accept, and the follow-through to get paid.
                        </p>
                        <div className="mt-6">
                            <Badge className="bg-brand/15 text-brand-light hover:bg-brand/15">
                                <Sparkles className="size-3" />
                                All of it free — no limits, no trial
                            </Badge>
                        </div>
                    </section>

                    <Section title="The cycle" intro="Each step feeds the next, so nothing is retyped and nothing is forgotten.">
                        {cycle.map((item) => (
                            <FeatureCard key={item.title} {...item} />
                        ))}
                    </Section>

                    <Section
                        title="Running the business"
                        intro="The parts that turn invoicing into an operation."
                    >
                        {operations.map((item) => (
                            <FeatureCard key={item.title} {...item} />
                        ))}
                    </Section>

                    <section className="mt-20">
                        <h2 className="text-center font-display text-2xl md:text-3xl">
                            Works for your trade
                        </h2>
                        <p className="mx-auto mt-2 max-w-xl text-center text-sm text-ivory/60">
                            Each industry gets its own fields, pricing basis
                            and document defaults.
                        </p>

                        <ul className="mt-8 grid gap-3 text-left sm:grid-cols-2">
                            {industries.map((industry) => (
                                <li
                                    key={industry.name}
                                    className="flex items-start gap-3 rounded-xl border border-ivory/12 bg-ivory/[0.04] p-4"
                                >
                                    <Building2 className="mt-0.5 size-4 shrink-0 text-brand" />
                                    <div>
                                        <p className="text-sm font-medium">
                                            {industry.name}
                                        </p>
                                        <p className="text-xs text-ivory/60">
                                            {industry.note}
                                        </p>
                                    </div>
                                </li>
                            ))}
                        </ul>
                    </section>

                    <section className="mt-20 rounded-2xl border border-ivory/12 bg-ivory/[0.03] p-8 md:p-10">
                        <h2 className="font-display text-xl">
                            Not designed for
                        </h2>
                        <p className="mt-2 text-sm text-ivory/65">
                            Being clear about this is more useful than
                            pretending otherwise.
                        </p>
                        <ul className="mt-5 flex flex-wrap gap-2">
                            {notFor.map((item) => (
                                <li
                                    key={item}
                                    className="rounded-full border border-ivory/15 px-3 py-1.5 text-xs text-ivory/60"
                                >
                                    {item}
                                </li>
                            ))}
                        </ul>
                    </section>

                    <section className="mt-20 rounded-2xl border border-brand/30 bg-brand/10 p-8 text-center md:p-12">
                        <h2 className="font-display text-2xl md:text-3xl">
                            Ready when you are
                        </h2>
                        <p className="mx-auto mt-3 max-w-xl text-sm text-ivory/75">
                            Unlimited staff and invoices. Full catalog,
                            stock, quotations, e-invoicing, automation and
                            the customer portal. Nothing to upgrade.
                        </p>
                        {!auth.user && (
                            <Button
                                asChild
                                size="lg"
                                className="mt-6 bg-brand text-navy-deep hover:bg-brand-light"
                            >
                                <Link href="/register">
                                    Create your free account
                                    <ArrowRight className="size-4" />
                                </Link>
                            </Button>
                        )}
                    </section>
                </main>

                <Footer />
            </div>
        </>
    );
}

function Section({
    title,
    intro,
    children,
}: {
    title: string;
    intro: string;
    children: React.ReactNode;
}) {
    return (
        <section className="mt-20">
            <h2 className="text-center font-display text-2xl md:text-3xl">
                {title}
            </h2>
            <p className="mx-auto mt-2 max-w-xl text-center text-sm text-ivory/60">
                {intro}
            </p>
            <div className="mt-8 grid gap-4 text-left sm:grid-cols-2 lg:grid-cols-3">
                {children}
            </div>
        </section>
    );
}

function FeatureCard({ icon: Icon, title, text }: Item) {
    return (
        <div className="rounded-xl border border-ivory/12 bg-ivory/[0.04] p-6">
            <span className="flex size-9 items-center justify-center rounded-lg bg-brand/15 text-brand">
                <Icon className="size-4" />
            </span>
            <h3 className="mt-3 font-display text-lg">{title}</h3>
            <p className="mt-1 text-sm text-ivory/65">{text}</p>
        </div>
    );
}

function Footer() {
    return (
        <footer className="border-t border-ivory/10">
            <div className="mx-auto flex max-w-6xl flex-col items-center justify-between gap-3 px-6 py-6 text-sm text-ivory/55 sm:flex-row">
                <p>© {new Date().getFullYear()} Invoice CRM</p>
                <nav className="flex items-center gap-5">
                    <Link
                        href="/features"
                        className="transition-colors hover:text-ivory"
                    >
                        Features
                    </Link>
                    <Link
                        href="/contact"
                        className="flex items-center gap-1.5 transition-colors hover:text-ivory"
                    >
                        <LifeBuoy className="size-3.5" />
                        Support
                    </Link>
                    <Link
                        href="/login"
                        className="transition-colors hover:text-ivory"
                    >
                        Log in
                    </Link>
                </nav>
            </div>
        </footer>
    );
}

Features.layout = { breadcrumbs: [] };