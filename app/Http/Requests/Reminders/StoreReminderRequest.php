<?php

namespace App\Http\Requests\Reminders;

use App\Enums\Permission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreReminderRequest extends FormRequest
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
            'title' => ['required', 'string', 'max:255'],
            'remind_on' => ['required', 'date'],
            'customer_id' => ['nullable', Rule::exists('customers', 'id')->where('tenant_id', $this->user()->tenant_id)],
            'assigned_to' => ['nullable', Rule::exists('users', 'id')->where('tenant_id', $this->user()->tenant_id)],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
