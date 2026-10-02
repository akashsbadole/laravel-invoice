<?php

namespace App\Http\Requests\Settings;

use App\Enums\Permission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInvoiceTemplateRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:100', 'alpha_dash', Rule::unique('invoice_templates', 'slug')->where('tenant_id', $this->user()->tenant_id)],
            'accent_color' => ['required', 'string', 'max:7'],
            'header_alignment' => ['required', 'in:left,center'],
            'footer_note' => ['nullable', 'string', 'max:500'],
            'show_huid' => ['boolean'],
            'show_hsn' => ['boolean'],
            'show_stone_details' => ['boolean'],
            'show_bank_details' => ['boolean'],
            'show_signature' => ['boolean'],
            'show_stamp' => ['boolean'],
            'show_qr_code' => ['boolean'],
        ];
    }
}
