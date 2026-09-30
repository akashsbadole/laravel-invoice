import type { PaymentMethod } from './invoice';

export type PaymentRow = {
    id: number;
    amount: string;
    payment_date: string;
    payment_method: PaymentMethod;
    reference_number: string | null;
    invoice: { id: number; invoice_number: string; customer: { id: number; full_name: string } } | null;
    receiver: { id: number; name: string } | null;
};
