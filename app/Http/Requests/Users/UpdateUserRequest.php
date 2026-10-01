<?php

namespace App\Http\Requests\Users;

use App\Enums\Permission;
use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->canDo(Permission::ManageUsers);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->route('user')?->id)],
            // Only roles a tenant may hand out — never the platform super admin.
            'role' => ['required', Rule::in(array_map(fn (UserRole $role) => $role->value, UserRole::assignable()))],
            'password' => ['nullable', Password::defaults()],
            'is_active' => ['boolean'],
        ];
    }
}
