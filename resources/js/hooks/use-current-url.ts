import { usePage } from '@inertiajs/react';

export function useCurrentUrl() {
    const { url } = usePage();
    return url;
}

export function useIsActivePath(path: string) {
    const url = useCurrentUrl();
    return url.startsWith(path);
}
