import * as React from 'react';
import { Link, usePage } from '@inertiajs/react';
import { cn } from '@/lib/utils';
import Heading from '@/components/heading';

interface SettingsLayoutProps {
    children: React.ReactNode;
    title?: string;
    description?: string;
}

const settingsNav = [
    { title: 'Profile', href: '/settings/profile' },
    { title: 'Business', href: '/settings/business' },
    { title: 'Security', href: '/settings/security' },
    { title: 'Appearance', href: '/settings/appearance' },
    { title: 'Users', href: '/settings/users' },
    { title: 'Activity Log', href: '/settings/activity-log' },
    { title: 'Metal Rates', href: '/settings/metal-rates' },
    { title: 'Catalog', href: '/settings/catalog' },
    { title: 'Charge Types', href: '/settings/charge-types' },
    { title: 'Invoice Templates', href: '/settings/invoice-templates' },
];

export default function SettingsLayout({ children, title, description }: SettingsLayoutProps) {
    const { url } = usePage();

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
                                {item.title}
                            </Link>
                        );
                    })}
                </nav>
            </aside>
            <div className="min-w-0 flex-1">{children}</div>
        </div>
    );
}
