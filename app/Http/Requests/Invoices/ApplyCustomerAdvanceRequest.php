<?php

namespace App\Http\Requests\Invoices;

use App\Enums\Permission;
use Illuminate\Foundation\Http\FormRequest;

class ApplyCustomerAdvanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->canDo(Permission::RecordPayments);
    }

    /**
     * The advance must belong to the invoice's customer and still hold a
     * balance — both are checked in the controller where the models are
     * already loaded.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'advance_id' => ['required', 'integer', 'exists:customer_advances,id'],
            'amount' => ['nullable', 'numeric', 'min:0.01'],
        ];
    }
}
