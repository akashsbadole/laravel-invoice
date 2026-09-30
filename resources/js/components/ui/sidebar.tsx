import * as React from 'react';
import { cn } from '@/lib/utils';

export function Sidebar({ className, ...props }: React.HTMLAttributes<HTMLDivElement>) {
    return (
        <div
            className={cn('flex h-full w-64 flex-col border-r bg-background', className)}
            {...props}
        />
    );
}

export function SidebarHeader({ className, ...props }: React.HTMLAttributes<HTMLDivElement>) {
    return <div className={cn('flex h-14 items-center border-b px-4', className)} {...props} />;
}

export function SidebarContent({ className, ...props }: React.HTMLAttributes<HTMLDivElement>) {
    return <div className={cn('flex-1 overflow-auto py-4', className)} {...props} />;
}

export function SidebarFooter({ className, ...props }: React.HTMLAttributes<HTMLDivElement>) {
    return <div className={cn('border-t p-4', className)} {...props} />;
}

export function SidebarGroup({ className, ...props }: React.HTMLAttributes<HTMLDivElement>) {
    return <div className={cn('px-3 py-2', className)} {...props} />;
}

export function SidebarGroupLabel({ className, ...props }: React.HTMLAttributes<HTMLDivElement>) {
    return (
        <div
            className={cn('mb-2 px-2 text-xs font-semibold text-muted-foreground', className)}
            {...props}
        />
    );
}

export function SidebarMenu({ className, ...props }: React.HTMLAttributes<HTMLDivElement>) {
    return <div className={cn('space-y-1', className)} {...props} />;
}

export function SidebarMenuItem({ className, ...props }: React.HTMLAttributes<HTMLDivElement>) {
    return <div className={cn('', className)} {...props} />;
}

export function SidebarMenuButton({
    className,
    isActive,
    ...props
}: React.ButtonHTMLAttributes<HTMLButtonElement> & { isActive?: boolean }) {
    return (
        <button
            className={cn(
                'flex w-full items-center gap-2 rounded-md px-2 py-1.5 text-sm transition-colors',
                isActive
                    ? 'bg-accent text-accent-foreground'
                    : 'text-muted-foreground hover:bg-accent/50 hover:text-accent-foreground',
                className
            )}
            {...props}
        />
    );
}
