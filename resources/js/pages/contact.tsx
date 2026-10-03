import { Head, Link, usePage } from '@inertiajs/react';
import {
    ArrowLeft,
    ArrowRight,
    BookOpen,
    LifeBuoy,
    Mail,
    MessageCircle,
    Package,
    Send,
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import type { Auth } from '@/types/auth';

/**
 * Contact details come from config so a deployment can point them at its own
 * inbox without touching the page.
 */
type Props = {
    support: {
        email: string;
        whatsapp: string | null;
        phone: string | null;
        hours: string | null;
    };
};

export default function Contact({ support }: Props) {
    const { auth } = usePage<{ auth: Auth }>().props;

    const channels = [
        support.email && {
            icon: Mail,
            title: 'Email',
            value: support.email,
            href: `mailto:${support.email}`,
            text: 'Best for anything that needs detail. We reply within one working day.',
        },
        support.whatsapp && {
            icon: MessageCircle,
            title: 'WhatsApp',
            value: support.whatsapp,
            href: `https://wa.me/${support.whatsapp.replace(/\D/g, '')}`,
            text: 'Quick questions during business hours.',
        },
        support.phone && {
            icon: LifeBuoy,
            title: 'Phone',
            value: support.phone,
            href: `tel:${support.phone}`,
            text: 'If something is blocking you from billing a customer today.',
        },
        {
            icon: MessageCircle,
            title: '1-on-1 Video Call & Developer Setup',
            value: 'Schedule Onboarding',
            href: support.whatsapp ? `https://wa.me/${support.whatsapp.replace(/\D/g, '')}?text=Hello,%20I%20would%20like%20to%20schedule%20a%201-on-1%20video%20call%20setup%20and%20developer%20customization%20consultation.` : `mailto:${support.email}?subject=Video%20Call%20Onboarding`,
            text: 'Book a 1-on-1 video consultation for custom print designs, staff training, or API integrations.',
        },
    ].filter(Boolean) as {
        icon: typeof Mail;
        title: string;
        value: string;
        href: string;
        text: string;
    }[];

    return (
        <>
            <Head title="Contact support — Invoice CRM" />

            <div className="brand-panel min-h-screen text-ivory">
                <header className="mx-auto flex w-full max-w-6xl items-center justify-between px-6 py-5">
                    <Link href="/" className="flex items-center gap-2.5">
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
                            <Button
                                asChild
                                className="bg-brand text-navy-deep hover:bg-brand-light"
                            >
                                <Link href="/register">
                                    Start free
                                    <ArrowRight className="size-4" />
                                </Link>
                            </Button>
                        )}
                    </nav>
                </header>

                <main className="mx-auto w-full max-w-3xl px-6 pb-24">
                    <Link
                        href="/features"
                        className="inline-flex items-center gap-1.5 text-sm text-ivory/60 transition-colors hover:text-ivory"
                    >
                        <ArrowLeft className="size-3.5" />
                        All features
                    </Link>

                    <section className="pt-10">
                        <p className="text-xs font-semibold uppercase tracking-[0.24em] text-brand-light">
                            Support
                        </p>
                        <h1 className="mt-4 font-display text-4xl font-medium md:text-5xl">
                            Talk to a person
                        </h1>
                        <p className="mt-4 text-ivory/70">
                            Something not behaving, or unsure whether a
                            workflow fits your trade? Ask. You are not
                            filing a ticket into a void.
                        </p>
                    </section>

                    <section className="mt-10 grid gap-4 sm:grid-cols-2">
                        {channels.map((channel) => (
                            <a
                                key={channel.title}
                                href={channel.href}
                                className="rounded-xl border border-ivory/12 bg-ivory/[0.04] p-6 transition-colors hover:border-brand/40 hover:bg-ivory/[0.06]"
                            >
                                <span className="flex size-9 items-center justify-center rounded-lg bg-brand/15 text-brand">
                                    <channel.icon className="size-4" />
                                </span>
                                <h2 className="mt-3 font-display text-lg">
                                    {channel.title}
                                </h2>
                                <p className="mt-0.5 text-sm text-brand-light">
                                    {channel.value}
                                </p>
                                <p className="mt-1.5 text-xs text-ivory/60">
                                    {channel.text}
                                </p>
                            </a>
                        ))}
                    </section>

                    {support.hours && (
                        <Card className="mt-6 border-ivory/12 bg-ivory/[0.04] text-ivory">
                            <CardContent className="flex items-start gap-3 p-5">
                                <LifeBuoy className="mt-0.5 size-4 shrink-0 text-brand" />
                                <p className="text-sm text-ivory/75">
                                    <span className="font-medium text-ivory">
                                        Support hours:
                                    </span>{' '}
                                    {support.hours}
                                </p>
                            </CardContent>
                        </Card>
                    )}

                    <section className="mt-10 rounded-2xl border border-ivory/12 bg-ivory/[0.03] p-6">
                        <h2 className="flex items-center gap-2 font-display text-lg">
                            <BookOpen className="size-4 text-brand" />
                            Before you write
                        </h2>
                        <ul className="mt-3 space-y-2 text-sm text-ivory/65">
                            <li>
                                • Settings → Business is where industry,
                                numbering, tax default, logo and SMS gateway
                                live.
                            </li>
                            <li>
                                • Catalog → CSV template downloads the exact
                                columns an import expects.
                            </li>
                            <li>
                                • Settings → Users controls roles, and who can
                                delete, see costs or change settings.
                            </li>
                            <li>
                                • A super admin can suspend a tenant or fix a
                                subscription from the platform admin panel.
                            </li>
                        </ul>
                    </section>

                    <section className="mt-10 rounded-2xl border border-brand/30 bg-brand/10 p-6 text-center">
                        <Send className="mx-auto size-5 text-brand" />
                        <h2 className="mt-3 font-display text-xl">
                            Still stuck?
                        </h2>
                        <p className="mx-auto mt-2 max-w-md text-sm text-ivory/75">
                            Send us a note with your industry and what you
                            were trying to do. If it is a gap, we would
                            rather add the feature than work around it.
                        </p>
                        {!auth.user && (
                            <Button
                                asChild
                                className="mt-5 bg-brand text-navy-deep hover:bg-brand-light"
                            >
                                <Link href="/register">
                                    Create a free account
                                    <ArrowRight className="size-4" />
                                </Link>
                            </Button>
                        )}
                    </section>
                </main>

                <footer className="border-t border-ivory/10">
                    <div className="mx-auto flex max-w-6xl items-center justify-between gap-3 px-6 py-6 text-sm text-ivory/55">
                        <p>© {new Date().getFullYear()} Invoice CRM</p>
                        <Link
                            href="/features"
                            className="transition-colors hover:text-ivory"
                        >
                            Features
                        </Link>
                    </div>
                </footer>
            </div>
        </>
    );
}

Contact.layout = { breadcrumbs: [] };