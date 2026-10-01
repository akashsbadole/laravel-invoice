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

type SettingsNavItem = {
    titleKey: I18nKey;
    href: string;
    /** Capability gate — matches App\Http\Middleware\EnsureIndustryAllows. */
    capability?: string;
};

const settingsNav: SettingsNavItem[] = [
    { titleKey: 'nav.profile', href: '/settings/profile' },
    { titleKey: 'nav.business', href: '/settings/business' },
    { titleKey: 'nav.security', href: '/settings/security' },
    { titleKey: 'nav.appearance', href: '/settings/appearance' },
    { titleKey: 'nav.users', href: '/settings/users' },
    { titleKey: 'nav.activityLog', href: '/settings/activity-log' },
    { titleKey: 'nav.metalRates', href: '/settings/metal-rates', capability: 'metal_rates' },
    { titleKey: 'nav.chargeTypes', href: '/settings/charge-types' },
    { titleKey: 'nav.invoiceTemplates', href: '/settings/invoice-templates' },
];

type Capabilities = {
    metal_rates: boolean;
};

const CAPABILITY_DEFAULTS: Capabilities = { metal_rates: true };

export default function SettingsLayout({ children, title, description }: SettingsLayoutProps) {
    // `url` lives on the Inertia page object, not in props — reading it from
    // props leaves it undefined and `url.startsWith` throws.
    const page = usePage<{ capabilities?: Capabilities }>();
    const { url } = page;
    const { capabilities } = page.props;
    const { t } = useLocale();

    const resolved = { ...CAPABILITY_DEFAULTS, ...capabilities };
    const visibleNav = settingsNav.filter((item) => {
        if (!item.capability) return true;
        return resolved[item.capability as keyof Capabilities] !== false;
    });

    return (
        <div className="flex flex-col gap-6 p-4 md:p-6 lg:flex-row">
            <aside className="w-full shrink-0 space-y-4 lg:w-56">
                {title && <Heading title={title} description={description} />}
                <nav className="flex flex-row flex-wrap gap-1 lg:flex-col">
                    {visibleNav.map((item) => {
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