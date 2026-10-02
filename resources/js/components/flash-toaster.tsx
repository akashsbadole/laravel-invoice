import { usePage } from '@inertiajs/react';
import { useEffect, useRef } from 'react';
import { toast } from 'sonner';

type FlashToast = {
    type?: 'success' | 'error' | 'info';
    message?: string;
} | null;

type PageProps = {
    flash?: { toast?: FlashToast };
};

/**
 * Bridges Laravel's flashed `toast` session data into sonner toasts, and
 * surfaces validation errors as a toast when no explicit message was set.
 * Mounted once per layout, next to <Toaster />.
 */
export function FlashToaster() {
    const { flash, errors } = usePage<PageProps>().props;
    const lastToast = useRef<string | null>(null);
    const lastErrors = useRef<string | null>(null);

    useEffect(() => {
        const data = flash?.toast;

        if (data?.message) {
            const key = `${data.type ?? 'info'}:${data.message}`;

            // Inertia re-renders without remounting on navigation; only
            // guard the same flash within one visit so repeating an action
            // still toasts again afterwards.
            if (lastToast.current !== key) {
                lastToast.current = key;

                if (data.type === 'error') {
                    toast.error(data.message);
                } else if (data.type === 'success') {
                    toast.success(data.message);
                } else {
                    toast(data.message);
                }
            }

            return;
        }

        // Flash is cleared on the next navigation, so an identical message
        // fired later is allowed to show again.
        lastToast.current = null;

        const errorMessages = errors
            ? Object.values(errors)
                  .map((message) =>
                      typeof message === 'string' ? message : '',
                  )
                  .filter((message) => message !== '')
            : [];

        if (errorMessages.length === 0) {
            lastErrors.current = null;

            return;
        }

        const errorKey = errorMessages.join('|');

        if (lastErrors.current === errorKey) return;
        lastErrors.current = errorKey;

        const [first, ...rest] = errorMessages;
        toast.error(
            rest.length > 0 ? `${first} (+${rest.length} more)` : first,
        );
    }, [flash, errors]);

    return null;
}
