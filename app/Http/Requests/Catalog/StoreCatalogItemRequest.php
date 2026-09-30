<?php

namespace App\Http\Requests\Catalog;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCatalogItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->role->canWrite();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'item_code' => ['nullable', 'string', 'max:100', Rule::unique('catalog_items', 'item_code')],
            'hsn_code' => ['nullable', 'string', 'max:20'],
            'metal_type' => ['nullable', 'string', 'max:50'],
            'purity' => ['nullable', 'string', 'max:20'],
            'rate_type' => ['required', 'in:per_gram,per_carat,per_piece,fixed'],
            'default_rate' => ['nullable', 'numeric', 'min:0'],
            'default_net_weight' => ['nullable', 'numeric', 'min:0'],
            'default_gross_weight' => ['nullable', 'numeric', 'min:0'],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
