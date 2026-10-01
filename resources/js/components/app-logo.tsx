import { Link } from '@inertiajs/react';
import { ReceiptText } from 'lucide-react';
import { cn } from '@/lib/utils';

export function AppLogo({ dark = false, className }: { dark?: boolean; className?: string }) {
    return (
        <Link href="/" className={cn('group flex items-center gap-2.5', className)}>
            <span className="flex size-9 items-center justify-center rounded-full border border-gold/60 bg-gold/10 text-gold">
                <ReceiptText className="size-4" />
            </span>
            <span
                className={cn(
                    'font-display text-xl font-semibold tracking-wide',
                    dark ? 'text-ivory' : 'text-foreground',
                )}
            >
                Invoice CRM
            </span>
        </Link>
    );
}
