<?php

namespace App\Enums;

enum DocumentType: string
{
    case JewelryInvoice = 'jewelry_invoice';
    case GeneralInvoice = 'general_invoice';
    case Quotation = 'quotation';
    case DeliveryChallan = 'delivery_challan';

    public function label(): string
    {
        return match ($this) {
            self::JewelryInvoice => 'Jewelry Invoice',
            self::GeneralInvoice => 'General Invoice',
            self::Quotation => 'Quotation',
            self::DeliveryChallan => 'Delivery Challan',
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

    public function isPayable(): bool
    {
        return $this === self::JewelryInvoice || $this === self::GeneralInvoice;
    }

    /**
     * Whether this document counts as realised revenue.
     *
     * A quotation is a proposal and a delivery challan is a goods movement, so
     * neither belongs in sales, tax, ageing or salesperson totals. Counting
     * them is worse than cosmetic: converting a quotation creates a separate
     * invoice for the same value, so both totals would include the same money.
     */
    public function isSale(): bool
    {
        return $this === self::JewelryInvoice || $this === self::GeneralInvoice;
    }
}
