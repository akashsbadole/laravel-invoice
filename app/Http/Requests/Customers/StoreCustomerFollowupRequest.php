<?php

namespace App\Http\Requests\Customers;

use App\Enums\Permission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCustomerFollowupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->canDo(Permission::SendMessages);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'followup_date' => ['required', 'date'],
            'reminder_at' => ['nullable', 'date'],
            'assigned_to' => ['nullable', Rule::exists('users', 'id')->where('tenant_id', $this->user()->tenant_id)],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
