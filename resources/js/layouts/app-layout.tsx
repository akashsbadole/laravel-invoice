import * as React from 'react';
import { Link } from '@inertiajs/react';
import { ChevronRight } from 'lucide-react';
import { AppSidebar } from '@/components/app-sidebar';

type Breadcrumb = {
    title: string;
    href?: string;
};

interface AppLayoutProps {
    children: React.ReactNode;
    breadcrumbs?: Breadcrumb[];
}

export default function AppLayout({ children, breadcrumbs = [] }: AppLayoutProps) {
    return (
        <div className="flex min-h-screen">
            <AppSidebar />
            <div className="flex min-w-0 flex-1 flex-col">
                {breadcrumbs.length > 0 && (
                    <header className="flex items-center gap-1.5 border-b px-4 py-2.5 text-sm text-muted-foreground md:px-6">
                        {breadcrumbs.map((crumb, i) => (
                            <React.Fragment key={`${crumb.title}-${i}`}>
                                {i > 0 && <ChevronRight className="size-3.5" />}
                                {crumb.href && i < breadcrumbs.length - 1 ? (
                                    <Link href={crumb.href} className="hover:text-foreground">
                                        {crumb.title}
                                    </Link>
                                ) : (
                                    <span className="text-foreground">{crumb.title}</span>
                                )}
                            </React.Fragment>
                        ))}
                    </header>
                )}
                <main className="flex-1">{children}</main>
            </div>
        </div>
    );
}
