import * as React from 'react';
import { cn } from '@/lib/utils';

interface HeadingProps extends React.HTMLAttributes<HTMLHeadingElement> {
    title: string;
    description?: string;
}

export function Heading({ title, description, className, ...props }: HeadingProps) {
    return (
        <div className={cn('flex flex-col gap-1', className)} {...props}>
            <h2 className="text-2xl font-bold tracking-tight">{title}</h2>
            {description && <p className="text-sm text-muted-foreground">{description}</p>}
        </div>
    );
}

export default Heading;
