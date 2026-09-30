<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;

class StoreMetalRateRequest extends FormRequest
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
            'metal_type' => ['required', 'string', 'max:50'],
            'purity' => ['required', 'string', 'max:20'],
            'rate_date' => ['required', 'date'],
            'rate_per_gram' => ['required', 'numeric', 'min:0.01'],
        ];
    }
}
