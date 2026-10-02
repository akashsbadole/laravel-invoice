<?php

namespace App\Http\Requests\Invoices;

use App\Enums\Permission;
use Illuminate\Foundation\Http\FormRequest;

class StoreAdjustmentNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->canDo(Permission::CreateInvoices);
    }

    /**
     * Friendlier field names in validation messages.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'type' => 'note type',
            'amount' => 'amount',
            'tax_rate' => 'tax rate',
            'reason' => 'reason',
        ];
    }

    /**
     * The note itself is built server-side against the parent invoice, so
     * only the adjustment's own inputs are accepted from the dialog.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', 'in:credit_note,debit_note'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:100000000'],
            'tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }
}
