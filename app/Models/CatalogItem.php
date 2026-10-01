<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
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
        'attributes', 'description', 'is_active', 'created_by',
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
            'attributes' => 'array',
            'is_active' => 'boolean',
        ];
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
