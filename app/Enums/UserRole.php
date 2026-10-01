<?php

namespace App\Enums;

enum UserRole: string
{
    /**
     * Platform operator. Has no tenant of their own and manages the whole
     * application: tenants, plans and subscriptions.
     */
    case SuperAdmin = 'super_admin';

    /** Full control of a single tenant, including settings and staff. */
    case Admin = 'admin';

    /** Runs the day to day; no destructive settings or staff changes. */
    case Manager = 'manager';

    /** Quotes and invoices only. */
    case InvoiceCreator = 'invoice_creator';

    /** Read-only. */
    case Viewer = 'viewer';

    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Super Admin',
            self::Admin => 'Admin',
            self::Manager => 'Manager',
            self::InvoiceCreator => 'Invoice Creator',
            self::Viewer => 'Viewer',
        };
    }

    /**
     * Roles a tenant administrator may assign. The super admin is a platform
     * account, never something a tenant can hand out.
     *
     * @return list<self>
     */
    public static function assignable(): array
    {
        return [self::Admin, self::Manager, self::InvoiceCreator, self::Viewer];
    }

    /**
     * @return list<string>
     */
    public function permissions(): array
    {
        /** @var array<string, list<string>> $matrix */
        $matrix = config('permissions', []);

        return $matrix[$this->value] ?? [];
    }

    /**
     * @param  Permission|string  $permission
     */
    public function can($permission): bool
    {
        $key = $permission instanceof Permission ? $permission->value : $permission;

        return in_array($key, $this->permissions(), true);
    }

    /**
     * Roles allowed to create or edit invoices and customers.
     */
    public function canWrite(): bool
    {
        return $this->can(Permission::CreateInvoices);
    }

    public function canManageUsers(): bool
    {
        return $this->can(Permission::ManageUsers);
    }

    public function canManageSettings(): bool
    {
        return $this->can(Permission::ManageSettings);
    }

    public function isSuperAdmin(): bool
    {
        return $this === self::SuperAdmin;
    }

    /**
     * A platform account is not scoped to a tenant, so it must never be given
     * one — tenant data is reached through the admin panel instead.
     */
    public function belongsToTenant(): bool
    {
        return $this !== self::SuperAdmin;
    }
}
