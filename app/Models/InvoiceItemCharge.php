<?php

namespace App\Models;

use App\Enums\ChargeCalculationType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceItemCharge extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'invoice_item_id', 'charge_type_id', 'label', 'code', 'calculation_type',
        'is_taxable', 'rate', 'amount', 'sort_order',
    ];

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
     * @return BelongsTo<InvoiceItem, $this>
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(InvoiceItem::class, 'invoice_item_id');
    }

    /**
     * @return BelongsTo<ChargeType, $this>
     */
    public function chargeType(): BelongsTo
    {
        return $this->belongsTo(ChargeType::class);
    }
}
