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
}
