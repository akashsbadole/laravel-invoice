import { Link } from '@inertiajs/react';
import { cn } from '@/lib/utils';

interface NavFooterProps {
    className?: string;
}

export function NavFooter({ className }: NavFooterProps) {
    return (
        <div className={cn('flex flex-col gap-1', className)}>
            <Link
                href="/settings/profile"
                className="flex items-center gap-2 rounded-md px-2 py-1.5 text-sm text-muted-foreground transition-colors hover:bg-accent hover:text-accent-foreground"
            >
                Settings
            </Link>
        </div>
    );
}
