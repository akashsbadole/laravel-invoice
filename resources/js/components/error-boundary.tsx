import { Component, type ReactNode } from 'react';
import { Gem, RefreshCw } from 'lucide-react';
import { Button } from '@/components/ui/button';

type Props = {
    children: ReactNode;
};

type State = {
    error: Error | null;
};

/**
 * Last-resort catch for client render crashes (e.g. a stale cached
 * bundle). Shows a branded fallback instead of a blank page.
 */
export class ErrorBoundary extends Component<Props, State> {
    state: State = { error: null };

    static getDerivedStateFromError(error: Error): State {
        return { error };
    }

    componentDidCatch(error: Error): void {
        console.error('Uncaught render error:', error);
    }

    render(): ReactNode {
        if (!this.state.error) {
            return this.props.children;
        }

        return (
            <div className="brand-panel flex min-h-screen flex-col items-center justify-center gap-4 p-6 text-center text-ivory">
                <span className="flex size-12 items-center justify-center rounded-full border border-brand/60 bg-brand/10 text-brand">
                    <Gem className="size-5" />
                </span>
                <div className="space-y-1">
                    <h1 className="font-display text-2xl">Something went sideways.</h1>
                    <p className="max-w-sm text-sm text-ivory/70">
                        The page hit an unexpected error. Reloading usually fixes it â€”
                        especially after an update.
                    </p>
                </div>
                <div className="flex gap-2">
                    <Button
                        className="bg-brand text-navy-deep hover:bg-brand-light"
                        onClick={() => window.location.reload()}
                    >
                        <RefreshCw className="size-4" />
                        Reload page
                    </Button>
                    <Button
                        variant="outline"
                        className="border-ivory/25 bg-transparent text-ivory hover:bg-ivory/10 hover:text-ivory"
                        onClick={() => {
                            if ('caches' in window) {
                                void caches.keys().then((keys) => Promise.all(keys.map((k) => caches.delete(k))));
                            }
                            window.location.reload();
                        }}
                    >
                        Clear cache & reload
                    </Button>
                </div>
            </div>
        );
    }
}
