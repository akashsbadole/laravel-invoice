<?php

namespace App\Http\Requests\Catalog;

use Illuminate\Foundation\Http\FormRequest;

class StoreCatalogItemRequest extends FormRequest
{
    use CatalogItemRules;

    public function authorize(): bool
    {
        return $this->user()->role->canWrite();
    }

    /**
     * @return array<string,mixed>
     */
    public function rules(): array
    {
        return $this->catalogRules();
    }
}