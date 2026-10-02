<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Enums\CatalogStatus;
use App\Enums\RateType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CatalogItem extends Model
{
    use BelongsToTenant;

    /** @var list<string> */
    protected $fillable = [
        'name', 'brand', 'item_code', 'model_number', 'hsn_code',
        'metal_type', 'purity', 'rate_type',
        'size_label', 'finish', 'grade', 'specification', 'unit_label',
        'default_rate', 'default_net_weight', 'default_gross_weight',
        'default_length', 'default_width', 'default_wastage_percent',
        'cost_price', 'minimum_order_quantity', 'pack_size', 'tax_inclusive',
        'barcode', 'color', 'material', 'thickness', 'warranty_months',
        'manufacturer', 'country_of_origin',
        // Fields added for furniture, textiles, electronics, paint and
        // contractors; each industry opts into them via item_fields.
        'fabric', 'weave', 'pattern', 'serial_number', 'shade_code',
        'volume', 'coverage_area', 'service_type', 'site_reference',
        'batch_number', 'boxes',
        'stock_tracked', 'stock_quantity', 'reorder_level', 'stock_unit',
        'image_path',
        'attributes', 'description', 'status', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'rate_type' => RateType::class,
            'default_rate' => 'decimal:2',
            'default_net_weight' => 'decimal:3',
            'default_gross_weight' => 'decimal:3',
            'default_length' => 'decimal:3',
            'default_width' => 'decimal:3',
            'default_wastage_percent' => 'decimal:2',
            'cost_price' => 'decimal:2',
            'minimum_order_quantity' => 'integer',
            'warranty_months' => 'integer',
            'volume' => 'decimal:3',
            'coverage_area' => 'decimal:3',
            'boxes' => 'decimal:2',
            'tax_inclusive' => 'boolean',
            'stock_tracked' => 'boolean',
            'stock_quantity' => 'decimal:3',
            'reorder_level' => 'decimal:3',
            'attributes' => 'array',
            'status' => CatalogStatus::class,
        ];
    }

    /**
     * Append-only history behind stock_quantity.
     *
     * @return HasMany<InventoryMovement, $this>
     */
    public function movements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class);
    }

    /**
     * Stock at or below the reorder level, but only for products that opt in.
     */
    public function isLowOnStock(): bool
    {
        return $this->stock_tracked
            && (float) $this->reorder_level > 0
            && (float) $this->stock_quantity <= (float) $this->reorder_level;
    }

    /**
     * Whether the item is live in the quotation builder and invoice picker.
     */
    public function isActive(): bool
    {
        return $this->status === CatalogStatus::Active;
    }

    public function isDraft(): bool
    {
        return $this->status === CatalogStatus::Draft;
    }

    /**
     * The sellable versions of this product (sizes, colours, purities).
     *
     * @return HasMany<CatalogVariant, $this>
     */
    public function variants(): HasMany
    {
        return $this->hasMany(CatalogVariant::class)->orderBy('sort_order')->orderBy('id');
    }

    public function hasVariants(): bool
    {
        return $this->variants()->exists();
    }

    /**
     * Invoice lines built from this product.
     *
     * @return HasMany<InvoiceItem, $this>
     */
    public function invoiceItems(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
