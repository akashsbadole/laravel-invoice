<?php

namespace App\Http\Requests\Customers;

use App\Enums\FollowupStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class UpdateCustomerFollowupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->role->canWrite();
    }

    /**
     * Every field is optional per-request (`sometimes`) so this same
     * endpoint supports both a full edit and a quick one-field status
     * change — only the keys actually sent are validated and updated.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['sometimes', 'required', new Enum(FollowupStatus::class)],
            'followup_date' => ['sometimes', 'required', 'date'],
            'reminder_at' => ['sometimes', 'nullable', 'date'],
            'assigned_to' => ['sometimes', 'nullable', Rule::exists('users', 'id')->where('tenant_id', $this->user()->tenant_id)],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }
}
