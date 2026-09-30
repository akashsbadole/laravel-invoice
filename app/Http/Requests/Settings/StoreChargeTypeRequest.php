<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreChargeTypeRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:100', 'alpha_dash', Rule::unique('charge_types', 'code')],
            'calculation_type' => ['required', 'in:fixed,percentage,per_gram,per_carat'],
            'applies_to' => ['required', 'in:item,invoice'],
            'default_rate' => ['nullable', 'numeric', 'min:0'],
            'is_taxable' => ['boolean'],
        ];
    }
}
