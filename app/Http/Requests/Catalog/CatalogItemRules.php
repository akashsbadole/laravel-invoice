<?php

namespace App\Http\Requests\Catalog;

use App\Enums\CatalogStatus;
use App\Models\CatalogItem;
use App\Models\CatalogVariant;
use App\Support\Attributes;
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
     * Fall back to the industry's first rate type when none was sent.
     *
     * The dialog pre-selects a rate type, but an untouched select is not
     * guaranteed to submit one, and "the rate type field is required" is a
     * confusing way to learn you filled in the product name. Falling back
     * matches what the CSV importer already does, and the column's own
     * database default, so the form can never be unsatisfiable.
     *
     * On update an omitted value means "leave it alone" rather than "reset
     * it", so the item's existing rate type is kept.
     */
    protected function prepareForValidation(): void
    {
        if (! blank($this->input('rate_type'))) {
            return;
        }

        $existing = $this->route('catalogItem');

        if ($existing !== null) {
            $this->merge(['rate_type' => $existing->rate_type?->value]);

            return;
        }

        $allowed = $this->allowedRateTypes();

        if ($allowed !== []) {
            $this->merge(['rate_type' => $allowed[0]]);
        }
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
            // Two shapes are accepted: a plain map (CSV importer, API) and indexed
            // key/value rows (the repeatable editor). Both are flattened to a
            // string map by catalogPayload().
            ...Attributes::rules(),
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

        // Status is a form concern (not a registry field) but validated here
        // so the enum is enforced centrally.
        $rules['status'] = ['nullable', Rule::enum(CatalogStatus::class)];

        // Optional one-to-many: sizes, colours or purities of this product.
        // Omitted entirely by clients that do not use variants.
        return $rules + $this->variantRules();
    }

    /**
     * Nested rows for the repeatable variant editor.
     *
     * @return array<string,mixed>
     */
    protected function variantRules(): array
    {
        $tenantId = $this->user()->tenant_id;

        return [
            'variants' => ['sometimes', 'array', 'max:50'],
            // An HTML form cannot post an empty array, so "the last variant
            // was deleted" arrives as an explicit flag rather than as
            // `variants => []`.
            'clear_variants' => ['sometimes', 'boolean'],
            'variants.*.id' => ['nullable', 'integer'],
            'variants.*.label' => ['required', 'string', 'max:100'],
            'variants.*.rate' => ['nullable', 'numeric', 'min:0'],
            'variants.*.stock_quantity' => ['nullable', 'numeric', 'min:0'],
            'variants.*.is_active' => ['sometimes', 'boolean'],
            // A variant's SKU shares the tenant's uniqueness space with
            // product codes, so one namespace covers the whole catalog and
            // the CSV importer can never collide with a row it cannot see.
            'variants.*.item_code' => ['nullable', 'string', 'max:100', function (string $attribute, mixed $value, $fail) use ($tenantId): void {
                if ($value === null || $value === '') {
                    return;
                }

                $rows = $this->input('variants');
                $index = (int) explode('.', $attribute)[1];
                $ownId = $rows[$index]['id'] ?? null;

                // Two rows in the same submit have not hit the database yet,
                // so the database cannot be the only thing asked.
                foreach ((array) $rows as $siblingIndex => $sibling) {
                    if ((int) $siblingIndex === $index) {
                        continue;
                    }

                    if (blank($sibling['item_code'] ?? null)) {
                        continue;
                    }

                    if ((string) $sibling['item_code'] === (string) $value) {
                        $fail(__('This item code is already used in your catalog.'));

                        return;
                    }
                }

                $taken = CatalogVariant::query()
                    ->where('tenant_id', $tenantId)
                    ->where('item_code', $value)
                    ->when($ownId, fn ($q) => $q->where('id', '!=', $ownId))
                    ->exists()
                    || CatalogItem::query()
                        ->where('tenant_id', $tenantId)
                        ->where('item_code', $value)
                        ->exists();

                if ($taken) {
                    $fail(__('This item code is already used in your catalog.'));
                }
            }],
        ];
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

        // Status is a form concern rather than a registry field. Defaults to
        // active when the request omits it (the create form pre-selects it).
        $payload['status'] = $this->input('status', CatalogStatus::Active->value);

        return $payload;
    }

    /**
     * The validated variant rows ready to be diffed against what the product
     * already has. Returns null when the request does not touch variants at
     * all, so an API client that has never heard of them cannot wipe them.
     *
     * @return list<array{id:int|null,label:string,item_code:string|null,rate:float|null,stock_quantity:float|null,is_active:bool,sort_order:int}>|null
     */
    public function variantsPayload(): ?array
    {
        if ($this->boolean('clear_variants')) {
            return [];
        }

        $rows = $this->validated('variants');

        if ($rows === null) {
            return null;
        }

        $clean = [];

        foreach (array_values($rows) as $position => $row) {
            $clean[] = [
                'id' => isset($row['id']) ? (int) $row['id'] : null,
                'label' => (string) $row['label'],
                'item_code' => blank($row['item_code'] ?? null) ? null : (string) $row['item_code'],
                'rate' => isset($row['rate']) && $row['rate'] !== '' ? (float) $row['rate'] : null,
                'stock_quantity' => isset($row['stock_quantity']) && $row['stock_quantity'] !== ''
                    ? (float) $row['stock_quantity']
                    : null,
                'is_active' => ! array_key_exists('is_active', $row) || filter_var($row['is_active'], FILTER_VALIDATE_BOOLEAN),
                'sort_order' => $position,
            ];
        }

        return $clean;
    }

    /**
     * Accepts either shape the attributes column can arrive in:
     * a plain map (`attributes[key] = value`, e.g. from the CSV importer) or
     * indexed pairs (`attributes[0][key]`, from the repeatable editor).
     * Rows missing either half are dropped.
     *
     * @param  array<array-key,mixed>  $attributes
     * @return array<string,string>
     */
    protected function cleanAttributes(array $attributes): array
    {
        return Attributes::clean($attributes);
    }
}
