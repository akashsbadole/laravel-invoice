<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'slug', 'is_default', 'layout_config'])]
class InvoiceTemplate extends Model
{
    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'layout_config' => 'array',
        ];
    }

    /**
     * @return HasMany<Invoice, $this>
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public static function currentDefault(): self
    {
        return static::query()->where('is_default', true)->first()
            ?? static::query()->firstOrCreate(
                ['slug' => 'default'],
                ['name' => 'Default', 'is_default' => true, 'layout_config' => static::defaultLayoutConfig()],
            );
    }

    /**
     * @return array<string, mixed>
     */
    public static function defaultLayoutConfig(): array
    {
        return [
            'accent_color' => '#0f172a',
            'header_alignment' => 'left',
            'show_huid' => true,
            'show_hsn' => true,
            'show_stone_details' => true,
            'show_bank_details' => true,
            'show_signature' => true,
            'show_stamp' => true,
            'show_qr_code' => true,
            'footer_note' => '',
        ];
    }

    /**
     * Read one layout_config key, falling back to the documented default
     * so older templates saved before a new toggle existed still work.
     */
    public function config(string $key, mixed $default = null): mixed
    {
        return data_get($this->layout_config, $key, $default ?? static::defaultLayoutConfig()[$key] ?? null);
    }
}
