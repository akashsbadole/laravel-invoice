import { Head, Link, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useLocale } from '@/lib/i18n';
import type { IndustryOption } from '@/lib/industries';
import { cn } from '@/lib/utils';

export default function Register({
    industries,
}: {
    industries: IndustryOption[];
}) {
    const { t } = useLocale();
    const { data, setData, post, processing, errors, reset } = useForm({
        name: '',
        business_name: '',
        industry: 'jewelry',
        email: '',
        password: '',
        password_confirmation: '',
    });

    function submit(e: FormEvent) {
        e.preventDefault();
        post('/register', {
            onFinish: () => reset('password', 'password_confirmation'),
        });
    }

    return (
        <>
            <Head title={t('auth.register')} />

            <div className="space-y-6">
                <Heading title={t('auth.createAccount')} description={t('auth.createBlurb')} />

                <form onSubmit={submit} className="space-y-4">
                    <div className="grid gap-2">
                        <Label htmlFor="name">{t('auth.yourName')}</Label>
                        <Input
                            id="name"
                            autoComplete="name"
                            autoFocus
                            value={data.name}
                            onChange={(e) => setData('name', e.target.value)}
                        />
                        <InputError message={errors.name} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="business_name">{t('auth.businessName')}</Label>
                        <Input
                            id="business_name"
                            autoComplete="organization"
                            placeholder="Sharma Traders"
                            value={data.business_name}
                            onChange={(e) => setData('business_name', e.target.value)}
                        />
                        <InputError message={errors.business_name} />
                    </div>

                    <div className="grid gap-2">
                        <Label>What do you sell?</Label>
                        <div className="space-y-2">
                            {industries.map((option) => (
                                <label
                                    key={option.key}
                                    className={cn(
                                        'flex cursor-pointer items-start gap-3 rounded-md border p-3 transition-colors',
                                        data.industry === option.key
                                            ? 'border-gold bg-gold/5'
                                            : 'hover:bg-muted/40',
                                    )}
                                >
                                    <input
                                        type="radio"
                                        name="industry"
                                        value={option.key}
                                        checked={data.industry === option.key}
                                        onChange={(e) =>
                                            setData('industry', e.target.value)
                                        }
                                        className="mt-1"
                                    />
                                    <span className="min-w-0">
                                        <span className="block text-sm font-medium">
                                            {option.label}
                                        </span>
                                        <span className="block text-xs text-muted-foreground">
                                            {option.description}
                                        </span>
                                    </span>
                                </label>
                            ))}
                        </div>
                        <InputError message={errors.industry} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="email">{t('auth.workEmail')}</Label>
                        <Input
                            id="email"
                            type="email"
                            autoComplete="email"
                            value={data.email}
                            onChange={(e) => setData('email', e.target.value)}
                        />
                        <InputError message={errors.email} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="password">{t('auth.password')}</Label>
                        <Input
                            id="password"
                            type="password"
                            autoComplete="new-password"
                            value={data.password}
                            onChange={(e) => setData('password', e.target.value)}
                        />
                        <InputError message={errors.password} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="password_confirmation">{t('auth.confirmPassword')}</Label>
                        <Input
                            id="password_confirmation"
                            type="password"
                            autoComplete="new-password"
                            value={data.password_confirmation}
                            onChange={(e) => setData('password_confirmation', e.target.value)}
                        />
                        <InputError message={errors.password_confirmation} />
                    </div>

                    <Button type="submit" disabled={processing} className="w-full">
                        {t('auth.register')}
                    </Button>

                    <p className="text-center text-sm text-muted-foreground">
                        {t('auth.alreadyHave')}{' '}
                        <Link href="/login" className="hover:text-foreground hover:underline">
                            {t('auth.login')}
                        </Link>
                    </p>
                </form>
            </div>
        </>
    );
}
