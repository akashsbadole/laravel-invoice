<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Enums\LineType;
use App\Enums\RateType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InvoiceItem extends Model
{
    use BelongsToTenant;

    /** @var list<string> */
    protected $fillable = [
        'invoice_id', 'sort_order', 'item_name', 'line_type', 'description', 'item_code',
        'catalog_item_id', 'catalog_variant_id', 'hsn_code',
        'brand', 'model_number', 'serial_number', 'warranty_months',
        'size_label', 'finish', 'grade', 'specification', 'batch_number',
        'length', 'width', 'height', 'wastage_percent', 'boxes', 'attributes',
        'metal_type', 'purity', 'huid_number',
        'stone_clarity', 'stone_color', 'stone_carat', 'certificate_number',
        'quantity', 'gross_weight', 'net_weight', 'stone_weight',
        'rate_type', 'rate', 'base_value', 'discount', 'tax_rate', 'tax', 'total',
    ];

    protected function casts(): array
    {
        return [
            'line_type' => LineType::class,
            'rate_type' => RateType::class,
            'quantity' => 'integer',
            'warranty_months' => 'integer',
            'attributes' => 'array',
            'length' => 'decimal:3',
            'width' => 'decimal:3',
            'height' => 'decimal:3',
            'boxes' => 'decimal:2',
            'wastage_percent' => 'decimal:2',
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
     * The catalog product this line was built from, if any.
     *
     * @return BelongsTo<CatalogItem, $this>
     */
    public function catalogItem(): BelongsTo
    {
        return $this->belongsTo(CatalogItem::class, 'catalog_item_id');
    }

    /**
     * The specific variant (size/colour) sold on this line, if any.
     *
     * @return BelongsTo<CatalogVariant, $this>
     */
    public function catalogVariant(): BelongsTo
    {
        return $this->belongsTo(CatalogVariant::class, 'catalog_variant_id');
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
