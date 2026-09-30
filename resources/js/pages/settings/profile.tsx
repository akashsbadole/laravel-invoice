import { Head, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type ProfileUser = {
    id: number;
    name: string;
    email: string;
    role: string;
    email_verified_at: string | null;
    created_at: string;
};

export default function Profile({ user }: { user: ProfileUser }) {
    const { data, setData, patch, processing, errors } = useForm({
        name: user.name,
        email: user.email,
    });

    const deleteForm = useForm({
        password: '',
    });

    function submit(e: FormEvent) {
        e.preventDefault();
        patch('/settings/profile');
    }

    function destroyAccount(e: FormEvent) {
        e.preventDefault();
        if (!confirm('Delete your account? This cannot be undone.')) return;
        deleteForm.delete('/settings/profile');
    }

    return (
        <>
            <Head title="Profile" />

            <div className="space-y-6">
                <Heading title="Profile" description="Update your name and email address." />

                <Card>
                    <CardContent className="pt-6">
                        <form onSubmit={submit} className="max-w-lg space-y-4">
                            <div className="grid gap-2">
                                <Label htmlFor="name">Name</Label>
                                <Input
                                    id="name"
                                    value={data.name}
                                    onChange={(e) => setData('name', e.target.value)}
                                />
                                <InputError message={errors.name} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="email">Email</Label>
                                <Input
                                    id="email"
                                    type="email"
                                    value={data.email}
                                    onChange={(e) => setData('email', e.target.value)}
                                />
                                <InputError message={errors.email} />
                            </div>

                            <Button type="submit" disabled={processing}>
                                Save changes
                            </Button>
                        </form>
                    </CardContent>
                </Card>

                <Card>
                    <CardContent className="space-y-4 pt-6">
                        <div>
                            <h3 className="font-medium text-destructive">Delete account</h3>
                            <p className="text-sm text-muted-foreground">
                                Permanently delete your account. Confirm with your password.
                            </p>
                        </div>
                        <form onSubmit={destroyAccount} className="max-w-lg space-y-4">
                            <div className="grid gap-2">
                                <Label htmlFor="delete-password">Password</Label>
                                <Input
                                    id="delete-password"
                                    type="password"
                                    autoComplete="current-password"
                                    value={deleteForm.data.password}
                                    onChange={(e) => deleteForm.setData('password', e.target.value)}
                                />
                                <InputError message={deleteForm.errors.password} />
                            </div>
                            <Button
                                type="submit"
                                variant="destructive"
                                disabled={deleteForm.processing}
                            >
                                Delete account
                            </Button>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

Profile.layout = {
    breadcrumbs: [
        { title: 'Profile', href: '/settings/profile' },
    ],
};
