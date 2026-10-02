<?php

namespace App\Http\Requests\Customers;

use App\Enums\Permission;
use Illuminate\Foundation\Http\FormRequest;

class StoreCustomerAdvanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->canDo(Permission::RecordPayments);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'min:0.01', 'max:100000000'],
            'advance_date' => ['required', 'date'],
            'payment_method' => ['required', 'in:cash,bank_transfer,card,upi,cheque,other'],
            'reference_number' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
