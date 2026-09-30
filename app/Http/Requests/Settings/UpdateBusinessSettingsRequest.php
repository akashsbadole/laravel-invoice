<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBusinessSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->role->canManageSettings();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'business_name' => ['required', 'string', 'max:255'],
            'logo' => ['nullable', 'image', 'max:2048'],
            'address' => ['nullable', 'string', 'max:1000'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'website' => ['nullable', 'url', 'max:255'],
            'tax_number' => ['nullable', 'string', 'max:50'],
            'state_code' => ['nullable', 'string', 'size:2'],
            'receipt_width' => ['required', 'in:58,80'],
            'sms_payment_reminders' => ['boolean'],
            'sms_birthday_wishes' => ['boolean'],

            'bank_name' => ['nullable', 'string', 'max:255'],
            'account_holder_name' => ['nullable', 'string', 'max:255'],
            'account_number' => ['nullable', 'string', 'max:50'],
            'ifsc_code' => ['nullable', 'string', 'max:20'],
            'upi_id' => ['nullable', 'string', 'max:100'],

            'invoice_prefix' => ['required', 'string', 'max:10'],
            'invoice_number_start' => ['required', 'integer', 'min:1'],
            'default_tax_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'default_currency' => ['required', 'string', 'size:3'],
            'invoice_terms' => ['nullable', 'string', 'max:5000'],
            'footer_text' => ['nullable', 'string', 'max:1000'],
            'signature' => ['nullable', 'image', 'max:1024'],
            'stamp' => ['nullable', 'image', 'max:1024'],
        ];
    }

    public function attributes(): array
    {
        return [
            'default_currency' => 'currency code',
        ];
    }
}
