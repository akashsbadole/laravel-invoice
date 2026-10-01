<?php

namespace App\Http\Requests\Customers;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCustomerNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->role->canWrite();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', 'in:private,communication'],
            'note' => ['required', 'string', 'max:2000'],
            'invoice_id' => ['nullable', Rule::exists('invoices', 'id')->where('tenant_id', $this->user()->tenant_id)],
        ];
    }
}
