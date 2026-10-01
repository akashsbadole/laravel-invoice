<?php

namespace App\Support;

/**
 * Normalises the repeatable key/value editor's payload.
 *
 * The same editor posts to customers, catalog items and invoices, and a CSV
 * import supplies a plain map instead. Both shapes are accepted so the rule
 * lives in one place rather than three near-identical request methods.
 */
final class Attributes
{
    /**
     * Accepts a plain map (`['thread' => '2x40']`, as sent by a CSV or an
     * API client) or indexed pairs (`[['key' => 'thread', 'value' => '2x40']]`,
     * as posted by the repeatable editor). Rows missing either half are
     * dropped rather than stored half-empty.
     *
     * @param  array<array-key,mixed>  $input
     * @return array<string,string>
     */
    public static function clean(array $input): array
    {
        $clean = [];

        foreach ($input as $key => $value) {
            if (is_array($value)) {
                $key = (string) ($value['key'] ?? '');
                $value = $value['value'] ?? '';
            }

            $key = trim((string) $key);
            $value = is_scalar($value) ? trim((string) $value) : '';

            if ($key !== '' && $value !== '') {
                $clean[$key] = $value;
            }
        }

        return $clean;
    }

    /**
     * Validation rules for either accepted shape.
     *
     * @return array<string,list<string>>
     */
    public static function rules(): array
    {
        return [
            'attributes' => ['nullable', 'array'],
            // Either a scalar value or a [key, value] pair row.
            'attributes.*' => ['nullable'],
            'attributes.*.key' => ['nullable', 'string', 'max:255'],
            'attributes.*.value' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * Flatten a stored map back into the `key=value;key2=value2` form the CSV
     * importer and exporter agree on.
     *
     * @param  array<string,mixed>|null  $attributes
     */
    public static function toString(?array $attributes): string
    {
        if (! $attributes) {
            return '';
        }

        $pairs = [];

        foreach ($attributes as $key => $value) {
            if (is_scalar($value) && (string) $value !== '') {
                $pairs[] = $key.'='.(string) $value;
            }
        }

        return implode(';', $pairs);
    }

    /**
     * The inverse of toString(), for CSV imports.
     *
     * @return array<string,string>
     */
    public static function fromString(?string $value): array
    {
        if (blank($value)) {
            return [];
        }

        $parsed = [];

        foreach (explode(';', $value) as $pair) {
            if (! str_contains($pair, '=')) {
                continue;
            }

            [$key, $val] = explode('=', $pair, 2);
            $key = trim($key);
            $val = trim($val);

            if ($key !== '' && $val !== '') {
                $parsed[$key] = $val;
            }
        }

        return $parsed;
    }
}
