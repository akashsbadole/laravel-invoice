import { Link } from '@inertiajs/react';
import { cn } from '@/lib/utils';

interface NavItem {
    title: string;
    href: string;
    icon?: React.ReactNode;
}

interface NavMainProps {
    items: NavItem[];
    className?: string;
}

export function NavMain({ items, className }: NavMainProps) {
    return (
        <nav className={cn('flex flex-col gap-1', className)}>
            {items.map((item) => (
                <Link
                    key={item.href}
                    href={item.href}
                    className="flex items-center gap-2 rounded-md px-2 py-1.5 text-sm text-muted-foreground transition-colors hover:bg-accent hover:text-accent-foreground"
                >
                    {item.icon}
                    {item.title}
                </Link>
            ))}
        </nav>
    );
}
