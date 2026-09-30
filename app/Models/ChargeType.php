<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Enums\ChargeAppliesTo;
use App\Enums\ChargeCalculationType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ChargeType extends Model
{
    use BelongsToTenant;

    /** @var list<string> */
    protected $fillable = [
        'name', 'code', 'calculation_type', 'applies_to', 'default_rate',
        'is_taxable', 'is_system', 'is_active', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'calculation_type' => ChargeCalculationType::class,
            'applies_to' => ChargeAppliesTo::class,
            'default_rate' => 'decimal:2',
            'is_taxable' => 'boolean',
            'is_system' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeAppliesTo(Builder $query, ChargeAppliesTo $appliesTo): Builder
    {
        return $query->where('applies_to', $appliesTo->value);
    }

    /**
     * System charge types (making, wastage, stone, other) can be renamed,
     * re-rated, or deactivated, but never deleted — invoicing assumes
     * they always exist as an option.
     */
    public function isDeletable(): bool
    {
        return ! $this->is_system;
    }
}
