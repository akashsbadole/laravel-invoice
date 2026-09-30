<?php

namespace App\Http\Requests\Invoices;

use Illuminate\Foundation\Http\FormRequest;

class StoreInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->role->canWrite();
    }

    /**
     * Friendlier field names in validation messages.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'items.*.item_name' => 'item name',
            'items.*.quantity' => 'quantity',
            'items.*.rate' => 'rate',
            'items.*.rate_type' => 'rate basis',
            'items.*.net_weight' => 'net weight',
            'items.*.gross_weight' => 'gross weight',
            'items.*.stone_weight' => 'stone weight',
            'items.*.stone_carat' => 'stone carat',
            'items.*.discount' => 'item discount',
            'items.*.tax_rate' => 'item tax rate',
            'items.*.charges.*.rate' => 'charge rate',
            'invoice_charges.*.rate' => 'charge rate',
            'customer_id' => 'customer',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'customer_id' => ['required', 'exists:customers,id'],
            'invoice_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:invoice_date'],
            'reference_number' => ['nullable', 'string', 'max:255'],
            'salesperson_id' => ['nullable', 'exists:users,id'],
            'invoice_template_id' => ['nullable', 'exists:invoice_templates,id'],
            'pricing_mode' => ['required', 'in:manual,jewelry_calculated'],
            'tax_mode' => ['nullable', 'in:single,cgst_sgst,igst'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'terms' => ['nullable', 'string', 'max:2000'],

            'items' => ['required', 'array', 'min:1'],
            'items.*.item_name' => ['required', 'string', 'max:255'],
            'items.*.description' => ['nullable', 'string', 'max:1000'],
            'items.*.item_code' => ['nullable', 'string', 'max:100'],
            'items.*.hsn_code' => ['nullable', 'string', 'max:20'],
            'items.*.metal_type' => ['nullable', 'string', 'max:50'],
            'items.*.purity' => ['nullable', 'string', 'max:20'],
            'items.*.huid_number' => ['nullable', 'string', 'max:50'],
            'items.*.stone_clarity' => ['nullable', 'string', 'max:20'],
            'items.*.stone_color' => ['nullable', 'string', 'max:20'],
            'items.*.stone_carat' => ['nullable', 'numeric', 'min:0'],
            'items.*.certificate_number' => ['nullable', 'string', 'max:100'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.gross_weight' => ['nullable', 'numeric', 'min:0'],
            'items.*.net_weight' => ['nullable', 'numeric', 'min:0'],
            'items.*.stone_weight' => ['nullable', 'numeric', 'min:0'],
            'items.*.rate_type' => ['required', 'in:per_gram,per_carat,per_piece,fixed'],
            'items.*.rate' => ['required', 'numeric', 'min:0'],
            'items.*.discount' => ['nullable', 'numeric', 'min:0'],
            'items.*.tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],

            'items.*.charges' => ['nullable', 'array'],
            'items.*.charges.*.charge_type_id' => ['required', 'exists:charge_types,id'],
            'items.*.charges.*.rate' => ['nullable', 'numeric'],

            'invoice_charges' => ['nullable', 'array'],
            'invoice_charges.*.charge_type_id' => ['required', 'exists:charge_types,id'],
            'invoice_charges.*.rate' => ['nullable', 'numeric'],
        ];
    }
}
