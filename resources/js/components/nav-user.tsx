import { usePage } from '@inertiajs/react';
import { Link } from '@inertiajs/react';
import { LogOut, User } from 'lucide-react';

export function NavUser() {
    const { auth } = usePage<{ auth: { user: { name: string; email: string } | null } }>().props;

    if (!auth.user) return null;

    return (
        <div className="flex items-center gap-2 rounded-md border p-2">
            <div className="flex h-8 w-8 items-center justify-center rounded-full bg-primary text-primary-foreground">
                <User className="h-4 w-4" />
            </div>
            <div className="flex flex-col">
                <span className="text-sm font-medium">{auth.user.name}</span>
                <span className="text-xs text-muted-foreground">{auth.user.email}</span>
            </div>
        </div>
    );
}
