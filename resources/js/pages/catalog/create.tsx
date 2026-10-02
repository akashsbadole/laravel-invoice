import { Form, Head } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { CatalogItemForm } from '@/components/catalog/catalog-item-form';
import CatalogItemController from '@/actions/App/Http/Controllers/CatalogItemController';
import { index as catalogIndex } from '@/routes/catalog';
import type { CatalogFieldSpec, IndustryConfig } from '@/lib/industries';

export default function CatalogCreatePage({
    industry,
    fields,
}: {
    industry: IndustryConfig;
    fields: CatalogFieldSpec[];
}) {
    return (
        <>
            <Head title="New catalog item" />

            <div className="max-w-3xl space-y-6 p-4 md:p-6">
                <div>
                    <h1 className="text-2xl font-semibold">New catalog item</h1>
                    <p className="text-sm text-muted-foreground">
                        Add a product to your catalog. It will be available for
                        quotations and invoices once activated.
                    </p>
                </div>

                <Form
                    {...CatalogItemController.store.form()}
                    resetOnSuccess
                    className="space-y-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <Card>
                                <CardHeader>
                                    <CardTitle>Product details</CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <CatalogItemForm
                                        errors={errors}
                                        industry={industry}
                                        fields={fields}
                                        idPrefix="new"
                                    />
                                </CardContent>
                            </Card>

                            <div className="flex items-start gap-2">
                                <input
                                    type="checkbox"
                                    id="new-status"
                                    name="status"
                                    value="draft"
                                    className="mt-0.5 size-4"
                                />
                                <label
                                    htmlFor="new-status"
                                    className="text-sm"
                                >
                                    Save as draft (not visible in quotations
                                    until activated)
                                </label>
                            </div>

                            <div className="flex justify-end gap-2">
                                <Button
                                    type="button"
                                    variant="secondary"
                                    asChild
                                >
                                    <a href={catalogIndex()}>
                                        Cancel
                                    </a>
                                </Button>
                                <Button disabled={processing} type="submit">
                                    Add item
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}
