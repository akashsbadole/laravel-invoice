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

export type MessageLogRow = {
    id: number;
    channel: string;
    driver: string;
    to: string;
    body: string;
    status: string;
    error: string | null;
    created_at: string;
    customer: ReminderCustomer | null;
    invoice: { id: number; invoice_number: string } | null;
};
