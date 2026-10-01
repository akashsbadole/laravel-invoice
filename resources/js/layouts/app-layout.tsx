import * as React from 'react';
import { Link, usePage } from '@inertiajs/react';
import { CreditCard, FileText, LayoutDashboard, Package, Users } from 'lucide-react';
import { AppLogo } from '@/components/app-logo';
import { AppSidebar } from '@/components/app-sidebar';
import {
    ImpersonationBanner,
    type Impersonation,
} from '@/components/impersonation-banner';
import { FlashToaster } from '@/components/flash-toaster';
import { NavUser } from '@/components/nav-user';
import { useLocale, type I18nKey } from '@/lib/i18n';
import { ChevronRight } from 'lucide-react';
import { cn } from '@/lib/utils';

type Breadcrumb = {
    title: string;
    href?: string;
};

interface AppLayoutProps {
    children: React.ReactNode;
    breadcrumbs?: Breadcrumb[];
}

const mobileTabs: { titleKey: I18nKey; href: string; icon: typeof LayoutDashboard }[] = [
    { titleKey: 'nav.dashboard', href: '/dashboard', icon: LayoutDashboard },
    { titleKey: 'nav.customers', href: '/customers', icon: Users },
    { titleKey: 'nav.catalog', href: '/catalog', icon: Package },
    { titleKey: 'nav.invoices', href: '/invoices', icon: FileText },
    { titleKey: 'nav.payments', href: '/payments', icon: CreditCard },
];

export default function AppLayout({ children, breadcrumbs = [] }: AppLayoutProps) {
    const { url, props } = usePage<{
        impersonating?: Impersonation | null;
    }>();
    const { t } = useLocale();

    return (
        <div className="flex min-h-screen">
            <AppSidebar />
            <div className="flex min-w-0 flex-1 flex-col">
                {props.impersonating && (
                    <ImpersonationBanner impersonating={props.impersonating} />
                )}
                <div className="border-b border-ivory/10 bg-navy-deep px-4 py-3 md:hidden">
                    <AppLogo dark />
                </div>

                {breadcrumbs.length > 0 && (
                    <header className="flex items-center gap-1.5 overflow-x-auto border-b px-4 py-2.5 text-sm text-muted-foreground md:px-6">
                        {breadcrumbs.map((crumb, i) => (
                            <React.Fragment key={`${crumb.title}-${i}`}>
                                {i > 0 && <ChevronRight className="size-3.5 shrink-0 text-brand-dark" />}
                                {crumb.href && i < breadcrumbs.length - 1 ? (
                                    <Link href={crumb.href} className="shrink-0 hover:text-foreground">
                                        {crumb.title}
                                    </Link>
                                ) : (
                                    <span className="shrink-0 font-medium text-foreground">{crumb.title}</span>
                                )}
                            </React.Fragment>
                        ))}
                    </header>
                )}
                <main className="rise-in flex-1 pb-20 md:pb-0">{children}</main>
                <div className="px-4 pb-24 md:hidden">
                    <NavUser />
                </div>
                <FlashToaster />
            </div>

            <nav className="fixed inset-x-0 bottom-0 z-40 border-t border-ivory/10 bg-navy-deep/95 pb-[env(safe-area-inset-bottom)] backdrop-blur md:hidden">
                <div className="grid grid-cols-5">
                    {mobileTabs.map((tab) => {
                        const isActive = url === tab.href || url.startsWith(`${tab.href}/`);
                        const Icon = tab.icon;
                        return (
                            <Link
                                key={tab.href}
                                href={tab.href}
                                className={cn(
                                    'flex flex-col items-center gap-0.5 py-2 text-[11px] transition-colors',
                                    isActive ? 'font-semibold text-brand' : 'text-ivory/60',
                                )}
                            >
                                <Icon className="size-5" />
                                {t(tab.titleKey)}
                            </Link>
                        );
                    })}
                </div>
            </nav>
        </div>
    );
}
