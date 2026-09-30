import { Head } from '@inertiajs/react';
import { Check } from 'lucide-react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { useAppearance } from '@/hooks/use-appearance';
import { cn } from '@/lib/utils';

const options = [
    { value: 'light', label: 'Light', description: 'Always use the light theme.' },
    { value: 'dark', label: 'Dark', description: 'Always use the dark theme.' },
    { value: 'system', label: 'System', description: 'Follow your device setting.' },
] as const;

export default function Appearance() {
    const { appearance, updateAppearance } = useAppearance();

    return (
        <>
            <Head title="Appearance" />

            <div className="space-y-6">
                <Heading title="Appearance" description="Choose how the app looks on this device." />

                <Card>
                    <CardContent className="grid gap-3 pt-6 sm:grid-cols-3">
                        {options.map((option) => {
                            const isActive = appearance === option.value;
                            return (
                                <Button
                                    key={option.value}
                                    type="button"
                                    variant={isActive ? 'default' : 'outline'}
                                    className={cn(
                                        'flex h-auto flex-col items-start gap-1 p-4 text-left',
                                    )}
                                    onClick={() => updateAppearance(option.value)}
                                >
                                    <span className="flex w-full items-center justify-between font-medium">
                                        {option.label}
                                        {isActive && <Check className="size-4" />}
                                    </span>
                                    <span
                                        className={cn(
                                            'text-xs font-normal',
                                            isActive
                                                ? 'text-primary-foreground/80'
                                                : 'text-muted-foreground',
                                        )}
                                    >
                                        {option.description}
                                    </span>
                                </Button>
                            );
                        })}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

Appearance.layout = {
    breadcrumbs: [
        { title: 'Appearance', href: '/settings/appearance' },
    ],
};
