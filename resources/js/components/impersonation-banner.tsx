import { Form } from '@inertiajs/react';
import { LogOut } from 'lucide-react';
import { Button } from '@/components/ui/button';

export type Impersonation = {
    super_admin_id: number;
    tenant_id: number;
    tenant_name: string;
    user_id: number;
    user_name: string;
};

/**
 * A super admin browsing a customer's workspace must never be able to forget
 * whose data they are looking at, so the banner is unmissable and always
 * offers a way out.
 */
export function ImpersonationBanner({
    impersonating,
}: {
    impersonating: Impersonation;
}) {
    return (
        <div className="sticky top-0 z-50 flex flex-wrap items-center justify-between gap-3 bg-brand px-4 py-2 text-sm text-white">
            <p className="font-medium">
                Viewing <strong>{impersonating.tenant_name}</strong> as{' '}
                <strong>{impersonating.user_name}</strong>
            </p>

            <Form {...{ action: '/admin/stop-impersonating', method: 'post' }}>
                {({ processing }) => (
                    <Button
                        type="submit"
                        size="sm"
                        variant="secondary"
                        disabled={processing}
                    >
                        <LogOut className="size-4" />
                        Back to platform admin
                    </Button>
                )}
            </Form>
        </div>
    );
}