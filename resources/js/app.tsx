import { StrictMode, type ReactNode } from 'react';
import { createRoot } from 'react-dom/client';
import { createInertiaApp } from '@inertiajs/react';
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

type PageWithLayout = {
    layout?: {
        breadcrumbs?: Breadcrumb[];
    };
};

const appName = import.meta.env.VITE_APP_NAME || 'Jewelry Invoice';

const pages = import.meta.glob('./pages/**/*.tsx', { eager: true }) as Record<
    string,
    unknown
>;

function applyLayout(name: string, breadcrumbs: Breadcrumb[], page: ReactNode): ReactNode {
    switch (true) {
        case name === 'welcome':
            return page;
        case name === 'invoices/public':
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
        const page = pages[`./pages/${name}.tsx`];
        if (!page) {
            throw new Error(`Page not found: ./pages/${name}.tsx`);
        }
        return page;
    },
    setup({ el, App, props }) {
        const initialPage = (
            props as unknown as { initialPage: { component: string } }
        ).initialPage;
        const name = initialPage.component;
        const breadcrumbs =
            (App as unknown as PageWithLayout).layout?.breadcrumbs ?? [];

        createRoot(el).render(
            <StrictMode>
                <TooltipProvider delayDuration={0}>
                    {applyLayout(name, breadcrumbs, <App {...props} />)}
                    <Toaster />
                </TooltipProvider>
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
