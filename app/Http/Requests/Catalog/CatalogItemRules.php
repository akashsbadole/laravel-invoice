<?php

namespace App\Http\Requests\Catalog;

use App\Support\CatalogField;
use App\Support\Industry;
use Illuminate\Validation\Rule;

/**
 * Validation and payload assembly driven by the catalog field registry, so
 * adding a field to `CatalogField::all()` is enough to make it validated,
 * accepted and persisted — no parallel list to keep in sync.
 */
trait CatalogItemRules
{
    /**
     * Columns whose length is tighter than the 255 default.
     *
     * @var array<string,int>
     */
    private static array $lengthOverrides = [
        'brand' => 100,
        'item_code' => 100,
        'model_number' => 100,
        'manufacturer' => 100,
        'material' => 100,
        'country_of_origin' => 100,
        'pack_size' => 100,
        'barcode' => 100,
        'specification' => 255,
        'size_label' => 50,
        'finish' => 50,
        'grade' => 50,
        'color' => 50,
        'thickness' => 50,
        'metal_type' => 50,
        'purity' => 20,
        'unit_label' => 20,
        'stock_unit' => 20,
        'hsn_code' => 20,
    ];

    /**
     * @return list<string>
     */
    public function allowedRateTypes(): array
    {
        return Industry::rateTypes($this->user()->tenant?->industry);
    }

    /**
     * Rules for every field in the registry, plus the two request-level
     * concerns (item code uniqueness and the attributes array).
     *
     * @return array<string,mixed>
     */
    protected function catalogRules(?int $ignoreItemId = null): array
    {
        $rules = [
            'item_code' => [
                'nullable',
                'string',
                'max:100',
                Rule::unique('catalog_items', 'item_code')
                    ->where('tenant_id', $this->user()->tenant_id)
                    ->ignore($ignoreItemId),
            ],
            'attributes' => ['nullable', 'array'],
            'attributes.*' => ['nullable', 'string', 'max:255'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remove_image' => ['boolean'],
        ];

        foreach (CatalogField::all() as $field) {
            // Covered above with tenant-scoped uniqueness.
            if ($field['name'] === 'item_code') {
                continue;
            }

            $rules[$field['name']] = match ($field['type']) {
                'text' => ['nullable', 'string', 'max:'.(self::$lengthOverrides[$field['name']] ?? 255)],
                'textarea' => ['nullable', 'string', 'max:2000'],
                'number' => $this->numberRules($field['name']),
                // Booleans are resolved with $request->boolean() so an
                // unchecked box clears the value instead of being ignored.
                'boolean' => ['boolean'],
                'select' => ['required', Rule::in($this->allowedRateTypes())],
                // image and attributes are handled by the controller.
                default => ['nullable'],
            };
        }

        $rules['name'] = ['required', 'string', 'max:255'];

        return $rules;
    }

    /**
     * @return list<mixed>
     */
    private function numberRules(string $field): array
    {
        $rules = ['nullable', 'numeric', 'min:0'];

        if ($field === 'default_wastage_percent') {
            $rules[] = 'max:100';
        }

        if ($field === 'minimum_order_quantity') {
            // Not required: the registry default of 1 applies when the form
            // omits it entirely (API clients, CSV import).
            $rules = ['nullable', 'integer', 'min:1'];
        }

        if ($field === 'warranty_months') {
            $rules = ['nullable', 'integer', 'min:0', 'max:600'];
        }

        return $rules;
    }

    /**
     * The validated attributes ready for the model, with blanks collapsed to
     * null so a cleared field actually clears the column.
     *
     * @return array<string,mixed>
     */
    public function catalogPayload(): array
    {
        $validated = $this->validated();

        $payload = [];

        foreach (CatalogField::all() as $field) {
            $name = $field['name'];

            if (! array_key_exists($name, $validated)) {
                continue;
            }

            $value = $validated[$name];

            if ($field['type'] === 'boolean') {
                $payload[$name] = $this->boolean($name);
            } elseif ($field['type'] === 'attributes') {
                $payload[$name] = $this->cleanAttributes(is_array($value) ? $value : []);
            } elseif (in_array($field['type'], ['text', 'textarea'], true)) {
                $payload[$name] = blank($value) ? null : $value;
            } elseif ($field['type'] === 'number') {
                // Blank numeric cells fall back to the registry default so a
                // NOT NULL column is never written as null.
                $number = $value === null || $value === '' ? null : $value + 0;
                $payload[$name] = $number ?? ($field['default'] ?? null);
            } else {
                $payload[$name] = $value === null ? null : $value;
            }
        }

        // is_active is a form concern rather than a registry field.
        $payload['is_active'] = $this->boolean('is_active');

        return $payload;
    }

    /**
     * @param  array<string,mixed>  $attributes
     * @return array<string,string>
     */
    protected function cleanAttributes(array $attributes): array
    {
        $clean = [];

        foreach ($attributes as $key => $value) {
            $key = trim((string) $key);
            $value = is_scalar($value) ? trim((string) $value) : '';

            if ($key !== '' && $value !== '') {
                $clean[$key] = $value;
            }
        }

        return $clean;
    }
}
