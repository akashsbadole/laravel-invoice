<?php

namespace App\Http\Requests\Settings;

use App\Enums\Permission;
use App\Support\Industry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBusinessSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->canDo(Permission::ManageSettings);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'business_name' => ['required', 'string', 'max:255'],
            'industry' => ['required', 'string', Rule::in(array_keys(Industry::all()))],
            'logo' => ['nullable', 'image', 'max:2048'],
            'address' => ['nullable', 'string', 'max:1000'],
            'pincode' => ['nullable', 'string', 'size:6'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'website' => ['nullable', 'url', 'max:255'],
            'tax_number' => ['nullable', 'string', 'max:50'],
            'state_code' => ['nullable', 'string', 'size:2'],
            'receipt_width' => ['required', 'in:58,80'],
            'sms_payment_reminders' => ['boolean'],
            'email_payment_reminders' => ['boolean'],
            'sms_birthday_wishes' => ['boolean'],
            'sms_anniversary_wishes' => ['boolean'],
            'quotation_customer_decisions' => ['boolean'],
            'quotation_show_updates' => ['boolean'],
            'show_all_catalog_fields' => ['boolean'],
            'sms_driver' => ['required', 'in:log,twilio,http'],
            'sms_country_code' => ['required', 'string', 'max:5'],
            'sms_twilio_sid' => ['nullable', 'string', 'max:255'],
            'sms_twilio_token' => ['nullable', 'string', 'max:255'],
            'sms_twilio_from' => ['nullable', 'string', 'max:50'],
            'sms_http_url' => ['nullable', 'string', 'max:500'],
            'sms_http_token' => ['nullable', 'string', 'max:500'],
            'sms_http_to_field' => ['nullable', 'string', 'max:50'],
            'sms_http_message_field' => ['nullable', 'string', 'max:50'],

            'bank_name' => ['nullable', 'string', 'max:255'],
            'account_holder_name' => ['nullable', 'string', 'max:255'],
            'account_number' => ['nullable', 'string', 'max:50'],
            'ifsc_code' => ['nullable', 'string', 'max:20'],
            'upi_id' => ['nullable', 'string', 'max:100'],

            'invoice_prefix' => ['required', 'string', 'max:10'],
            'invoice_number_start' => ['required', 'integer', 'min:1'],
            'quotation_prefix' => ['required', 'string', 'max:10'],
            'challan_prefix' => ['required', 'string', 'max:10'],
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
