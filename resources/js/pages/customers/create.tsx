import { Form, Head, Link } from '@inertiajs/react';
import CustomerController from '@/actions/App/Http/Controllers/CustomerController';
import Heading from '@/components/heading';
import CustomerFormFields from '@/components/customers/customer-form-fields';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { create, index } from '@/routes/customers';
import { dashboard } from '@/routes';
import type { CustomerGroup, Staff } from '@/types/customer';

export default function CreateCustomer({
    staff,
    customerGroups,
}: {
    staff: Staff[];
    customerGroups: CustomerGroup[];
}) {
    return (
        <>
            <Head title="New customer" />

            <div className="mx-auto w-full max-w-2xl space-y-6 p-4 md:p-6">
                <Heading
                    title="New customer"
                    description="Add a customer so you can start invoicing them."
                />

                <Card>
                    <CardContent>
                        <Form
                            {...CustomerController.store.form()}
                            options={{ preserveScroll: true }}
                            className="space-y-6"
                        >
                            {({ processing, errors }) => (
                                <>
                                    <CustomerFormFields
                                        staff={staff}
                                        customerGroups={customerGroups}
                                        errors={errors}
                                    />

                                    <div className="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                                        <Button
                                            type="button"
                                            variant="secondary"
                                            asChild
                                            className="w-full sm:w-auto"
                                        >
                                            <Link href={index()}>Cancel</Link>
                                        </Button>
                                        <Button
                                            disabled={processing}
                                            className="w-full sm:w-auto"
                                        >
                                            {processing ? 'Saving…' : 'Save customer'}
                                        </Button>
                                    </div>
                                </>
                            )}
                        </Form>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

CreateCustomer.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Customers', href: index() },
        { title: 'New', href: create() },
    ],
};
