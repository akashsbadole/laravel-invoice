import { Head, Link } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';

export default function InviteExpired() {
    return (
        <>
            <Head title="Invitation expired" />

            <div className="space-y-6 text-center">
                <Heading
                    title="Invitation no longer valid"
                    description="This invite link expired, was revoked, or was already used. Ask your administrator for a fresh one."
                />
                <Button asChild className="w-full">
                    <Link href="/login">Back to log in</Link>
                </Button>
            </div>
        </>
    );
}
