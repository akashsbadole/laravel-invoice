<?php

namespace App\Http\Requests\Invoices;

use App\Enums\DocumentType;
use Illuminate\Contracts\Validation\Validator;

class UpdateInvoiceRequest extends StoreInvoiceRequest
{
    /**
     * The document type drives numbering, status and payment behaviour, so it
     * can only be changed while the invoice is still untouched — no payments
     * recorded and not already converted from a quotation.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $invoice = $this->route('invoice');
            $requested = $this->input('document_type');

            if (! $invoice || $requested === null || $requested === $invoice->document_type->value) {
                return;
            }

            if (! DocumentType::tryFrom($requested)) {
                return;
            }

            if ($invoice->converted_to_id !== null) {
                $validator->errors()->add('document_type', 'This document has already been converted and its type cannot change.');

                return;
            }

            if ($invoice->payments()->exists()) {
                $validator->errors()->add('document_type', 'Payments are recorded against this document, so its type cannot change.');
            }
        });
    }
}
