import * as React from 'react';
import { Link, usePage } from '@inertiajs/react';
import { AppLogo } from '@/components/app-logo';
import { AppSidebar } from '@/components/app-sidebar';
import { FlashToaster } from '@/components/flash-toaster';
import { NavUser } from '@/components/nav-user';
import { ChevronRight } from 'lucide-react';

type Breadcrumb = {
    title: string;
    href?: string;
};

interface AppLayoutProps {
    children: React.ReactNode;
    breadcrumbs?: Breadcrumb[];
}

const mobileLinks = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Customers', href: '/customers' },
    { title: 'Invoices', href: '/invoices' },
    { title: 'Payments', href: '/payments' },
    { title: 'Reports', href: '/reports' },
    { title: 'Reminders', href: '/reminders' },
];

export default function AppLayout({ children, breadcrumbs = [] }: AppLayoutProps) {
    const { url } = usePage();

    return (
        <div className="flex min-h-screen">
            <AppSidebar />
            <div className="flex min-w-0 flex-1 flex-col">
                <div className="border-b border-ivory/10 bg-emeraldink-deep px-4 py-3 md:hidden">
                    <div className="flex items-center justify-between gap-3">
                        <AppLogo dark />
                    </div>
                    <nav className="-mb-px mt-3 flex gap-1 overflow-x-auto pb-1">
                        {mobileLinks.map((link) => (
                            <Link
                                key={link.href}
                                href={link.href}
                                className={
                                    url === link.href || url.startsWith(`${link.href}/`)
                                        ? 'shrink-0 rounded-full bg-gold px-3 py-1 text-xs font-semibold text-emeraldink-deep'
                                        : 'shrink-0 rounded-full px-3 py-1 text-xs text-ivory/70'
                                }
                            >
                                {link.title}
                            </Link>
                        ))}
                    </nav>
                </div>

                {breadcrumbs.length > 0 && (
                    <header className="flex items-center gap-1.5 border-b px-4 py-2.5 text-sm text-muted-foreground md:px-6">
                        {breadcrumbs.map((crumb, i) => (
                            <React.Fragment key={`${crumb.title}-${i}`}>
                                {i > 0 && <ChevronRight className="size-3.5 text-gold-dark" />}
                                {crumb.href && i < breadcrumbs.length - 1 ? (
                                    <Link href={crumb.href} className="hover:text-foreground">
                                        {crumb.title}
                                    </Link>
                                ) : (
                                    <span className="font-medium text-foreground">{crumb.title}</span>
                                )}
                            </React.Fragment>
                        ))}
                    </header>
                )}
                <main className="rise-in flex-1">{children}</main>
                <FlashToaster />
                <div className="px-4 pb-4 md:hidden">
                    <NavUser />
                </div>
            </div>
        </div>
    );
}
