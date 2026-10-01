import { Head, Link, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useLocale } from '@/lib/i18n';

export default function Login({ status }: { status?: string | null }) {
    const { t } = useLocale();
    const { data, setData, post, processing, errors, reset } = useForm({
        email: '',
        password: '',
        remember: false,
    });

    function submit(e: FormEvent) {
        e.preventDefault();
        post('/login', {
            onFinish: () => reset('password'),
        });
    }

    return (
        <>
            <Head title={t('auth.login')} />

            <div className="space-y-6">
                <Heading title={t('auth.welcomeBack')} description={t('auth.loginBlurb')} />

                {status && (
                    <p className="rounded-md bg-green-50 px-3 py-2 text-sm text-green-700 dark:bg-green-950 dark:text-green-300">
                        {status}
                    </p>
                )}

                <form onSubmit={submit} className="space-y-4">
                    <div className="grid gap-2">
                        <Label htmlFor="email">{t('auth.email')}</Label>
                        <Input
                            id="email"
                            type="email"
                            autoComplete="username"
                            autoFocus
                            value={data.email}
                            onChange={(e) => setData('email', e.target.value)}
                        />
                        <InputError message={errors.email} />
                    </div>

                    <div className="grid gap-2">
                        <div className="flex items-center justify-between">
                            <Label htmlFor="password">{t('auth.password')}</Label>
                            <Link
                                href="/forgot-password"
                                className="text-sm text-muted-foreground hover:text-foreground hover:underline"
                            >
                                {t('auth.forgot')}
                            </Link>
                        </div>
                        <Input
                            id="password"
                            type="password"
                            autoComplete="current-password"
                            value={data.password}
                            onChange={(e) => setData('password', e.target.value)}
                        />
                        <InputError message={errors.password} />
                    </div>

                    <label className="flex cursor-pointer items-center gap-2 text-sm text-muted-foreground">
                        <input
                            type="checkbox"
                            checked={data.remember}
                            onChange={(e) => setData('remember', e.target.checked)}
                            className="size-4 rounded border-input"
                        />
                        {t('auth.rememberMe')}
                    </label>

                    <Button type="submit" disabled={processing} className="w-full">
                        {t('auth.login')}
                    </Button>

                    <p className="text-center text-sm text-muted-foreground">
                        {t('auth.newHere')}{' '}
                        <Link href="/register" className="hover:text-foreground hover:underline">
                            {t('auth.startTrial')}
                        </Link>
                    </p>
                </form>
            </div>
        </>
    );
}
