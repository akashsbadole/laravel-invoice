<?php

namespace App\Http\Requests\Catalog;

use App\Support\Industry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCatalogItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->role->canWrite();
    }

    /**
     * @return list<string>
     */
    public function allowedRateTypes(): array
    {
        return Industry::rateTypes($this->user()->tenant?->industry);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $itemId = $this->route('catalogItem')?->id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'brand' => ['nullable', 'string', 'max:100'],
            'item_code' => ['nullable', 'string', 'max:100', Rule::unique('catalog_items', 'item_code')->where('tenant_id', $this->user()->tenant_id)->ignore($itemId)],
            'model_number' => ['nullable', 'string', 'max:100'],
            'hsn_code' => ['nullable', 'string', 'max:20'],
            'size_label' => ['nullable', 'string', 'max:50'],
            'finish' => ['nullable', 'string', 'max:50'],
            'grade' => ['nullable', 'string', 'max:50'],
            'specification' => ['nullable', 'string', 'max:255'],
            'unit_label' => ['nullable', 'string', 'max:20'],
            'metal_type' => ['nullable', 'string', 'max:50'],
            'purity' => ['nullable', 'string', 'max:20'],
            'rate_type' => ['required', Rule::in($this->allowedRateTypes())],
            'default_rate' => ['nullable', 'numeric', 'min:0'],
            'default_net_weight' => ['nullable', 'numeric', 'min:0'],
            'default_gross_weight' => ['nullable', 'numeric', 'min:0'],
            'default_length' => ['nullable', 'numeric', 'min:0'],
            'default_width' => ['nullable', 'numeric', 'min:0'],
            'default_wastage_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'attributes' => ['nullable', 'array'],
            'attributes.*' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['boolean'],
        ];
    }
}
