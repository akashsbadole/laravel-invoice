import { Head, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { AppLogo } from '@/components/app-logo';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

export default function PortalLogin({ status }: { status?: string | null }) {
    const { data, setData, post, processing, errors } = useForm({
        email: '',
    });

    function submit(e: FormEvent) {
        e.preventDefault();
        post('/portal/login');
    }

    return (
        <>
            <Head title="Customer portal" />

            <div className="brand-glow flex min-h-screen flex-col items-center justify-center gap-6 p-6">
                <AppLogo dark />
                <Card className="w-full max-w-md">
                    <CardContent className="space-y-4 pt-6">
                        <div>
                            <h1 className="font-display text-2xl">View your invoices</h1>
                            <p className="text-sm text-muted-foreground">
                                Enter your email and we'll send you a secure sign-in link.
                            </p>
                        </div>

                        {status && (
                            <p className="rounded-md bg-green-50 px-3 py-2 text-sm text-green-700 dark:bg-green-950 dark:text-green-300">
                                {status}
                            </p>
                        )}

                        <form onSubmit={submit} className="space-y-4">
                            <div className="grid gap-2">
                                <Label htmlFor="email">Email</Label>
                                <Input
                                    id="email"
                                    type="email"
                                    autoComplete="email"
                                    autoFocus
                                    value={data.email}
                                    onChange={(e) => setData('email', e.target.value)}
                                />
                                <InputError message={errors.email} />
                            </div>
                            <Button type="submit" disabled={processing} className="w-full">
                                Email me a sign-in link
                            </Button>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
