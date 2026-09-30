<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Enums\RateType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InvoiceItem extends Model
{
    use BelongsToTenant;

    /** @var list<string> */
    protected $fillable = [
        'invoice_id', 'sort_order', 'item_name', 'description', 'item_code', 'hsn_code',
        'metal_type', 'purity', 'huid_number',
        'stone_clarity', 'stone_color', 'stone_carat', 'certificate_number',
        'quantity', 'gross_weight', 'net_weight', 'stone_weight',
        'rate_type', 'rate', 'base_value', 'discount', 'tax_rate', 'tax', 'total',
    ];

    protected function casts(): array
    {
        return [
            'rate_type' => RateType::class,
            'quantity' => 'integer',
            'gross_weight' => 'decimal:3',
            'net_weight' => 'decimal:3',
            'stone_weight' => 'decimal:3',
            'stone_carat' => 'decimal:3',
            'rate' => 'decimal:2',
            'base_value' => 'decimal:2',
            'discount' => 'decimal:2',
            'tax_rate' => 'decimal:2',
            'tax' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Invoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * Dynamic per-item charges (making, wastage, stone, hallmarking, ...) —
     * see App\Services\InvoiceCalculationService for how these are computed.
     *
     * @return HasMany<InvoiceItemCharge, $this>
     */
    public function charges(): HasMany
    {
        return $this->hasMany(InvoiceItemCharge::class);
    }
}
