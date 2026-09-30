<?php

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case InvoiceCreator = 'invoice_creator';
    case Viewer = 'viewer';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Admin',
            self::InvoiceCreator => 'Invoice Creator',
            self::Viewer => 'Viewer',
        };
    }

    /**
     * Roles allowed to create or edit invoices and customers.
     */
    public function canWrite(): bool
    {
        return match ($this) {
            self::Admin, self::InvoiceCreator => true,
            self::Viewer => false,
        };
    }

    public function canManageUsers(): bool
    {
        return $this === self::Admin;
    }

    public function canManageSettings(): bool
    {
        return $this === self::Admin;
    }
}
