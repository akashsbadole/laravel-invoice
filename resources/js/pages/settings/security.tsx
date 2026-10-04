import { Form, Head, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

export default function Security({
    twoFactorEnabled,
    twoFactorPending,
    qrCodeSvg,
    secret,
    recoveryCodes = [],
}: {
    twoFactorEnabled: boolean;
    twoFactorPending: boolean;
    qrCodeSvg?: string | null;
    secret?: string | null;
    recoveryCodes?: string[];
}) {
    const passwordForm = useForm({
        current_password: '',
        password: '',
        password_confirmation: '',
    });

    const confirmForm = useForm({
        code: '',
    });

    function submitPassword(e: FormEvent) {
        e.preventDefault();
        passwordForm.put('/settings/password', {
            onSuccess: () => passwordForm.reset(),
        });
    }

    return (
        <>
            <Head title="Security" />

            <div className="space-y-6">
                <Heading title="Security" description="Manage your account password and authentication settings." />

                <Card>
                    <CardHeader>
                        <CardTitle>Change Password</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={submitPassword} className="max-w-lg space-y-4">
                            <div className="grid gap-2">
                                <Label htmlFor="current_password">Current password</Label>
                                <Input
                                    id="current_password"
                                    type="password"
                                    autoComplete="current-password"
                                    value={passwordForm.data.current_password}
                                    onChange={(e) => passwordForm.setData('current_password', e.target.value)}
                                />
                                <InputError message={passwordForm.errors.current_password} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="password">New password</Label>
                                <Input
                                    id="password"
                                    type="password"
                                    autoComplete="new-password"
                                    value={passwordForm.data.password}
                                    onChange={(e) => passwordForm.setData('password', e.target.value)}
                                />
                                <InputError message={passwordForm.errors.password} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="password_confirmation">Confirm new password</Label>
                                <Input
                                    id="password_confirmation"
                                    type="password"
                                    autoComplete="new-password"
                                    value={passwordForm.data.password_confirmation}
                                    onChange={(e) =>
                                        passwordForm.setData('password_confirmation', e.target.value)
                                    }
                                />
                                <InputError message={passwordForm.errors.password_confirmation} />
                            </div>

                            <Button type="submit" disabled={passwordForm.processing}>
                                Update password
                            </Button>
                        </form>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <div className="flex items-center justify-between">
                            <div>
                                <CardTitle>Two-Factor Authentication</CardTitle>
                                <CardDescription>
                                    Add additional security to your account using two-factor authentication.
                                </CardDescription>
                            </div>
                            {twoFactorEnabled ? (
                                <Badge variant="default">Enabled</Badge>
                            ) : twoFactorPending ? (
                                <Badge variant="secondary">Setup pending</Badge>
                            ) : (
                                <Badge variant="outline">Disabled</Badge>
                            )}
                        </div>
                    </CardHeader>
                    <CardContent className="space-y-6">
                        {!twoFactorEnabled && !twoFactorPending && (
                            <Form action="/settings/two-factor-authentication" method="post">
                                {({ processing }) => (
                                    <Button type="submit" disabled={processing}>
                                        Enable two-factor authentication
                                    </Button>
                                )}
                            </Form>
                        )}

                        {twoFactorPending && (
                            <div className="space-y-4 rounded-md border p-4">
                                <p className="text-sm text-muted-foreground">
                                    To finish enabling two-factor authentication, scan the QR code using your phone's authenticator application (e.g. Google Authenticator) and enter the generated code below.
                                </p>

                                {qrCodeSvg && (
                                    <div
                                        className="my-2 p-2 bg-white inline-block rounded border"
                                        dangerouslySetInnerHTML={{ __html: qrCodeSvg }}
                                    />
                                )}

                                {secret && (
                                    <p className="text-xs text-muted-foreground font-mono">
                                        Setup key: <strong>{secret}</strong>
                                    </p>
                                )}

                                <form
                                    onSubmit={(e) => {
                                        e.preventDefault();
                                        confirmForm.post('/settings/two-factor-confirm');
                                    }}
                                    className="max-w-xs space-y-3"
                                >
                                    <div className="grid gap-2">
                                        <Label htmlFor="two_factor_code">Verification code</Label>
                                        <Input
                                            id="two_factor_code"
                                            type="text"
                                            inputMode="numeric"
                                            placeholder="123456"
                                            value={confirmForm.data.code}
                                            onChange={(e) => confirmForm.setData('code', e.target.value)}
                                        />
                                        <InputError message={confirmForm.errors.code} />
                                    </div>
                                    <Button type="submit" disabled={confirmForm.processing}>
                                        Confirm and enable
                                    </Button>
                                </form>
                            </div>
                        )}

                        {twoFactorEnabled && (
                            <div className="space-y-4">
                                <p className="text-sm text-muted-foreground">
                                    Two-factor authentication is enabled. When logging in, you will be prompted for a secure, random token provided by your authenticator app.
                                </p>

                                {recoveryCodes.length > 0 && (
                                    <div className="rounded-md border p-4 space-y-2">
                                        <h4 className="text-sm font-semibold">Emergency Recovery Codes</h4>
                                        <p className="text-xs text-muted-foreground">
                                            Store these recovery codes in a safe place. They can be used to recover access to your account if your two-factor authentication device is lost.
                                        </p>
                                        <div className="grid grid-cols-2 gap-2 text-xs font-mono bg-muted p-3 rounded">
                                            {recoveryCodes.map((code, i) => (
                                                <div key={i}>{code}</div>
                                            ))}
                                        </div>
                                    </div>
                                )}

                                <Form action="/settings/two-factor-authentication" method="delete">
                                    {({ processing }) => (
                                        <Button variant="destructive" type="submit" disabled={processing}>
                                            Disable two-factor authentication
                                        </Button>
                                    )}
                                </Form>
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

Security.layout = {
    breadcrumbs: [
        { title: 'Security', href: '/settings/security' },
    ],
};
