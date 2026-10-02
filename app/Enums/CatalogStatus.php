<?php

namespace App\Enums;

/**
 * Publication state of a catalog item.
 *
 * Draft: not yet ready to sell — hidden from the quotation builder and the
 * invoice product picker. Active: live and selectable. Inactive: previously
 * published but taken off sale (e.g. discontinued), still editable.
 */
enum CatalogStatus: string
{
    case Draft = 'draft';

    case Active = 'active';

    case Inactive = 'inactive';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Active => 'Active',
            self::Inactive => 'Inactive',
        };
    }

    /**
     * Whether the item is visible to the quotation builder and invoice picker.
     */
    public function isLive(): bool
    {
        return $this === self::Active;
    }
}
