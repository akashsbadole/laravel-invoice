<?php

namespace App\Http\Requests\Catalog;

use App\Enums\Permission;
use Illuminate\Foundation\Http\FormRequest;

class StoreCatalogItemRequest extends FormRequest
{
    use CatalogItemRules;

    public function authorize(): bool
    {
        return $this->user()->canDo(Permission::ManageCatalog);
    }

    /**
     * @return array<string,mixed>
     */
    public function rules(): array
    {
        return $this->catalogRules();
    }
}
