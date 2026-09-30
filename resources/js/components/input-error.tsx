import * as React from 'react';
import { cn } from '@/lib/utils';

interface InputErrorProps extends React.HTMLAttributes<HTMLParagraphElement> {
    message?: string;
}

export function InputError({ message, className, ...props }: InputErrorProps) {
    if (!message) return null;

    return (
        <p className={cn('text-sm text-destructive', className)} {...props}>
            {message}
        </p>
    );
}

export default InputError;
