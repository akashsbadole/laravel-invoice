<?php

namespace App\Models;

use App\Enums\RateType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CatalogItem extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'name', 'item_code', 'hsn_code', 'metal_type', 'purity', 'rate_type',
        'default_rate', 'default_net_weight', 'default_gross_weight',
        'description', 'is_active', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'rate_type' => RateType::class,
            'default_rate' => 'decimal:2',
            'default_net_weight' => 'decimal:3',
            'default_gross_weight' => 'decimal:3',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
