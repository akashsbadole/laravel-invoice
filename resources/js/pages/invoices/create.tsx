import { Head } from '@inertiajs/react';
import Heading from '@/components/heading';
import InvoiceForm, { newInvoiceItem } from '@/components/invoices/invoice-form';
import { dashboard } from '@/routes';
import { create, index } from '@/routes/invoices';
import type { Customer, Staff } from '@/types/customer';
import type {
    CatalogItem,
    ChargeType,
    InvoiceFormData,
    InvoiceTemplateOption,
    MetalRate,
} from '@/types/invoice';

function today(): string {
    return new Date().toISOString().slice(0, 10);
}

export default function CreateInvoice({
    customers,
    staff,
    chargeTypes,
    catalogItems,
    invoiceTemplates,
    metalRates,
    preselectedCustomerId,
    defaults,
}: {
    customers: Customer[];
    staff: Staff[];
    chargeTypes: ChargeType[];
    catalogItems: CatalogItem[];
    invoiceTemplates: InvoiceTemplateOption[];
    metalRates: MetalRate[];
    preselectedCustomerId: number | null;
    defaults: {
        default_tax_rate: number;
        default_currency: string;
        invoice_number_preview: string;
        business_state_code: string | null;
    };
}) {
    const initialData: InvoiceFormData = {
        document_type: 'jewelry_invoice',
        customer_id: preselectedCustomerId ?? '',
        invoice_date: today(),
        due_date: '',
        reference_number: '',
        salesperson_id: '',
        invoice_template_id: invoiceTemplates.find((t) => t.is_default)?.id ?? '',
        pricing_mode: 'jewelry_calculated',
        tax_mode: 'single',
        discount: 0,
        tax_rate: 0,
        notes: '',
        terms: '',
        items: [newInvoiceItem(defaults.default_tax_rate)],
        invoice_charges: [],
    };

    return (
        <>
            <Head title="New invoice" />

            <div className="mx-auto w-full max-w-3xl space-y-6 p-4 md:p-6">
                <Heading
                    title="New invoice"
                    description="Add items, charges apply automatically from your charge types."
                />

                <InvoiceForm
                    mode="create"
                    initialData={initialData}
                    customers={customers}
                    staff={staff}
                    chargeTypes={chargeTypes}
                    catalogItems={catalogItems}
                    invoiceTemplates={invoiceTemplates}
                    metalRates={metalRates}
                    businessStateCode={defaults.business_state_code}
                    invoiceNumberPreview={defaults.invoice_number_preview}
                    defaultItemTaxRate={defaults.default_tax_rate}
                />
            </div>
        </>
    );
}

CreateInvoice.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Invoices', href: index() },
        { title: 'New', href: create() },
    ],
};
