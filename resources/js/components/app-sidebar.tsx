import { Link, usePage } from '@inertiajs/react';
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
import { cn } from '@/lib/utils';

const navItems = [
    { title: 'Dashboard', href: '/dashboard', icon: LayoutDashboard },
    { title: 'Customers', href: '/customers', icon: Users },
    { title: 'Invoices', href: '/invoices', icon: FileText },
    { title: 'Payments', href: '/payments', icon: CreditCard },
    { title: 'Reports', href: '/reports', icon: BarChart3 },
    { title: 'Reminders', href: '/reminders', icon: Bell },
];

const secondaryItems = [
    { title: 'Settings', href: '/settings/profile', icon: Settings },
    { title: 'Billing', href: '/billing', icon: Crown },
];

export function AppSidebar() {
    const { url } = usePage();

    return (
        <aside className="sticky top-0 hidden h-screen w-64 shrink-0 flex-col bg-emeraldink-deep text-ivory md:flex">
            <div className="border-b border-ivory/10 px-5 pb-5 pt-6">
                <AppLogo dark />
                <div className="gold-rule mt-4" />
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
                            {item.title}
                        </Link>
                    );
                })}

                <p className="px-3 pb-1 pt-5 text-[11px] font-semibold uppercase tracking-[0.14em] text-ivory/40">
                    Manage
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
                            {item.title}
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
