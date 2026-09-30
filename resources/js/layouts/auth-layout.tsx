import * as React from 'react';
import { AppLogo } from '@/components/app-logo';

interface AuthLayoutProps {
    children: React.ReactNode;
}

export default function AuthLayout({ children }: AuthLayoutProps) {
    return (
        <div className="flex min-h-screen flex-col items-center justify-center bg-background">
            <div className="mb-8">
                <AppLogo />
            </div>
            <div className="w-full max-w-md rounded-lg border bg-card p-8 shadow">
                {children}
            </div>
        </div>
    );
}
