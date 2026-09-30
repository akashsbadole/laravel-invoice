<?php

namespace App\Enums;

/**
 * How the invoice's tax is presented. The total tax is identical in every
 * mode; CgstSgst / Igst only change how it is split for GST invoices.
 */
enum TaxMode: string
{
    case Single = 'single';
    case CgstSgst = 'cgst_sgst';
    case Igst = 'igst';

    public function label(): string
    {
        return match ($this) {
            self::Single => 'Single tax line',
            self::CgstSgst => 'CGST + SGST (same state)',
            self::Igst => 'IGST (other state)',
        };
    }
}
