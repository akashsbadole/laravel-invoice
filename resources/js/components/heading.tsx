import * as React from 'react';
import { cn } from '@/lib/utils';

interface HeadingProps extends React.HTMLAttributes<HTMLHeadingElement> {
    title: string;
    description?: string;
    variant?: 'default' | 'small';
}

export function Heading({ title, description, variant = 'default', className, ...props }: HeadingProps) {
    return (
        <div className={cn('flex flex-col gap-1', className)} {...props}>
            <h2
                className={
                    variant === 'small'
                        ? 'font-display text-xl font-semibold leading-tight'
                        : 'font-display text-[1.7rem] font-semibold leading-tight tracking-normal'
                }
            >
                {title}
            </h2>
            {description && <p className="text-sm text-muted-foreground">{description}</p>}
        </div>
    );
}

export default Heading;
