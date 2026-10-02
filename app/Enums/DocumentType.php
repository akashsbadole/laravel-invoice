<?php

namespace App\Enums;

enum DocumentType: string
{
    case JewelryInvoice = 'jewelry_invoice';
    case GeneralInvoice = 'general_invoice';
    case Quotation = 'quotation';
    case DeliveryChallan = 'delivery_challan';
    case CreditNote = 'credit_note';
    case DebitNote = 'debit_note';

    public function label(): string
    {
        return match ($this) {
            self::JewelryInvoice => 'Jewelry Invoice',
            self::GeneralInvoice => 'General Invoice',
            self::Quotation => 'Quotation',
            self::DeliveryChallan => 'Delivery Challan',
            self::CreditNote => 'Credit Note',
            self::DebitNote => 'Debit Note',
        };
    }

    public function isQuotation(): bool
    {
        return $this === self::Quotation;
    }

    public function isDeliveryChallan(): bool
    {
        return $this === self::DeliveryChallan;
    }

    /**
     * Whether this document adjusts an existing invoice: a credit note for
     * returns and allowances, a debit note for undercharges.
     */
    public function isAdjustment(): bool
    {
        return $this === self::CreditNote || $this === self::DebitNote;
    }

    public function isPayable(): bool
    {
        return $this === self::JewelryInvoice || $this === self::GeneralInvoice;
    }

    /**
     * Whether this document counts as realised revenue.
     *
     * A quotation is a proposal, a delivery challan is a goods movement, and
     * a credit or debit note moves value onto an invoice that was already
     * counted — so none of the three belongs in sales, tax, ageing or
     * salesperson totals. Counting them is worse than cosmetic: converting a
     * quotation creates a separate invoice for the same value, so both totals
     * would include the same money, and a credit note would double-count a
     * sale instead of reducing it. The money effect of a note lands on the
     * parent invoice's balance instead.
     */
    public function isSale(): bool
    {
        return $this === self::JewelryInvoice || $this === self::GeneralInvoice;
    }

    /**
     * Document types that are adjustments against another invoice.
     *
     * @return list<string>
     */
    public static function adjustmentValues(): array
    {
        return [self::CreditNote->value, self::DebitNote->value];
    }
}
