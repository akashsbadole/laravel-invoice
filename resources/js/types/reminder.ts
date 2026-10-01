import type { Staff } from './customer';

export type ReminderCustomer = { id: number; full_name: string; mobile_number: string };

export type DueInvoiceReminder = {
    id: number;
    invoice_number: string;
    due_date: string | null;
    balance_amount: string;
    customer: ReminderCustomer;
};

export type DueFollowup = {
    id: number;
    followup_date: string;
    reminder_at: string | null;
    notes: string | null;
    status: string;
    customer: ReminderCustomer;
    assignee: Staff | null;
};

export type OccasionReminder = {
    id: number;
    full_name: string;
    mobile_number: string;
    date: string;
    days_until: number;
};

export type CustomReminder = {
    id: number;
    title: string;
    notes: string | null;
    remind_on: string;
    is_done: boolean;
    customer: ReminderCustomer | null;
};

/**
 * A quotation whose validity window is about to close. Staff chase these
 * before the customer can, so the deadline is the point of the reminder.
 */
export type ExpiringQuote = {
    id: number;
    invoice_number: string;
    quotation_valid_until: string;
    grand_total: string;
    customer: ReminderCustomer;
};
