import type { Customer, CustomerNote, Staff } from './customer';

export type ChargeCalculationType = 'fixed' | 'percentage' | 'per_gram' | 'per_carat';
export type ChargeAppliesTo = 'item' | 'invoice';
export type RateType =
    | 'per_gram'
    | 'per_carat'
    | 'per_piece'
    | 'fixed'
    | 'per_sqft'
    | 'per_sqm'
    | 'per_meter'
    | 'per_kg'
    | 'per_box'
    | 'per_litre'
    | 'per_unit';
export type PricingMode = 'manual' | 'jewelry_calculated';
export type TaxMode = 'single' | 'cgst_sgst' | 'igst';
export type InvoiceStatus =
    | 'unpaid'
    | 'partially_paid'
    | 'paid'
    | 'overdue'
    | 'cancelled'
    | 'refunded'
    | 'draft'
    | 'sent'
    | 'accepted'
    | 'converted'
    | 'closed';

export type CatalogStatus = 'draft' | 'active' | 'inactive' | 'discontinued';
export type PaymentMethod = 'cash' | 'bank_transfer' | 'card' | 'upi' | 'cheque' | 'other';

export type ChargeType = {
    id: number;
    name: string;
    code: string;
    calculation_type: ChargeCalculationType;
    applies_to: ChargeAppliesTo;
    default_rate: string | null;
    is_taxable: boolean;
    is_system?: boolean;
    is_active?: boolean;
    sort_order?: number;
};

export type ItemChargeInput = {
    charge_type_id: number;
    rate: number;
};

export type ItemChargeComputed = {
    charge_type_id: number;
    label: string;
    code: string;
    calculation_type: ChargeCalculationType;
    is_taxable: boolean;
    rate: number;
    amount: number;
};

export type LineType = 'sale' | 'exchange_credit';

export type InvoiceItemForm = {
    key: string;
    item_name: string;
    line_type: LineType;
    description: string;
    item_code: string;
    catalog_item_id: number | null;
    catalog_variant_id: number | null;
    hsn_code: string;
    brand: string;
    model_number: string;
    serial_number: string;
    warranty_months: number | null;
    size_label: string;
    finish: string;
    grade: string;
    specification: string;
    batch_number: string;
    length: number | null;
    width: number | null;
    height: number | null;
    wastage_percent: number | null;
    boxes: number | null;
    attributes: Record<string, string>;
    metal_type: string;
    purity: string;
    huid_number: string;
    stone_clarity: string;
    stone_color: string;
    stone_carat: number;
    certificate_number: string;
    quantity: number;
    gross_weight: number;
    net_weight: number;
    stone_weight: number;
    rate_type: RateType;
    rate: number;
    discount: number;
    tax_rate: number;
    charges: ItemChargeInput[];
};

export type InvoiceChargeInput = {
    charge_type_id: number;
    rate: number;
};

export type InvoiceFormData = {
    document_type: DocumentType;
    customer_id: number | '';
    invoice_date: string;
    due_date: string;
    reference_number: string;
    salesperson_id: number | '';
    invoice_template_id: number | '';
    pricing_mode: PricingMode;
    tax_mode: TaxMode;
    discount: number;
    tax_rate: number;
    /** TCS collected at source — added on top of the invoice total. */
    tcs_rate: number;
    /** TDS deducted at source — withheld from the amount the customer pays. */
    tds_rate: number;
    notes: string;
    terms: string;
    attributes?: Record<string, string>;
    quotation_valid_until?: string;
    rate_locked_at?: string;
    revision_note?: string;
    items: InvoiceItemForm[];
    invoice_charges: InvoiceChargeInput[];
};

export type InvoiceItem = {
    id: number;
    item_name: string;
    line_type: LineType;
    description: string | null;
    item_code: string | null;
    catalog_item_id: number | null;
    catalog_variant_id: number | null;
    hsn_code: string | null;
    brand: string | null;
    model_number: string | null;
    serial_number: string | null;
    warranty_months: number | null;
    size_label: string | null;
    finish: string | null;
    grade: string | null;
    specification: string | null;
    batch_number: string | null;
    length: string | null;
    width: string | null;
    height: string | null;
    wastage_percent: string | null;
    boxes: string | null;
    attributes: Record<string, string> | null;
    metal_type: string | null;
    purity: string | null;
    huid_number: string | null;
    stone_clarity: string | null;
    stone_color: string | null;
    stone_carat: string;
    certificate_number: string | null;
    quantity: number;
    gross_weight: string;
    net_weight: string;
    stone_weight: string;
    rate_type: RateType;
    rate: string;
    base_value: string;
    discount: string;
    tax_rate: string;
    tax: string;
    total: string;
    charges: ItemChargeComputed[];
};

/** A catalog product offered by the quotation builder. */
export type QuotationProduct = {
    id: number;
    name: string;
    brand: string | null;
    item_code: string | null;
    rate_type: RateType;
    default_rate: string | null;
    unit_label: string | null;
    description: string | null;
};

export type QuotationStatus =
    | 'draft'
    | 'sent'
    | 'accepted'
    | 'rejected'
    | 'expired'
    | 'converted';

export type Installment = {
    id: number;
    invoice_id: number;
    sequence: number;
    due_date: string;
    amount: string;
    status: 'pending' | 'paid' | 'waived';
    paid_at: string | null;
    notes: string | null;
    payment?: { id: number; amount: string; payment_date: string } | null;
};

export type InvoiceChargeRow = {
    id: number;
    charge_type_id: number | null;
    label: string;
    code: string | null;
    calculation_type: ChargeCalculationType;
    is_taxable: boolean;
    rate: string;
    amount: string;
};

export type Payment = {
    id: number;
    amount: string;
    payment_date: string;
    payment_method: PaymentMethod;
    reference_number: string | null;
    notes: string | null;
    receiver?: Staff | null;
};

export type ShareLink = {
    id: number;
    token: string;
    expires_at: string | null;
    is_active: boolean;
    sent_via: string | null;
    sent_at: string | null;
    viewed_at: string | null;
    downloaded_at: string | null;
    created_at: string;
};

export type ChargesSummaryRow = {
    code: string;
    label: string;
    amount: number;
};

export type DocumentType =
    | 'jewelry_invoice'
    | 'general_invoice'
    | 'quotation'
    | 'delivery_challan'
    | 'credit_note'
    | 'debit_note';

export type EInvoiceStatus = 'not_required' | 'pending' | 'generated' | 'failed';

export type Invoice = {
    id: number;
    uuid: string;
    invoice_number: string;
    invoice_date: string;
    due_date: string | null;
    reference_number: string | null;
    document_type: DocumentType;
    converted_to_id: number | null;
    parent_invoice_id: number | null;
    quotation_status: QuotationStatus | null;
    quotation_response: string | null;
    quotation_responded_at: string | null;
    quotation_valid_until: string | null;
    rate_locked_at: string | null;
    revision_number: number;
    revision_note: string | null;
    status: InvoiceStatus;
    pricing_mode: PricingMode;
    invoice_template_id: number | null;
    tax_mode: TaxMode;
    tax_breakdown: { label: string; rate: number; amount: number }[] | null;
    einvoice_status: EInvoiceStatus;
    irn: string | null;
    irn_ack_no: string | null;
    irn_ack_date: string | null;
    eway_bill_no: string | null;
    subtotal: string;
    charges_summary: ChargesSummaryRow[] | null;
    discount: string;
    tax: string;
    tds_rate: string;
    tds_amount: string;
    tcs_rate: string;
    tcs_amount: string;
    round_off: string;
    grand_total: string;
    paid_amount: string;
    balance_amount: string;
    notes: string | null;
    terms: string | null;
    attributes: Record<string, string> | null;
    cancelled_at: string | null;
    customer: Customer;
    salesperson?: Staff | null;
    creator?: Staff | null;
    items: InvoiceItem[];
    charges: InvoiceChargeRow[];
    payments: Payment[];
    share_links: ShareLink[];
    notes_log: CustomerNote[];
    installments: Installment[];
    /** The invoice a credit/debit note corrects — loaded on the show page. */
    parent_invoice?: {
        id: number;
        invoice_number: string;
        document_type: DocumentType;
        status: InvoiceStatus;
    } | null;
    /** Credit/debit notes issued against this invoice — show page only. */
    adjustment_notes?: Invoice[];
};

export type CatalogItem = {
    id: number;
    name: string;
    brand: string | null;
    item_code: string | null;
    model_number: string | null;
    hsn_code: string | null;
    size_label: string | null;
    finish: string | null;
    grade: string | null;
    specification: string | null;
    unit_label: string | null;
    metal_type: string | null;
    purity: string | null;
    rate_type: RateType;
    default_rate: string | null;
    default_net_weight: string | null;
    default_gross_weight: string | null;
    default_length: string | null;
    default_width: string | null;
    default_wastage_percent: string | null;
    attributes: Record<string, string> | null;
    description: string | null;
    status: CatalogStatus;

    // Commercial
    cost_price: string | null;
    minimum_order_quantity: number | string;
    pack_size: string | null;
    tax_inclusive?: boolean;

    // Traceability / physical
    barcode: string | null;
    color: string | null;
    material: string | null;
    thickness: string | null;
    warranty_months: number | string | null;
    manufacturer: string | null;
    country_of_origin: string | null;

    // Stock
    stock_tracked?: boolean;
    stock_quantity: string | number;
    reorder_level: string | number;
    stock_unit: string | null;
    is_low_stock?: boolean;

    // Media
    image_path?: string | null;
    image_url?: string | null;

    // One product, many sellable forms: sizes, colours, purities. Only the
    // active ones are offered when the product is picked on an invoice line.
    variants?: CatalogVariant[];
};

export type CatalogVariant = {
    id: number;
    label: string;
    item_code: string | null;
    rate: string | number | null;
    stock_quantity: string | number;
    is_active: boolean;
    sort_order: number;
};

export type InvoiceTemplateOption = {
    id: number;
    name: string;
    is_default: boolean;
};

export type InvoiceTemplate = InvoiceTemplateOption & {
    slug: string;
    layout_config: {
        accent_color: string;
        header_alignment: 'left' | 'center';
        show_huid: boolean;
        show_hsn: boolean;
        show_stone_details: boolean;
        show_bank_details: boolean;
        show_signature: boolean;
        show_stamp: boolean;
        show_qr_code: boolean;
        footer_note: string | null;
    };
};

export type MetalRate = {
    id: number;
    metal_type: string;
    purity: string;
    rate_date: string;
    rate_per_gram: string;
};
