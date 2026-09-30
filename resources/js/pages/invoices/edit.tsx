import { Head } from '@inertiajs/react';
import Heading from '@/components/heading';
import InvoiceForm from '@/components/invoices/invoice-form';
import { dashboard } from '@/routes';
import { index } from '@/routes/invoices';
import type { Customer, Staff } from '@/types/customer';
import type {
    CatalogItem,
    ChargeType,
    Invoice,
    InvoiceFormData,
    InvoiceItemForm,
    InvoiceTemplateOption,
    MetalRate,
} from '@/types/invoice';

function toFormItem(item: Invoice['items'][number]): InvoiceItemForm {
    return {
        key: crypto.randomUUID(),
        item_name: item.item_name,
        description: item.description ?? '',
        item_code: item.item_code ?? '',
        hsn_code: item.hsn_code ?? '',
        metal_type: item.metal_type ?? '',
        purity: item.purity ?? '',
        huid_number: item.huid_number ?? '',
        stone_clarity: item.stone_clarity ?? '',
        stone_color: item.stone_color ?? '',
        stone_carat: Number(item.stone_carat),
        certificate_number: item.certificate_number ?? '',
        quantity: item.quantity,
        gross_weight: Number(item.gross_weight),
        net_weight: Number(item.net_weight),
        stone_weight: Number(item.stone_weight),
        rate_type: item.rate_type,
        rate: Number(item.rate),
        discount: Number(item.discount),
        tax_rate: Number(item.tax_rate),
        charges: item.charges.map((c) => ({ charge_type_id: c.charge_type_id, rate: Number(c.rate) })),
    };
}

export default function EditInvoice({
    invoice,
    customers,
    staff,
    chargeTypes,
    catalogItems,
    invoiceTemplates,
    metalRates,
    defaults,
}: {
    invoice: Invoice;
    customers: Customer[];
    staff: Staff[];
    chargeTypes: ChargeType[];
    catalogItems: CatalogItem[];
    invoiceTemplates: InvoiceTemplateOption[];
    metalRates: MetalRate[];
    defaults: {
        default_tax_rate: number;
        default_currency: string;
        invoice_number_preview: string;
        business_state_code: string | null;
    };
}) {
    const initialData: InvoiceFormData = {
        document_type: invoice.document_type,
        customer_id: invoice.customer.id,
        invoice_date: invoice.invoice_date.slice(0, 10),
        due_date: invoice.due_date ? invoice.due_date.slice(0, 10) : '',
        reference_number: invoice.reference_number ?? '',
        salesperson_id: invoice.salesperson?.id ?? '',
        invoice_template_id: invoice.invoice_template_id ?? '',
        tax_mode: invoice.tax_mode ?? 'single',
        pricing_mode: invoice.pricing_mode,
        discount: Number(invoice.discount),
        tax_rate: 0,
        notes: invoice.notes ?? '',
        terms: invoice.terms ?? '',
        items: invoice.items.map(toFormItem),
        invoice_charges: invoice.charges.map((c) => ({
            charge_type_id: c.charge_type_id ?? 0,
            rate: Number(c.rate),
        })),
    };

    return (
        <>
            <Head title={`Edit ${invoice.invoice_number}`} />

            <div className="mx-auto w-full max-w-3xl space-y-6 p-4 md:p-6">
                <Heading
                    title={`Edit ${invoice.invoice_number}`}
                    description="Changes recalculate totals from your current charge types."
                />

                <InvoiceForm
                    mode="edit"
                    invoiceId={invoice.id}
                    initialData={initialData}
                    customers={customers}
                    staff={staff}
                    chargeTypes={chargeTypes}
                    catalogItems={catalogItems}
                    invoiceTemplates={invoiceTemplates}
                    metalRates={metalRates}
                    businessStateCode={defaults.business_state_code}
                    invoiceNumberPreview={invoice.invoice_number}
                    defaultItemTaxRate={defaults.default_tax_rate}
                />
            </div>
        </>
    );
}

EditInvoice.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Invoices', href: index() },
    ],
};
