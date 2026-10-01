import * as React from 'react';
import { Link, usePage } from '@inertiajs/react';
import { useLocale, type I18nKey } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import Heading from '@/components/heading';

interface SettingsLayoutProps {
    children: React.ReactNode;
    title?: string;
    description?: string;
}

const settingsNav: { titleKey: I18nKey; href: string }[] = [
    { titleKey: 'nav.profile', href: '/settings/profile' },
    { titleKey: 'nav.business', href: '/settings/business' },
    { titleKey: 'nav.security', href: '/settings/security' },
    { titleKey: 'nav.appearance', href: '/settings/appearance' },
    { titleKey: 'nav.users', href: '/settings/users' },
    { titleKey: 'nav.activityLog', href: '/settings/activity-log' },
    { titleKey: 'nav.metalRates', href: '/settings/metal-rates' },
    { titleKey: 'nav.catalog', href: '/settings/catalog' },
    { titleKey: 'nav.chargeTypes', href: '/settings/charge-types' },
    { titleKey: 'nav.invoiceTemplates', href: '/settings/invoice-templates' },
];

export default function SettingsLayout({ children, title, description }: SettingsLayoutProps) {
    const { url } = usePage();
    const { t } = useLocale();

    return (
        <div className="flex flex-col gap-6 p-4 md:p-6 lg:flex-row">
            <aside className="w-full shrink-0 space-y-4 lg:w-56">
                {title && <Heading title={title} description={description} />}
                <nav className="flex flex-row flex-wrap gap-1 lg:flex-col">
                    {settingsNav.map((item) => {
                        const isActive = url.startsWith(item.href);
                        return (
                            <Link
                                key={item.href}
                                href={item.href}
                                className={cn(
                                    'rounded-md px-3 py-2 text-sm transition-colors',
                                    isActive
                                        ? 'bg-accent font-medium text-accent-foreground'
                                        : 'text-muted-foreground hover:bg-accent/60 hover:text-accent-foreground',
                                )}
                            >
                                {t(item.titleKey)}
                            </Link>
                        );
                    })}
                </nav>
            </aside>
            <div className="min-w-0 flex-1">{children}</div>
        </div>
    );
}
