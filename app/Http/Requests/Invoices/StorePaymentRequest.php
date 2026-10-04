<?php

namespace App\Http\Requests\Invoices;

use App\Enums\Permission;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class StorePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $invoice = $this->route('invoice');

        return $this->user()->canDo(Permission::RecordPayments)
            && ($invoice === null || $invoice->document_type->isPayable());
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_date' => ['required', 'date'],
            'payment_method' => ['required', 'in:cash,bank_transfer,card,upi,cheque,other'],
            'reference_number' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $invoice = $this->route('invoice');
            $amount = (float) $this->input('amount');

            if ($invoice && $amount > (float) $invoice->balance_amount + 0.005) {
                $validator->errors()->add(
                    'amount',
                    sprintf('Payment amount exceeds remaining balance of Rs. %s.', number_format((float) $invoice->balance_amount, 2)),
                );
            }
        });
    }
}
