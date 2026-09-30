import { router, usePage } from '@inertiajs/react';
import { LogOut } from 'lucide-react';
import type { Auth } from '@/types/auth';

export function NavUser() {
    const { auth } = usePage<{ auth: Auth }>().props;

    if (!auth.user) return null;

    const initial = (auth.user.name || auth.user.email || '?').charAt(0).toUpperCase();

    return (
        <div className="flex items-center gap-2.5 rounded-lg border border-ivory/10 bg-ivory/5 p-2.5">
            <div className="flex size-9 shrink-0 items-center justify-center rounded-full bg-gold font-display text-sm font-semibold text-emeraldink-deep">
                {initial}
            </div>
            <div className="min-w-0 flex-1">
                <p className="truncate text-sm font-medium text-ivory">{auth.user.name}</p>
                <p className="truncate text-xs capitalize text-ivory/55">
                    {auth.user.role.replace('_', ' ')}
                </p>
            </div>
            <button
                type="button"
                title="Log out"
                onClick={() => router.post('/logout')}
                className="rounded-md p-2 text-ivory/55 transition-colors hover:bg-ivory/10 hover:text-gold-light"
            >
                <LogOut className="size-4" />
            </button>
        </div>
    );
}
