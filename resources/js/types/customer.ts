import type { DocumentType } from './invoice';

export type Staff = {
    id: number;
    name: string;
};

export type CustomerType = 'individual' | 'business';

export type CustomerNote = {
    id: number;
    customer_id: number;
    invoice_id: number | null;
    type: 'private' | 'communication';
    note: string;
    created_by: number | null;
    created_at: string;
    creator?: Staff | null;
};

export type FollowupStatus =
    | 'pending'
    | 'contacted'
    | 'waiting_for_response'
    | 'completed'
    | 'cancelled';

export type CustomerFollowup = {
    id: number;
    customer_id: number;
    assigned_to: number | null;
    followup_date: string;
    reminder_at: string | null;
    status: FollowupStatus;
    notes: string | null;
    created_at: string;
    assignee?: Staff | null;
};

export type InvoiceSummary = {
    id: number;
    uuid: string;
    invoice_number: string;
    invoice_date: string;
    document_type: DocumentType;
    status: string;
    grand_total: string;
    balance_amount: string;
};

export type PortalQuotation = {
    id: number;
    invoice_number: string;
    invoice_date: string;
    valid_until: string | null;
    status: string;
    status_label: string;
    is_open: boolean;
    grand_total: number;
    can_decide: boolean;
    share_token: string;
};

export type GstinType = 'regular' | 'composition' | 'unregistered' | 'consumer';

export type ContactChannel =
    | 'whatsapp'
    | 'sms'
    | 'email'
    | 'call';

export type PriceTier = 'a' | 'b' | 'c';

/** A named pricing tier (Wholesale, Staff, VIP). */
export type CustomerGroup = {
    id: number;
    name: string;
    discount_percent: string;
    is_active: boolean;
    sort_order?: number;
    customers_count?: number;
};

export type AdvanceStatus = 'available' | 'applied' | 'refunded';

export type CustomerAdvance = {
    id: number;
    customer_id: number;
    amount: string;
    applied_amount: string;
    advance_date: string;
    payment_method: string;
    reference_number: string | null;
    notes: string | null;
    status: AdvanceStatus;
    created_at: string;
    creator?: Staff | null;
};

export type Customer = {
    id: number;
    full_name: string;
    mobile_number: string;
    email: string | null;
    address: string | null;
    tax_number: string | null;
    notes: string | null;
    customer_type: CustomerType;
    state_code: string | null;
    birthday: string | null;
    anniversary: string | null;
    attributes?: Record<string, string> | null;
    gstin_type: GstinType | null;
    place_of_supply: string | null;
    credit_limit: string | null;
    credit_days: number | null;
    price_tier: PriceTier | null;
    customer_group_id: number | null;
    group?: CustomerGroup | null;
    /**
     * Percentage the customer's group takes off unpriced invoice lines.
     * Only the invoice form ships it; everywhere else it is absent.
     */
    group_discount_percent?: number;
    preferred_contact_channel: ContactChannel | null;
    referral_source: string | null;
    tags: string[] | null;
    /** Outstanding balance that counts against the credit limit. */
    credit_outstanding?: string;
    /** Null when the business does not extend credit to this customer. */
    credit_overrun?: string | null;
    assigned_staff_id: number | null;
    assigned_staff?: Staff | null;
    total_invoiced?: string | null;
    total_outstanding?: string | null;
    created_at: string;
    updated_at: string;
    invoices?: InvoiceSummary[];
    followups?: CustomerFollowup[];
    notes_log?: CustomerNote[];
    advances?: CustomerAdvance[];
};

export type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

export type Paginated<T> = {
    data: T[];
    links: PaginationLink[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
};

export type CustomerFilters = {
    search?: string;
    customer_type?: CustomerType | '';
    assigned_staff_id?: number | string | '';
    tag?: string;
};
