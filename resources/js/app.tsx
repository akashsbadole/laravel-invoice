import '@fontsource-variable/fraunces';
import '@fontsource-variable/manrope';
import { StrictMode, type ComponentType, type ReactNode } from 'react';
import { createRoot } from 'react-dom/client';
import { createInertiaApp } from '@inertiajs/react';
import { ErrorBoundary } from '@/components/error-boundary';
import { Toaster } from '@/components/ui/sonner';
import { TooltipProvider } from '@/components/ui/tooltip';
import { initializeTheme } from '@/hooks/use-appearance';
import AppLayout from '@/layouts/app-layout';
import AuthLayout from '@/layouts/auth-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { registerServiceWorker } from '@/lib/register-service-worker';

type Breadcrumb = {
    title: string;
    href?: string;
};

type PageWithLayoutData = {
    layout?: {
        breadcrumbs?: Breadcrumb[];
    };
};

const appName = import.meta.env.VITE_APP_NAME || 'Jewelry Invoice';

const pageModules = import.meta.glob('./pages/**/*.tsx', { eager: true }) as Record<
    string,
    { default: ComponentType & PageWithLayoutData }
>;

/**
 * Wrap a page in the app chrome. This runs INSIDE the Inertia provider
 * (attached as Component.layout during resolve), so usePage() works in
 * every layout, sidebar and nav component.
 */
function applyLayout(name: string, breadcrumbs: Breadcrumb[], page: ReactNode): ReactNode {
    switch (true) {
        case name === 'welcome':
            return page;
        case name === 'invoices/public':
            return page;
        case name.startsWith('portal/'):
            return page;
        case name.startsWith('auth/'):
            return <AuthLayout>{page}</AuthLayout>;
        case name.startsWith('settings/'):
            return (
                <AppLayout breadcrumbs={breadcrumbs}>
                    <SettingsLayout>{page}</SettingsLayout>
                </AppLayout>
            );
        default:
            return <AppLayout breadcrumbs={breadcrumbs}>{page}</AppLayout>;
    }
}

void createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    resolve: (name) => {
        const module = pageModules[`./pages/${name}.tsx`];
        if (!module?.default) {
            throw new Error(`Page not found: ./pages/${name}.tsx`);
        }

        const Component = module.default;
        const breadcrumbs = Component.layout?.breadcrumbs ?? [];
        Component.layout = (page: ReactNode) => applyLayout(name, breadcrumbs, page);

        return Component;
    },
    setup({ el, App, props }) {
        createRoot(el).render(
            <StrictMode>
                <ErrorBoundary>
                    <TooltipProvider delayDuration={0}>
                        <App {...props} />
                        <Toaster position="top-right" richColors closeButton />
                    </TooltipProvider>
                </ErrorBoundary>
            </StrictMode>,
        );
    },
    progress: {
        color: '#4B5563',
    },
});

// This will set light / dark mode on load...
initializeTheme();

// ...and register the PWA service worker for offline app-shell caching.
registerServiceWorker();
