<?php

namespace App\Models;

use App\Enums\ChargeCalculationType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'invoice_id', 'charge_type_id', 'label', 'code', 'calculation_type',
    'is_taxable', 'rate', 'amount', 'sort_order',
])]
class InvoiceCharge extends Model
{
    protected function casts(): array
    {
        return [
            'calculation_type' => ChargeCalculationType::class,
            'is_taxable' => 'boolean',
            'rate' => 'decimal:2',
            'amount' => 'decimal:2',
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
     * @return BelongsTo<ChargeType, $this>
     */
    public function chargeType(): BelongsTo
    {
        return $this->belongsTo(ChargeType::class);
    }
}
