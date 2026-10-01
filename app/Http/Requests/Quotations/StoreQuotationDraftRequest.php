<?php

namespace App\Http\Requests\Quotations;

use App\Enums\Permission;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The catalog selection that seeds a quotation form.
 */
class StoreQuotationDraftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->canDo(Permission::CreateInvoices);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.catalog_item_id' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('catalog_items', 'id')->where('tenant_id', $this->user()->tenant_id),
            ],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:100000'],
            'items.*.rate' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
