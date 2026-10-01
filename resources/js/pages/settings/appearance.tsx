import { Head } from '@inertiajs/react';
import { Check, Languages } from 'lucide-react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { useAppearance } from '@/hooks/use-appearance';
import { useLocale, type Locale } from '@/lib/i18n';
import { cn } from '@/lib/utils';

const options = [
    { value: 'light', label: 'Light', description: 'Always use the light theme.' },
    { value: 'dark', label: 'Dark', description: 'Always use the dark theme.' },
    { value: 'system', label: 'System', description: 'Follow your device setting.' },
] as const;

const locales: { value: Locale; label: string; description: string }[] = [
    { value: 'en', label: 'English', description: 'Default interface language.' },
    { value: 'hi', label: 'Hindi (हिन्दी)', description: 'मेनू और शीर्षक हिन्दी में।' },
];

export default function Appearance() {
    const { appearance, updateAppearance } = useAppearance();
    const { locale, updateLocale, t } = useLocale();

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

                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2 text-base">
                            <Languages className="size-4 text-gold-dark dark:text-gold-light" />
                            {t('language.title')}
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="grid gap-3 pt-0 sm:grid-cols-2">
                        <p className="-mt-2 text-sm text-muted-foreground sm:col-span-2">
                            {t('language.description')}
                        </p>
                        {locales.map((option) => {
                            const isActive = locale === option.value;
                            return (
                                <Button
                                    key={option.value}
                                    type="button"
                                    variant={isActive ? 'default' : 'outline'}
                                    className="flex h-auto flex-col items-start gap-1 p-4 text-left"
                                    onClick={() => updateLocale(option.value)}
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
