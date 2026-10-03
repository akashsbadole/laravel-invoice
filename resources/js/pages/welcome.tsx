import { Head, Link, usePage } from '@inertiajs/react';
import {
    ArrowRight,
    BarChart3,
    Bell,
    Boxes,
    FileText,
    LifeBuoy,
    Package,
    Receipt,
    ShieldCheck,
    Sparkles,
    Truck,
} from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import type { Auth } from '@/types/auth';

type Feature = {
    icon: typeof Package;
    title: string;
    text: string;
};

const stages: Feature[] = [
    {
        icon: Package,
        title: 'Catalog',
        text: 'Products with pricing that matches how you sell — per gram, square foot, metre, kg or piece. Stock, images and a searchable catalogue.',
    },
    {
        icon: FileText,
        title: 'Quote',
        text: 'Build quotations straight from the catalog. Share a secure link; the customer accepts or declines and sees every update.',
    },
    {
        icon: Truck,
        title: 'Deliver',
        text: 'Raise a delivery challan against an accepted quotation, then bill it as a GST invoice.',
    },
    {
        icon: Receipt,
        title: 'Collect',
        text: 'Record part or full payments, take UPI payments from a shared link, and issue printable or PDF receipts.',
    },
    {
        icon: Bell,
        title: 'Remind',
        text: 'Automatic SMS and email nudges for overdue invoices, follow-ups, birthdays and anniversaries.',
    },
    {
        icon: BarChart3,
        title: 'Report',
        text: 'Revenue, salesperson, GST, outstanding and payment reports with CSV, Excel and PDF export.',
    },
];

const platform: Feature[] = [
    {
        icon: Sparkles,
        title: 'Built for many trades',
        text: 'Jewelry, hardware, tiles, plumbing, furniture, textiles, electronics, paint and contracting — each with its own fields and pricing.',
    },
    {
        icon: Boxes,
        title: 'Stock you can trust',
        text: 'Every movement is recorded in a ledger, so a balance can always be explained and low stock is flagged.',
    },
    {
        icon: ShieldCheck,
        title: 'GST e-invoicing',
        text: 'IRN and QR generation with prerequisite checks, on invoices and shared PDFs.',
    },
    {
        icon: LifeBuoy,
        title: 'Customer portal',
        text: 'Your customers get their own sign-in to view and download their invoices, without calling you.',
    },
];

export default function Welcome() {
    const { auth } = usePage<{ auth: Auth }>().props;

    return (
        <>
            <Head title="Invoicing for businesses that quote first" />

            <div className="brand-panel min-h-screen text-ivory">
                <header className="mx-auto flex w-full max-w-6xl items-center justify-between px-6 py-5">
                    <span className="flex items-center gap-2.5">
                        <span className="flex size-9 items-center justify-center rounded-full border border-brand/60 bg-brand/10 text-brand">
                            <Package className="size-4" />
                        </span>
                        <span className="font-display text-xl font-semibold tracking-wide">
                            Invoice CRM
                        </span>
                    </span>

                    <nav className="flex items-center gap-2">
                        <Button
                            asChild
                            variant="ghost"
                            className="text-ivory/80 hover:text-ivory"
                        >
                            <Link href="/features">Features</Link>
                        </Button>
                        <Button
                            asChild
                            variant="ghost"
                            className="text-ivory/80 hover:text-ivory"
                        >
                            <Link href="/docs">User Guide &amp; Docs</Link>
                        </Button>
                        <Button
                            asChild
                            variant="ghost"
                            className="text-ivory/80 hover:text-ivory"
                        >
                            <Link href="/contact">Developer Support</Link>
                        </Button>
                        {auth.user ? (
                            <Button asChild variant="secondary">
                                <Link href="/dashboard">Open dashboard</Link>
                            </Button>
                        ) : (
                            <>
                                <Button
                                    asChild
                                    variant="ghost"
                                    className="text-ivory/80 hover:text-ivory"
                                >
                                    <Link href="/login">Log in</Link>
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
                    <section className="pt-10 text-center md:pt-16">
                        <p className="rise-in text-xs font-semibold uppercase tracking-[0.24em] text-brand-light">
                            Catalogue · Quotation · Challan · Invoice · Payment
                        </p>

                        <h1
                            className="rise-in mx-auto mt-4 max-w-3xl font-display text-4xl font-medium leading-tight md:text-6xl"
                            style={{ animationDelay: '80ms' }}
                        >
                            Invoicing built for the way you sell.
                        </h1>

                        <p
                            className="rise-in mx-auto mt-5 max-w-2xl text-ivory/70"
                            style={{ animationDelay: '160ms' }}
                        >
                            Jewelry by the gram, tiles by the square foot,
                            hardware by the piece. Run the whole cycle —
                            catalogue to quotation to invoice to payment —
                            in one place, with a customer portal and
                            reminders that chase themselves.
                        </p>

                        <div
                            className="rise-in mt-7 flex flex-wrap items-center justify-center gap-3"
                            style={{ animationDelay: '240ms' }}
                        >
                            <Badge className="bg-brand/15 text-brand-light hover:bg-brand/15">
                                Every feature free — no limits, no trial
                            </Badge>
                        </div>

                        {!auth.user && (
                            <div
                                className="rise-in mt-8 flex flex-wrap items-center justify-center gap-3"
                                style={{ animationDelay: '280ms' }}
                            >
                                <Button
                                    asChild
                                    size="lg"
                                    className="bg-brand text-navy-deep hover:bg-brand-light"
                                >
                                    <Link href="/register">
                                        Create your free account
                                        <ArrowRight className="size-4" />
                                    </Link>
                                </Button>
                                <Button
                                    asChild
                                    size="lg"
                                    variant="outline"
                                    className="border-ivory/25 bg-transparent text-ivory hover:bg-ivory/10 hover:text-ivory"
                                >
                                    <Link href="/login">Log in</Link>
                                </Button>
                            </div>
                        )}
                    </section>

                    <section className="mt-20">
                        <h2 className="text-center font-display text-2xl md:text-3xl">
                            The whole cycle, in order
                        </h2>
                        <p className="mx-auto mt-2 max-w-xl text-center text-sm text-ivory/60">
                            Every step feeds the next, so nothing is retyped
                            and nothing gets missed.
                        </p>

                        <div className="mt-8 grid gap-4 text-left sm:grid-cols-2 lg:grid-cols-3">
                            {stages.map((stage, i) => (
                                <div
                                    key={stage.title}
                                    className="rise-in rounded-xl border border-ivory/12 bg-ivory/[0.04] p-6"
                                    style={{ animationDelay: `${320 + i * 70}ms` }}
                                >
                                    <span className="flex size-9 items-center justify-center rounded-lg bg-brand/15 text-brand">
                                        <stage.icon className="size-4" />
                                    </span>
                                    <h3 className="mt-3 font-display text-lg">
                                        {stage.title}
                                    </h3>
                                    <p className="mt-1 text-sm text-ivory/65">
                                        {stage.text}
                                    </p>
                                </div>
                            ))}
                        </div>
                    </section>

                    <section className="mt-20">
                        <h2 className="text-center font-display text-2xl md:text-3xl">
                            More than invoicing
                        </h2>

                        <div className="mt-8 grid gap-4 text-left sm:grid-cols-2">
                            {platform.map((feature, i) => (
                                <div
                                    key={feature.title}
                                    className="rise-in rounded-xl border border-ivory/12 bg-ivory/[0.04] p-6"
                                    style={{ animationDelay: `${320 + i * 70}ms` }}
                                >
                                    <span className="flex size-9 items-center justify-center rounded-lg bg-brand/15 text-brand">
                                        <feature.icon className="size-4" />
                                    </span>
                                    <h3 className="mt-3 font-display text-lg">
                                        {feature.title}
                                    </h3>
                                    <p className="mt-1 text-sm text-ivory/65">
                                        {feature.text}
                                    </p>
                                </div>
                            ))}
                        </div>
                    </section>

                    <section className="mt-20 rounded-2xl border border-brand/30 bg-brand/10 p-8 text-center md:p-12">
                        <h2 className="font-display text-2xl md:text-3xl">
                            Free Forever Core + Developer Customization Services
                        </h2>
                        <p className="mx-auto mt-3 max-w-xl text-sm text-ivory/75">
                            Core software is 100% free forever for every shop owner. Need custom print designs, dedicated video call setup, or custom API integrations? Connect with our developer team anytime.
                        </p>

                        <div className="mt-8 grid gap-4 text-left sm:grid-cols-2 max-w-3xl mx-auto">
                            <div className="rounded-xl border border-brand/40 bg-brand/5 p-6">
                                <span className="inline-block px-3 py-1 text-xs font-bold uppercase tracking-wider rounded bg-brand/20 text-brand-light">
                                    Free Forever Core App
                                </span>
                                <h3 className="mt-3 font-display text-lg">Every Shop Feature Included</h3>
                                <ul className="mt-2 space-y-1.5 text-xs text-ivory/75">
                                    <li>✓ Unlimited Quotations, Invoices &amp; Delivery Challans</li>
                                    <li>✓ Customer Self-Service Portal &amp; Link Tracking</li>
                                    <li>✓ 1-Click Quotation-to-Invoice Conversion</li>
                                    <li>✓ Inventory Movements Ledger &amp; Low Stock Flags</li>
                                    <li>✓ Automated Reminders &amp; GST / Tax Reports</li>
                                </ul>
                            </div>

                            <div className="rounded-xl border border-purple-500/40 bg-purple-950/20 p-6">
                                <span className="inline-block px-3 py-1 text-xs font-bold uppercase tracking-wider rounded bg-purple-500/20 text-purple-300">
                                    Developer Paid Add-Ons
                                </span>
                                <h3 className="mt-3 font-display text-lg">Custom Setup &amp; Support</h3>
                                <ul className="mt-2 space-y-1.5 text-xs text-ivory/75">
                                    <li>⚡ Tailored Invoice &amp; Thermal Receipt Layouts</li>
                                    <li>⚡ 1-on-1 Video Call Onboarding &amp; Staff Training</li>
                                    <li>⚡ Custom Payment Gateway &amp; SMS Gateway Integration</li>
                                    <li>⚡ Custom Trade Field Setup &amp; Migration Support</li>
                                </ul>
                            </div>
                        </div>

                        {!auth.user && (
                            <div className="mt-8 flex flex-wrap justify-center gap-3">
                                <Button
                                    asChild
                                    size="lg"
                                    className="bg-brand text-navy-deep hover:bg-brand-light"
                                >
                                    <Link href="/register">
                                        Create Free Account
                                        <ArrowRight className="size-4" />
                                    </Link>
                                </Button>
                                <Button
                                    asChild
                                    size="lg"
                                    variant="outline"
                                    className="border-ivory/25 bg-transparent text-ivory hover:bg-ivory/10 hover:text-ivory"
                                >
                                    <Link href="/contact">Book Developer Consultation</Link>
                                </Button>
                            </div>
                        )}
                    </section>
                </main>

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
                                className="transition-colors hover:text-ivory"
                            >
                                Contact support
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
            </div>
        </>
    );
}

Welcome.layout = {
    breadcrumbs: [],
};