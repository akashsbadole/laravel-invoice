<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

#[Fillable(['metal_type', 'purity', 'rate_date', 'rate_per_gram', 'created_by'])]
class MetalRate extends Model
{
    protected function casts(): array
    {
        return [
            'rate_date' => 'date:Y-m-d',
            'rate_per_gram' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * The most recent rate for every metal + purity combination.
     *
     * @return Collection<int, MetalRate>
     */
    public static function latestRates(): Collection
    {
        return static::query()
            ->orderByDesc('rate_date')
            ->orderByDesc('id')
            ->get()
            ->unique(fn (self $r) => strtolower($r->metal_type).'|'.strtolower($r->purity))
            ->values();
    }
}
