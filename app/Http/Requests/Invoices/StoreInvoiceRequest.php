<?php

namespace App\Http\Requests\Invoices;

use App\Enums\DocumentType;
use App\Enums\LineType;
use App\Enums\Permission;
use App\Support\Attributes;
use App\Support\Industry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->canDo(Permission::CreateInvoices);
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
     * Rate types are an industry capability (config/industries.php): a tiles
     * shop may bill per sq ft, a jeweller per gram. Rejecting anything the
     * tenant's industry cannot compute keeps the calculator in range.
     *
     * @return list<string>
     */
    public function allowedRateTypes(): array
    {
        return Industry::rateTypes($this->user()->tenant?->industry);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'customer_id' => ['required', Rule::exists('customers', 'id')->where('tenant_id', $this->user()->tenant_id)],
            'document_type' => ['sometimes', Rule::enum(DocumentType::class)],
            'invoice_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:invoice_date'],
            // Only a quotation has a validity window; the expiry sweep reads it.
            'quotation_valid_until' => ['nullable', 'date', 'after_or_equal:invoice_date'],
            'reference_number' => ['nullable', 'string', 'max:255'],
            'salesperson_id' => ['nullable', Rule::exists('users', 'id')->where('tenant_id', $this->user()->tenant_id)],
            'invoice_template_id' => ['nullable', Rule::exists('invoice_templates', 'id')->where('tenant_id', $this->user()->tenant_id)],
            'pricing_mode' => ['required', 'in:manual,jewelry_calculated'],
            'tax_mode' => ['nullable', 'in:single,cgst_sgst,igst'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'terms' => ['nullable', 'string', 'max:2000'],
            ...Attributes::rules(),

            'items' => ['required', 'array', 'min:1'],
            'items.*.item_name' => ['required', 'string', 'max:255'],
            'items.*.line_type' => ['nullable', Rule::enum(LineType::class)],
            'items.*.description' => ['nullable', 'string', 'max:1000'],
            'items.*.item_code' => ['nullable', 'string', 'max:100'],
            'items.*.catalog_item_id' => ['nullable', Rule::exists('catalog_items', 'id')->where('tenant_id', $this->user()->tenant_id)],
            'items.*.hsn_code' => ['nullable', 'string', 'max:20'],
            'items.*.brand' => ['nullable', 'string', 'max:100'],
            'items.*.model_number' => ['nullable', 'string', 'max:100'],
            'items.*.serial_number' => ['nullable', 'string', 'max:100'],
            'items.*.warranty_months' => ['nullable', 'integer', 'min:0', 'max:600'],
            'items.*.size_label' => ['nullable', 'string', 'max:50'],
            'items.*.finish' => ['nullable', 'string', 'max:50'],
            'items.*.grade' => ['nullable', 'string', 'max:50'],
            'items.*.specification' => ['nullable', 'string', 'max:255'],
            'items.*.batch_number' => ['nullable', 'string', 'max:100'],
            'items.*.length' => ['nullable', 'numeric', 'min:0'],
            'items.*.width' => ['nullable', 'numeric', 'min:0'],
            'items.*.height' => ['nullable', 'numeric', 'min:0'],
            'items.*.wastage_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'items.*.boxes' => ['nullable', 'numeric', 'min:0'],
            'items.*.attributes' => ['nullable', 'array'],
            'items.*.attributes.*' => ['nullable', 'string', 'max:255'],
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
            'items.*.rate_type' => ['required', Rule::in($this->allowedRateTypes())],
            'items.*.rate' => ['required', 'numeric', 'min:0'],
            'items.*.discount' => ['nullable', 'numeric', 'min:0'],
            'items.*.tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],

            'items.*.charges' => ['nullable', 'array'],
            'items.*.charges.*.charge_type_id' => ['required', Rule::exists('charge_types', 'id')->where('tenant_id', $this->user()->tenant_id)],
            'items.*.charges.*.rate' => ['nullable', 'numeric'],

            'invoice_charges' => ['nullable', 'array'],
            'invoice_charges.*.charge_type_id' => ['required', Rule::exists('charge_types', 'id')->where('tenant_id', $this->user()->tenant_id)],
            'invoice_charges.*.rate' => ['nullable', 'numeric'],
        ];
    }
}
