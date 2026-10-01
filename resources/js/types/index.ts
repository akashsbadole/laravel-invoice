export interface User {
    id: number;
    name: string;
    email: string;
    role: string;
    is_active: boolean;
    email_verified_at: string | null;
    last_login_at: string | null;
    created_at: string;
    updated_at: string;
}

export interface Customer {
    id: number;
    name: string;
    phone: string;
    email: string | null;
    address: string | null;
    city: string | null;
    state: string | null;
    pincode: string | null;
    gst_number: string | null;
    birthday: string | null;
    anniversary: string | null;
    notes: string | null;
    is_active: boolean;
    created_by: number;
    assigned_staff_id: number | null;
    created_at: string;
    updated_at: string;
}

export interface Invoice {
    id: number;
    invoice_number: string;
    customer_id: number;
    invoice_date: string;
    due_date: string | null;
    subtotal: number;
    discount: number;
    tax_amount: number;
    total: number;
    amount_paid: number;
    balance_due: number;
    status: string;
    notes: string | null;
    created_by: number;
    salesperson_id: number | null;
    created_at: string;
    updated_at: string;
}

export interface InvoiceItem {
    id: number;
    invoice_id: number;
    catalog_item_id: number | null;
    description: string;
    quantity: number;
    unit_price: number;
    total: number;
    created_at: string;
    updated_at: string;
}

export interface Payment {
    id: number;
    invoice_id: number;
    amount: number;
    payment_method: string;
    payment_date: string;
    notes: string | null;
    created_by: number;
    created_at: string;
    updated_at: string;
}

export interface CatalogItem {
    id: number;
    name: string;
    sku: string | null;
    category: string | null;
    description: string | null;
    unit_price: number;
    is_active: boolean;
    created_at: string;
    updated_at: string;
}

export interface ChargeType {
    id: number;
    name: string;
    type: string;
    value: number;
    is_active: boolean;
    created_at: string;
    updated_at: string;
}

export interface MetalRate {
    id: number;
    metal_type: string;
    purity: string;
    rate: number;
    effective_date: string;
    created_at: string;
    updated_at: string;
}

export interface Reminder {
    id: number;
    customer_id: number;
    invoice_id: number | null;
    type: string;
    title: string;
    notes: string | null;
    due_date: string;
    is_completed: boolean;
    assigned_to: number | null;
    created_by: number;
    created_at: string;
    updated_at: string;
}

export interface InvoiceTemplate {
    id: number;
    name: string;
    content: string;
    is_default: boolean;
    created_at: string;
    updated_at: string;
}

export interface BusinessSetting {
    id: number;
    key: string;
    value: string | null;
    created_at: string;
    updated_at: string;
}

export interface ActivityLog {
    id: number;
    log_name: string;
    description: string;
    subject_type: string | null;
    subject_id: number | null;
    causer_type: string | null;
    causer_id: number | null;
    properties: string | null;
    created_at: string;
    updated_at: string;
}

export interface InvoiceShareLink {
    id: number;
    invoice_id: number;
    token: string;
    password: string | null;
    expires_at: string | null;
    is_active: boolean;
    sent_at: string | null;
    created_at: string;
    updated_at: string;
}

export interface InvoiceEvent {
    id: number;
    invoice_id: number;
    event_type: string;
    description: string | null;
    created_by: number | null;
    created_at: string;
    updated_at: string;
}

export interface CustomerFollowup {
    id: number;
    customer_id: number;
    followup_date: string;
    notes: string;
    assigned_to: number | null;
    created_by: number;
    created_at: string;
    updated_at: string;
}

export interface CustomerNote {
    id: number;
    customer_id: number;
    note: string;
    created_by: number;
    created_at: string;
    updated_at: string;
}

export interface InvoiceCharge {
    id: number;
    invoice_id: number;
    charge_type_id: number;
    amount: number;
    created_at: string;
    updated_at: string;
}

export interface InvoiceItemCharge {
    id: number;
    invoice_item_id: number;
    charge_type_id: number;
    amount: number;
    created_at: string;
    updated_at: string;
}

export interface PaginatedResponse<T> {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
    first_page_url: string;
    last_page_url: string;
    next_page_url: string | null;
    prev_page_url: string | null;
    path: string;
    links: Array<{
        url: string | null;
        label: string;
        active: boolean;
    }>;
}

export interface BreadcrumbItem {
    title: string;
    href?: string;
}

export interface SharedProps {
    auth: {
        user: User | null;
    };
    flash?: {
        message?: string;
    };
}
