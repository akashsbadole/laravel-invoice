<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A named pricing tier (Wholesale, Staff, VIP) that takes a percentage off
 * invoice lines the shopkeeper has not priced by hand.
 */
class CustomerGroup extends Model
{
    use BelongsToTenant;

    /** @var list<string> */
    protected $fillable = [
        'name', 'discount_percent', 'is_active', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'discount_percent' => 'decimal:2',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return HasMany<Customer, $this>
     */
    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }

    /**
     * How much this group takes off a line, clamped to a sane percentage.
     * An inactive group prices nothing.
     */
    public function effectiveDiscountPercent(): float
    {
        if (! $this->is_active) {
            return 0.0;
        }

        return min(max((float) $this->discount_percent, 0), 100);
    }
}
