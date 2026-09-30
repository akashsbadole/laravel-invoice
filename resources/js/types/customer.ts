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
    status: string;
    grand_total: string;
    balance_amount: string;
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
    assigned_staff_id: number | null;
    assigned_staff?: Staff | null;
    total_invoiced?: string | null;
    total_outstanding?: string | null;
    created_at: string;
    updated_at: string;
    invoices?: InvoiceSummary[];
    followups?: CustomerFollowup[];
    notes_log?: CustomerNote[];
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
};
