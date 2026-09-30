import { Form, Head, Link } from '@inertiajs/react';
import CustomerController from '@/actions/App/Http/Controllers/CustomerController';
import Heading from '@/components/heading';
import CustomerFormFields from '@/components/customers/customer-form-fields';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { index, show } from '@/routes/customers';
import { dashboard } from '@/routes';
import type { Customer, Staff } from '@/types/customer';

export default function EditCustomer({
    customer,
    staff,
}: {
    customer: Customer;
    staff: Staff[];
}) {
    return (
        <>
            <Head title={`Edit ${customer.full_name}`} />

            <div className="mx-auto w-full max-w-2xl space-y-6 p-4 md:p-6">
                <Heading
                    title="Edit customer"
                    description={`Update details for ${customer.full_name}.`}
                />

                <Card>
                    <CardContent>
                        <Form
                            {...CustomerController.update.form(customer.id)}
                            options={{ preserveScroll: true }}
                            className="space-y-6"
                        >
                            {({ processing, errors }) => (
                                <>
                                    <CustomerFormFields
                                        customer={customer}
                                        staff={staff}
                                        errors={errors}
                                    />

                                    <div className="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                                        <Button
                                            type="button"
                                            variant="secondary"
                                            asChild
                                            className="w-full sm:w-auto"
                                        >
                                            <Link href={show(customer.id)}>
                                                Cancel
                                            </Link>
                                        </Button>
                                        <Button
                                            disabled={processing}
                                            className="w-full sm:w-auto"
                                        >
                                            {processing
                                                ? 'Saving…'
                                                : 'Save changes'}
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

EditCustomer.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Customers', href: index() },
    ],
};
