<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One sellable version of a product — a size, colour, purity or pack.
 *
 * A shop that used to create three near-identical products ("Shirt",
 * "Shirt", "Shirt") keeps one product row and three variants instead, so
 * the picker, the stock figure and the item code all stay readable.
 */
class CatalogVariant extends Model
{
    use BelongsToTenant;

    /** @var list<string> */
    protected $fillable = [
        'catalog_item_id', 'label', 'item_code', 'attributes',
        'rate', 'stock_quantity', 'is_active', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'attributes' => 'array',
            'rate' => 'decimal:2',
            'stock_quantity' => 'decimal:3',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<CatalogItem, $this>
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(CatalogItem::class, 'catalog_item_id');
    }

    /**
     * Invoice lines that were sold as this variant.
     *
     * @return HasMany<InvoiceItem, $this>
     */
    public function invoiceItems(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    /**
     * What the customer pays: the variant's own rate when it has one,
     * otherwise the product's default rate.
     */
    public function effectiveRate(): ?float
    {
        if ($this->rate !== null) {
            return (float) $this->rate;
        }

        $itemRate = $this->item?->default_rate;

        return $itemRate === null ? null : (float) $itemRate;
    }

    /**
     * The line description shown on invoices and PDFs.
     */
    public function describe(string $productName): string
    {
        return sprintf('%s (%s)', $productName, $this->label);
    }
}
