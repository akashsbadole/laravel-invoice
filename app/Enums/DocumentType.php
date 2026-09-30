<?php

namespace App\Enums;

enum DocumentType: string
{
    case JewelryInvoice = 'jewelry_invoice';
    case GeneralInvoice = 'general_invoice';
    case Quotation = 'quotation';

    public function label(): string
    {
        return match ($this) {
            self::JewelryInvoice => 'Jewelry Invoice',
            self::GeneralInvoice => 'General Invoice',
            self::Quotation => 'Quotation',
        };
    }

    public function isQuotation(): bool
    {
        return $this === self::Quotation;
    }
}
