<?php

namespace App\Http\Requests\Settings;

use App\Enums\Permission;
use Illuminate\Foundation\Http\FormRequest;

class UpdateChargeTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->canDo(Permission::ManageSettings);
    }

    /**
     * A charge type's code and applies_to are permanent once created —
     * changing calculation semantics after invoices reference it would
     * silently reinterpret historical amounts.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'calculation_type' => ['required', 'in:fixed,percentage,per_gram,per_carat'],
            'default_rate' => ['nullable', 'numeric', 'min:0'],
            'is_taxable' => ['boolean'],
            'is_active' => ['boolean'],
        ];
    }
}
