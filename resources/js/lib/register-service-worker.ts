export function registerServiceWorker() {
    // Never register a worker in dev: a stale app-shell cache is the
    // classic "blank page after rebuild" trap (old JS + new HTML).
    if (import.meta.env.DEV) {
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.getRegistrations().then((registrations) => {
                for (const registration of registrations) {
                    void registration.unregister();
                }
            }).catch(() => {
                // Unregistration failed; harmless in dev.
            });
        }
        return;
    }

    if ('serviceWorker' in navigator) {
        window.addEventListener('load', () => {
            navigator.serviceWorker.register('/sw.js').catch(() => {
                // Service worker registration failed
            });
        });
    }
}
