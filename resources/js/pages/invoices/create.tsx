import { Head } from '@inertiajs/react';
import Heading from '@/components/heading';
import InvoiceForm, { newInvoiceItem } from '@/components/invoices/invoice-form';
import { dashboard } from '@/routes';
import { create, index } from '@/routes/invoices';
import type { Customer, Staff } from '@/types/customer';
import type { IndustryConfig } from '@/lib/industries';
import type {
    CatalogItem,
    ChargeType,
    DocumentType,
    InvoiceFormData,
    InvoiceItemForm,
    InvoiceTemplateOption,
    MetalRate,
} from '@/types/invoice';

/** One catalog selection handed over by the quotation builder. */
export type QuotationDraftItem = {
    catalog_item_id: number;
    item_name: string;
    item_code: string | null;
    hsn_code: string | null;
    description: string | null;
    brand: string | null;
    model_number: string | null;
    size_label: string | null;
    finish: string | null;
    grade: string | null;
    specification: string | null;
    metal_type: string | null;
    purity: string | null;
    attributes: Record<string, string>;
    quantity: number;
    rate_type: InvoiceItemForm['rate_type'];
    rate: number;
    net_weight: number;
    gross_weight: number;
    length: number | null;
    width: number | null;
    wastage_percent: number | null;
};

function today(): string {
    return new Date().toISOString().slice(0, 10);
}

function toFormItem(draft: QuotationDraftItem, taxRate: number): InvoiceItemForm {
    return {
        ...newInvoiceItem(taxRate, draft.rate_type),
        item_name: draft.item_name,
        item_code: draft.item_code ?? '',
        catalog_item_id: draft.catalog_item_id,
        hsn_code: draft.hsn_code ?? '',
        description: draft.description ?? '',
        brand: draft.brand ?? '',
        model_number: draft.model_number ?? '',
        size_label: draft.size_label ?? '',
        finish: draft.finish ?? '',
        grade: draft.grade ?? '',
        specification: draft.specification ?? '',
        metal_type: draft.metal_type ?? '',
        purity: draft.purity ?? '',
        attributes: draft.attributes ?? {},
        quantity: draft.quantity,
        rate_type: draft.rate_type,
        rate: draft.rate,
        net_weight: draft.net_weight,
        gross_weight: draft.gross_weight,
        length: draft.length,
        width: draft.width,
        wastage_percent: draft.wastage_percent,
    };
}

export default function CreateInvoice({
    customers,
    staff,
    chargeTypes,
    catalogItems,
    invoiceTemplates,
    metalRates,
    industryConfig,
    preselectedCustomerId,
    defaults,
    requestedDocumentType,
    draftItems = [],
}: {
    customers: Customer[];
    staff: Staff[];
    chargeTypes: ChargeType[];
    catalogItems: CatalogItem[];
    invoiceTemplates: InvoiceTemplateOption[];
    metalRates: MetalRate[];
    industryConfig: IndustryConfig;
    preselectedCustomerId: number | null;
    defaults: {
        default_tax_rate: number;
        default_currency: string;
        invoice_number_preview: string;
        business_state_code: string | null;
    };
    requestedDocumentType: DocumentType;
    draftItems?: QuotationDraftItem[];
}) {
    const firstRateType = industryConfig.rate_types[0] ?? 'per_piece';

    const initialData: InvoiceFormData = {
        document_type: requestedDocumentType ?? industryConfig.document_type,
        customer_id: preselectedCustomerId ?? '',
        invoice_date: today(),
        due_date: '',
        reference_number: '',
        salesperson_id: '',
        invoice_template_id: invoiceTemplates.find((t) => t.is_default)?.id ?? '',
        pricing_mode:
            industryConfig.pricing_mode === 'jewelry_calculated'
                ? 'jewelry_calculated'
                : 'manual',
        tax_mode: 'single',
        discount: 0,
        tax_rate: 0,
        notes: '',
        terms: '',
        items: draftItems.length > 0
            ? draftItems.map((draft) => toFormItem(draft, defaults.default_tax_rate))
            : [newInvoiceItem(defaults.default_tax_rate, firstRateType)],
        invoice_charges: [],
    };

    const titles: Record<DocumentType, string> = {
        jewelry_invoice: 'New jewelry invoice',
        general_invoice: 'New invoice',
        quotation: 'New quotation',
        delivery_challan: 'New delivery challan',
    };

    return (
        <>
            <Head title={titles[initialData.document_type]} />

            <div className="mx-auto w-full max-w-3xl space-y-6 p-4 md:p-6">
                {draftItems.length > 0 && (
                    <p className="rounded-md border border-brand/40 bg-brand/5 px-3 py-2 text-sm">
                        Pre-filled with {draftItems.length} product
                        {draftItems.length === 1 ? '' : 's'} from your catalog.
                        Edit anything before saving.
                    </p>
                )}

                <Heading
                    title={titles[initialData.document_type]}
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
                    industryConfig={industryConfig}
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