import { Head, Link, usePage } from '@inertiajs/react';
import { ArrowRight, FileText, Package, QrCode, Send } from 'lucide-react';
import { Button } from '@/components/ui/button';
import type { Auth } from '@/types/auth';

const steps = [
    { icon: FileText, title: 'Create', text: 'Customers, quotations & GST invoices from your product catalog' },
    { icon: QrCode, title: 'Share', text: 'Secure links & PDFs with QR verification' },
    { icon: Send, title: 'Collect', text: 'Payments, receipts & reminders on autopilot' },
];

export default function Welcome() {
    const { auth } = usePage<{ auth: Auth }>().props;

    return (
        <>
            <Head title="Welcome" />

            <div className="brand-glow min-h-screen text-ivory">
                <header className="mx-auto flex w-full max-w-6xl items-center justify-between px-6 py-5">
                    <span className="flex items-center gap-2.5">
                        <span className="flex size-9 items-center justify-center rounded-full border border-gold/60 bg-gold/10 text-gold">
                            <Package className="size-4" />
                        </span>
                        <span className="font-display text-xl font-semibold tracking-wide">
                            Invoice CRM
                        </span>
                    </span>
                    <div className="flex items-center gap-2">
                        {auth.user ? (
                            <Button asChild variant="secondary">
                                <Link href="/dashboard">Open dashboard</Link>
                            </Button>
                        ) : (
                            <>
                                <Button asChild variant="ghost" className="text-ivory/80 hover:text-ivory">
                                    <Link href="/login">Log in</Link>
                                </Button>
                                <Button asChild className="bg-gold text-emeraldink-deep hover:bg-gold-light">
                                    <Link href="/register">
                                        Start free trial
                                        <ArrowRight className="size-4" />
                                    </Link>
                                </Button>
                            </>
                        )}
                    </div>
                </header>

                <main className="mx-auto w-full max-w-6xl px-6 pb-20 pt-10 text-center md:pt-16">
                    <p className="rise-in text-xs font-semibold uppercase tracking-[0.24em] text-gold-light">
                        Estimates · GST invoices · Payments
                    </p>
                    <h1
                        className="rise-in mx-auto mt-4 max-w-3xl font-display text-4xl font-medium leading-tight md:text-6xl"
                        style={{ animationDelay: '80ms' }}
                    >
                        Invoicing built for the way you sell.
                    </h1>
                    <p className="rise-in mx-auto mt-5 max-w-xl text-ivory/70" style={{ animationDelay: '160ms' }}>
                        Jewelry by the gram, tiles by the square foot, hardware by
                        the piece. Create customers, build quotations and invoices,
                        share secure PDF links, and track every rupee.
                    </p>
                    {!auth.user && (
                        <div className="rise-in mt-8 flex flex-wrap items-center justify-center gap-3" style={{ animationDelay: '240ms' }}>
                            <Button asChild size="lg" className="bg-gold text-emeraldink-deep hover:bg-gold-light">
                                <Link href="/register">
                                    Start 14-day free trial
                                    <ArrowRight className="size-4" />
                                </Link>
                            </Button>
                            <Button asChild size="lg" variant="outline" className="border-ivory/25 bg-transparent text-ivory hover:bg-ivory/10 hover:text-ivory">
                                <Link href="/login">Log in</Link>
                            </Button>
                        </div>
                    )}

                    <div className="mt-16 grid gap-4 text-left md:grid-cols-3">
                        {steps.map((step, i) => (
                            <div
                                key={step.title}
                                className="rise-in rounded-xl border border-ivory/12 bg-ivory/[0.04] p-6 backdrop-blur"
                                style={{ animationDelay: `${320 + i * 100}ms` }}
                            >
                                <step.icon className="size-5 text-gold" />
                                <h2 className="mt-3 font-display text-xl">{step.title}</h2>
                                <p className="mt-1 text-sm text-ivory/65">{step.text}</p>
                            </div>
                        ))}
                    </div>

                    <p className="mt-14 text-xs uppercase tracking-[0.2em] text-ivory/40">
                        No inventory · No barcode billing · No POS screens
                    </p>
                </main>
            </div>
        </>
    );
}
