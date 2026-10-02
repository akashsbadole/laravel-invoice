<?php

namespace App\Http\Requests\Customers;

use App\Enums\ContactChannel;
use App\Enums\GstinType;
use App\Enums\Permission;
use App\Enums\PriceTier;
use App\Support\Attributes;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->canDo(Permission::ManageCustomers);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'full_name' => ['required', 'string', 'max:255'],
            'mobile_number' => ['required', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:1000'],
            'tax_number' => ['nullable', 'string', 'max:50'],
            'gstin_type' => ['nullable', Rule::enum(GstinType::class)],
            'state_code' => ['nullable', 'string', 'size:2'],
            'place_of_supply' => ['nullable', 'string', 'size:2'],
            // A limit of zero is meaningful (cash only), so only null disables
            // the check.
            'credit_limit' => ['nullable', 'numeric', 'min:0'],
            'credit_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'price_tier' => ['nullable', Rule::enum(PriceTier::class)],
            'preferred_contact_channel' => ['nullable', Rule::enum(ContactChannel::class)],
            'referral_source' => ['nullable', 'string', 'max:100'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:50'],
            'birthday' => ['nullable', 'date', 'before:today'],
            'anniversary' => ['nullable', 'date', 'before_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'customer_type' => ['required', 'in:individual,business'],
            'assigned_staff_id' => ['nullable', Rule::exists('users', 'id')->where('tenant_id', $this->user()->tenant_id)],
            ...Attributes::rules(),
        ];
    }
}
