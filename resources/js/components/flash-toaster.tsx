import { usePage } from '@inertiajs/react';
import { useEffect, useRef } from 'react';
import { toast } from 'sonner';

type FlashToast = {
    type?: 'success' | 'error' | 'info';
    message?: string;
} | null;

/**
 * Bridges Laravel's flashed `toast` session data into sonner toasts.
 * Mounted once in app.tsx next to <Toaster />.
 */
export function FlashToaster() {
    const { flash } = usePage<{ flash?: { toast?: FlashToast } }>().props;
    const lastShown = useRef<string | null>(null);

    useEffect(() => {
        const data = flash?.toast;

        if (!data?.message) return;

        const key = `${data.type ?? 'info'}:${data.message}`;

        // Inertia re-renders without remounting on navigation; guard
        // against showing the same flash twice.
        if (lastShown.current === key) return;
        lastShown.current = key;

        if (data.type === 'error') {
            toast.error(data.message);
        } else if (data.type === 'success') {
            toast.success(data.message);
        } else {
            toast(data.message);
        }
    }, [flash]);

    return null;
}
