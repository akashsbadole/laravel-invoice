import { Head, Link, usePage } from '@inertiajs/react';
import { FileText, Gem } from 'lucide-react';
import { Button } from '@/components/ui/button';
import type { Auth } from '@/types/auth';

export default function Welcome() {
    const { auth } = usePage<{ auth: Auth }>().props;

    return (
        <>
            <Head title="Welcome" />

            <div className="flex min-h-screen flex-col items-center justify-center gap-6 bg-background p-6 text-center">
                <div className="flex size-14 items-center justify-center rounded-2xl bg-primary text-primary-foreground">
                    <Gem className="size-7" />
                </div>
                <div className="space-y-2">
                    <h1 className="text-3xl font-bold tracking-tight">Jewelry Invoice</h1>
                    <p className="max-w-md text-muted-foreground">
                        Create customers, build jewelry invoices, share secure PDF
                        links, and track payments — all in one place.
                    </p>
                </div>
                <div className="flex flex-wrap items-center justify-center gap-3">
                    {auth.user ? (
                        <Button asChild>
                            <Link href="/dashboard">
                                <FileText className="size-4" />
                                Open dashboard
                            </Link>
                        </Button>
                    ) : (
                        <Button asChild>
                            <Link href="/login">Log in</Link>
                        </Button>
                    )}
                </div>
            </div>
        </>
    );
}
