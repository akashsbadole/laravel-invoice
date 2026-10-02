<?php

namespace App\Http\Requests\CustomerGroups;

use App\Enums\Permission;
use App\Models\CustomerGroup;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCustomerGroupRequest extends FormRequest
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
        /** @var CustomerGroup $customerGroup */
        $customerGroup = $this->route('customerGroup');

        return [
            'name' => ['required', 'string', 'max:100', Rule::unique('customer_groups', 'name')
                ->where('tenant_id', $this->user()->tenant_id)
                ->ignore($customerGroup->id)],
            'discount_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
