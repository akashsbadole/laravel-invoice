import { Link, router, usePage } from '@inertiajs/react';
import {
    BarChart3,
    Bell,
    CreditCard,
    Crown,
    FileText,
    LayoutDashboard,
    Settings,
    Users,
} from 'lucide-react';
import { AppLogo } from '@/components/app-logo';
import { NavUser } from '@/components/nav-user';
import { useLocale, type I18nKey } from '@/lib/i18n';
import { cn } from '@/lib/utils';

const navItems: { titleKey: I18nKey; href: string; icon: typeof LayoutDashboard }[] = [
    { titleKey: 'nav.dashboard', href: '/dashboard', icon: LayoutDashboard },
    { titleKey: 'nav.customers', href: '/customers', icon: Users },
    { titleKey: 'nav.invoices', href: '/invoices', icon: FileText },
    { titleKey: 'nav.payments', href: '/payments', icon: CreditCard },
    { titleKey: 'nav.reports', href: '/reports', icon: BarChart3 },
    { titleKey: 'nav.reminders', href: '/reminders', icon: Bell },
];

const secondaryItems: { titleKey: I18nKey; href: string; icon: typeof Settings }[] = [
    { titleKey: 'nav.settings', href: '/settings/profile', icon: Settings },
    { titleKey: 'nav.billing', href: '/billing', icon: Crown },
];

export function AppSidebar() {
    const { url, props } = usePage<{ auth: { tenant: { id: number; name: string } | null; firms: { id: number; name: string }[] } }>();
    const { auth } = props;
    const { t } = useLocale();
    const firms = auth.firms ?? [];
    const currentFirmId = auth.tenant?.id;

    return (
        <aside className="sticky top-0 hidden h-screen w-64 shrink-0 flex-col bg-emeraldink-deep text-ivory md:flex">
            <div className="border-b border-ivory/10 px-5 pb-5 pt-6">
                <AppLogo dark />
                {firms.length > 1 ? (
                    <select
                        aria-label="Switch firm"
                        value={currentFirmId ?? ''}
                        onChange={(e) => {
                            const tenantId = Number(e.target.value);
                            if (tenantId && tenantId !== currentFirmId) {
                                router.post('/settings/firms/switch', { tenant_id: tenantId });
                            }
                        }}
                        className="mt-4 w-full cursor-pointer rounded-md border border-ivory/15 bg-ivory/5 px-2 py-1.5 text-sm text-ivory outline-none transition-colors hover:bg-ivory/10 [&>option]:text-foreground"
                    >
                        {firms.map((firm) => (
                            <option key={firm.id} value={firm.id}>
                                {firm.name}
                            </option>
                        ))}
                    </select>
                ) : (
                    <div className="gold-rule mt-4" />
                )}
            </div>

            <nav className="flex-1 space-y-1 overflow-auto px-3 py-4">
                {navItems.map((item) => {
                    const isActive = url === item.href || url.startsWith(`${item.href}/`);
                    const Icon = item.icon;
                    return (
                        <Link
                            key={item.href}
                            href={item.href}
                            className={cn(
                                'group relative flex items-center gap-3 rounded-md px-3 py-2 text-sm transition-colors',
                                isActive
                                    ? 'bg-ivory/10 font-medium text-ivory'
                                    : 'text-ivory/65 hover:bg-ivory/5 hover:text-ivory',
                            )}
                        >
                            <span
                                className={cn(
                                    'absolute left-0 top-1/2 h-5 w-0.5 -translate-y-1/2 rounded-full bg-gold transition-opacity',
                                    isActive ? 'opacity-100' : 'opacity-0 group-hover:opacity-40',
                                )}
                            />
                            <Icon className={cn('size-4', isActive ? 'text-gold' : 'text-ivory/50 group-hover:text-gold-light')} />
                            {t(item.titleKey)}
                        </Link>
                    );
                })}

                <p className="px-3 pb-1 pt-5 text-[11px] font-semibold uppercase tracking-[0.14em] text-ivory/40">
                    {t('nav.manage')}
                </p>
                {secondaryItems.map((item) => {
                    const isActive = url.startsWith(item.href);
                    const Icon = item.icon;
                    return (
                        <Link
                            key={item.href}
                            href={item.href}
                            className={cn(
                                'flex items-center gap-3 rounded-md px-3 py-2 text-sm transition-colors',
                                isActive
                                    ? 'bg-ivory/10 font-medium text-ivory'
                                    : 'text-ivory/65 hover:bg-ivory/5 hover:text-ivory',
                            )}
                        >
                            <Icon className="size-4 text-ivory/50" />
                            {t(item.titleKey)}
                        </Link>
                    );
                })}
            </nav>

            <div className="border-t border-ivory/10 p-3">
                <NavUser />
            </div>
        </aside>
    );
}
