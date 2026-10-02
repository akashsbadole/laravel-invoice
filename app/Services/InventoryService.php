<?php

namespace App\Services;

use App\Models\CatalogItem;
use App\Models\CatalogVariant;
use App\Models\InventoryMovement;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Keeps catalog stock and the movement ledger in step.
 *
 * The catalog row is the fast read; the ledger is the explanation. Every
 * change goes through here so the balance can never drift, and an adjustment
 * is recorded as a correction rather than a silent overwrite.
 *
 * When a product is split into variants, each variant owns its own balance
 * and the product row is kept as the sum, so a product-level low-stock check
 * still means what it says.
 */
class InventoryService
{
    /**
     * Apply a signed delta and record the movement.
     *
     * `in`/`out` take a positive quantity and derive their sign, so callers
     * cannot accidentally stock a negative amount by passing -5 to `in`.
     * `adjustment` takes a signed quantity that sets the balance outright.
     *
     * @param  array{reason?:string|null,note?:string|null,reference_type?:string|null,reference_id?:int|null}  $context
     */
    public function move(
        CatalogItem $item,
        string $type,
        float $quantity,
        ?int $byUserId = null,
        array $context = [],
        ?CatalogVariant $variant = null,
    ): InventoryMovement {
        if ($quantity === 0.0) {
            throw new InvalidArgumentException('A stock movement must be non-zero.');
        }

        if ($type === InventoryMovement::TYPE_ADJUSTMENT) {
            $delta = $quantity;
        } elseif (in_array($type, [InventoryMovement::TYPE_IN, InventoryMovement::TYPE_OUT], true)) {
            $delta = $type === InventoryMovement::TYPE_IN ? abs($quantity) : -abs($quantity);
        } else {
            throw new InvalidArgumentException("Unknown stock movement type [{$type}].");
        }

        return DB::transaction(function () use ($item, $type, $delta, $byUserId, $context, $variant): InventoryMovement {
            if ($variant !== null) {
                $balance = $type === InventoryMovement::TYPE_ADJUSTMENT
                    ? $delta
                    : round((float) $variant->stock_quantity + $delta, 3);

                $variant->forceFill(['stock_quantity' => $balance])->save();

                // The product row is the sum of its variants, so a product
                // that has been split keeps reporting one honest number.
                $item->forceFill([
                    'stock_quantity' => round(
                        (float) $variant->newQuery()->where('catalog_item_id', $item->id)->sum('stock_quantity'),
                        3,
                    ),
                ])->save();
            } else {
                $current = (float) $item->stock_quantity;
                $balance = $type === InventoryMovement::TYPE_ADJUSTMENT
                    ? $delta
                    : round($current + $delta, 3);

                $item->forceFill(['stock_quantity' => $balance])->save();
            }

            return InventoryMovement::create([
                'catalog_item_id' => $item->id,
                'catalog_variant_id' => $variant?->id,
                'type' => $type,
                'quantity' => $delta,
                'balance_after' => $balance,
                'reason' => $context['reason'] ?? null,
                'note' => $context['note'] ?? null,
                'reference_type' => $context['reference_type'] ?? null,
                'reference_id' => $context['reference_id'] ?? null,
                'created_by' => $byUserId,
            ]);
        });
    }

    /**
     * Opening stock when a product starts tracking, recorded as a movement so
     * the ledger explains the figure shown on the product.
     */
    public function setOpeningStock(CatalogItem $item, float $quantity, ?int $byUserId = null, ?CatalogVariant $variant = null): InventoryMovement
    {
        return $this->move(
            $item,
            InventoryMovement::TYPE_ADJUSTMENT,
            round($quantity, 3),
            $byUserId,
            ['reason' => 'Opening stock'],
            $variant,
        );
    }

    /**
     * Drop a variant that still holds stock without losing the explanation
     * for where that stock went: the remaining quantity is written off as a
     * single movement, then the product total is recomputed.
     */
    public function removeVariant(CatalogItem $item, CatalogVariant $variant, ?int $byUserId = null): void
    {
        DB::transaction(function () use ($item, $variant, $byUserId): void {
            $held = round((float) $variant->stock_quantity, 3);

            if ($held !== 0.0) {
                InventoryMovement::create([
                    'catalog_item_id' => $item->id,
                    'catalog_variant_id' => $variant->id,
                    'type' => InventoryMovement::TYPE_OUT,
                    'quantity' => -$held,
                    'balance_after' => 0,
                    'reason' => 'Variant removed',
                    'created_by' => $byUserId,
                ]);
            }

            $variant->delete();

            $item->forceFill([
                'stock_quantity' => round((float) $item->variants()->sum('stock_quantity'), 3),
            ])->save();
        });
    }

    /**
     * @return Collection<int,InventoryMovement>
     */
    public function history(CatalogItem $item, int $limit = 20)
    {
        return $item->movements()->latest('id')->limit($limit)->get();
    }
}
