import { Head, Link, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

export default function ForgotPassword({ status }: { status?: string | null }) {
    const { data, setData, post, processing, errors } = useForm({
        email: '',
    });

    function submit(e: FormEvent) {
        e.preventDefault();
        post('/forgot-password');
    }

    return (
        <>
            <Head title="Forgot password" />

            <div className="space-y-6">
                <Heading
                    title="Forgot password"
                    description="Enter your account email and we'll send you a reset link."
                />

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
                        Email password reset link
                    </Button>

                    <p className="text-center text-sm text-muted-foreground">
                        <Link href="/login" className="hover:text-foreground hover:underline">
                            Back to log in
                        </Link>
                    </p>
                </form>
            </div>
        </>
    );
}
