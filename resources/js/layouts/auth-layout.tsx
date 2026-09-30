import * as React from 'react';
import { Link } from '@inertiajs/react';
import { BadgeCheck, FileText, Share2 } from 'lucide-react';
import { AppLogo } from '@/components/app-logo';
import { FlashToaster } from '@/components/flash-toaster';

interface AuthLayoutProps {
    children: React.ReactNode;
}

const highlights = [
    { icon: FileText, text: 'Jewelry invoices with weight, rate & making-charge math' },
    { icon: Share2, text: 'Secure customer links with PDF download & tracking' },
    { icon: BadgeCheck, text: 'Payments, receipts & reports — no POS clutter' },
];

export default function AuthLayout({ children }: AuthLayoutProps) {
    return (
        <div className="flex min-h-screen">
            <div className="brand-glow relative hidden w-[44%] flex-col justify-between overflow-hidden p-10 text-ivory lg:flex">
                <AppLogo dark />
                <div className="rise-in space-y-6">
                    <p className="text-xs font-semibold uppercase tracking-[0.22em] text-gold-light">
                        Jewelry invoicing, refined
                    </p>
                    <h1 className="font-display text-4xl font-medium leading-tight xl:text-5xl">
                        Invoices worthy of
                        <br />
                        the jewels they bill.
                    </h1>
                    <ul className="space-y-3">
                        {highlights.map((item) => (
                            <li key={item.text} className="flex items-start gap-3 text-sm text-ivory/80">
                                <item.icon className="mt-0.5 size-4 shrink-0 text-gold" />
                                {item.text}
                            </li>
                        ))}
                    </ul>
                </div>
                <p className="text-xs text-ivory/50">
                    Trusted by jewelers for estimates, GST invoices & payment tracking.
                </p>
            </div>

            <div className="flex flex-1 flex-col items-center justify-center bg-background p-6">
                <div className="mb-6 lg:hidden">
                    <AppLogo />
                </div>
                <div className="rise-in w-full max-w-md rounded-xl border bg-card p-8 shadow-xl shadow-emeraldink/5">
                    {children}
                </div>
                <FlashToaster />
                <p className="mt-6 text-center text-xs text-muted-foreground">
                    <Link href="/" className="hover:text-foreground hover:underline">
                        ← Back to home
                    </Link>
                </p>
            </div>
        </div>
    );
}
